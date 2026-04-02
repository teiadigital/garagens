<?php

declare(strict_types=1);

namespace Drupal\commerce_ifthenpay\Controller;

use Drupal\commerce_ifthenpay\Service\IfthenpayApiService;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller para as páginas locais de pagamento do ifthenpay.
 *
 * Gere:
 * - Página de informações Multibanco (entidade/referência/validade)
 * - Formulário MB WAY (introdução de número de telemóvel)
 * - Página de espera MB WAY (aguarda confirmação)
 * - Endpoint de estado MB WAY (polling via AJAX)
 */
final class IfthenpayController extends ControllerBase {

  public function __construct(
    private readonly IfthenpayApiService $apiService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('commerce_ifthenpay.api_service'),
    );
  }

  // =========================================================================
  // MULTIBANCO
  // =========================================================================

  /**
   * Página de informações do pagamento Multibanco.
   *
   * Mostra a entidade, referência e data de expiração ao cliente.
   */
  public function multibancoInfo(OrderInterface $commerce_order, Request $request): array|RedirectResponse {
    $order = $commerce_order;

    // Recuperar dados da sessão.
    $sessionKey = 'ifthenpay_multibanco_' . $order->id();
    $data = $request->getSession()->get($sessionKey);

    if (!$data) {
      // Tentar carregar do pagamento guardado.
      $data = $this->loadMultibancoDataFromPayment($order);
    }

    if (!$data) {
      $this->messenger()->addError($this->t('Dados do pagamento Multibanco não encontrados. Por favor tente novamente.'));
      return new RedirectResponse(Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step'           => 'payment',
      ])->toString());
    }

    // URL de retorno: /checkout/{order_id}/payment/return
    // Visitar este URL chama onReturn() e completa o checkout do Commerce.
    $return_url = $data['return_url'] ?? Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ])->setAbsolute()->toString();

    return [
      '#theme'       => 'commerce_ifthenpay_multibanco',
      '#order'       => $order,
      '#entity'      => $data['entity'] ?? '',
      '#reference'   => $this->formatReference($data['reference'] ?? ''),
      '#amount'      => $data['amount'] ?? '',
      '#expiry_date' => $data['expiryDate'] ?? '',
      '#return_url'  => $return_url,
      '#attached'    => ['library' => ['commerce_ifthenpay/ifthenpay_base']],
    ];
  }

  // =========================================================================
  // MB WAY
  // =========================================================================

  /**
   * Página de checkout MB WAY.
   *
   * Mostra o formulário de introdução do número de telemóvel ou,
   * se o número já foi submetido, a página de espera.
   */
  public function mbwayCheckout(OrderInterface $commerce_order, Request $request): array|RedirectResponse|Response {
    $order = $commerce_order;

    // Verificar se é um POST (submissão do número de telemóvel).
    if ($request->isMethod('POST')) {
      return $this->handleMbWayPhoneSubmit($order, $request);
    }

    // Verificar se já está na fase de espera (telemóvel submetido anteriormente).
    $pendingData = $request->getSession()->get('ifthenpay_mbway_pending_' . $order->id());
    if ($pendingData) {
      return $this->renderMbWayPending($order, $pendingData, $request);
    }

    // Mostrar formulário de introdução do número de telemóvel.
    return $this->renderMbWayPhoneForm($order, $request);
  }

  /**
   * Endpoint de verificação do estado do pagamento MB WAY (AJAX polling).
   */
  public function mbwayStatus(OrderInterface $commerce_order, Request $request): JsonResponse {
    $order = $commerce_order;

    // Validar CSRF token.
    $token = $request->query->get('token');
    if (!$this->csrfToken()->validate((string) $token, 'mbway_status_' . $order->id())) {
      return new JsonResponse(['status' => 'error', 'message' => 'Token inválido.'], Response::HTTP_FORBIDDEN);
    }

    // Carregar o gateway e o pagamento.
    $payment = $this->loadPendingMbWayPayment($order);
    if (!$payment) {
      // Se não há pagamento pendente, verificar se foi completado.
      $completedPayment = $this->loadCompletedMbWayPayment($order);
      if ($completedPayment) {
        return new JsonResponse(['status' => 'paid', 'redirect' => $this->getOrderCompleteUrl($order)]);
      }
      return new JsonResponse(['status' => 'not_found'], Response::HTTP_NOT_FOUND);
    }

    // Verificar se o pagamento já foi marcado como completo (via callback).
    if ($payment->getState()->getId() === 'completed') {
      return new JsonResponse(['status' => 'paid', 'redirect' => $this->getOrderCompleteUrl($order)]);
    }

    // Fazer polling à API do ifthenpay para verificar estado.
    try {
      $gatewayPlugin = $payment->getPaymentGateway()->getPlugin();
      $config = $gatewayPlugin->getConfiguration();

      $requestId = $payment->getRemoteId();
      if (empty($requestId)) {
        return new JsonResponse(['status' => 'pending']);
      }

      $apiStatus = $this->apiService->getMbWayPaymentStatus(
        mbWayKey: $config['mbway_key'],
        requestId: $requestId,
      );

      // "000" = pago.
      if ($apiStatus === '000') {
        $payment->setState('completed');
        $payment->setRemoteState('paid');
        $payment->save();
        return new JsonResponse(['status' => 'paid', 'redirect' => $this->getOrderCompleteUrl($order)]);
      }

      // Statuses de erro/recusa.
      $failedStatuses = ['020' => FALSE]; // 020 = pendente (aguarda)
      // Outros statuses = erro/recusado/expirado.
      if ($apiStatus !== '020' && $apiStatus !== '000') {
        $payment->setState('authorization_voided');
        $payment->setRemoteState($apiStatus);
        $payment->save();
        return new JsonResponse(['status' => 'failed', 'code' => $apiStatus]);
      }

      return new JsonResponse(['status' => 'pending']);
    }
    catch (\Exception $e) {
      $this->getLogger('commerce_ifthenpay')->warning(
        'Erro ao verificar estado MB WAY: @err',
        ['@err' => $e->getMessage()]
      );
      // Retornar pending em caso de erro de comunicação (não falhar o pagamento).
      return new JsonResponse(['status' => 'pending']);
    }
  }

  // =========================================================================
  // Métodos privados
  // =========================================================================

  /**
   * Renderiza o formulário de introdução do número de telemóvel MB WAY.
   */
  private function renderMbWayPhoneForm(OrderInterface $order, Request $request): array {
    $token = $this->csrfToken()->get('mbway_phone_' . $order->id());

    return [
      '#theme'      => FALSE,
      '#type'       => 'markup',
      '#markup'     => '',
      // Usamos um form render array em vez de um tema para ter CSRF integrado.
      'form' => [
        '#type'   => 'container',
        '#attributes' => ['class' => ['ifthenpay-mbway-form']],
        'title'   => [
          '#markup' => '<h2>' . $this->t('Pagamento com MB WAY') . '</h2>',
        ],
        'description' => [
          '#markup' => '<p>' . $this->t('Introduza o seu número de telemóvel para receber a notificação MB WAY de pagamento.') . '</p>',
        ],
        'form_element' => $this->buildMbWayPhoneFormArray($order, $token),
      ],
      '#attached' => ['library' => ['commerce_ifthenpay/ifthenpay_base']],
    ];
  }

  /**
   * Constrói o array do formulário do telemóvel MB WAY.
   */
  private function buildMbWayPhoneFormArray(OrderInterface $order, string $token): array {
    $cancel_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ])->toString();

    $form_url = Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
      'commerce_order' => $order->id(),
    ])->toString();

    $amount = $this->getOrderAmount($order);

    return [
      '#type'   => 'html_tag',
      '#tag'    => 'form',
      '#attributes' => [
        'method' => 'post',
        'action' => $form_url,
        'class'  => ['ifthenpay-mbway-phone-form'],
      ],
      'csrf_token' => [
        '#type'  => 'html_tag',
        '#tag'   => 'input',
        '#attributes' => [
          'type'  => 'hidden',
          'name'  => 'csrf_token',
          'value' => $token,
        ],
      ],
      'amount_display' => [
        '#markup' => '<div class="ifthenpay-amount"><strong>' . $this->t('Total a pagar:') . '</strong> ' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . ' €</div>',
      ],
      'phone_wrapper' => [
        '#type'   => 'html_tag',
        '#tag'    => 'div',
        '#attributes' => ['class' => ['form-item']],
        'label'   => [
          '#markup' => '<label for="mbway_phone">' . $this->t('Número de telemóvel') . ' <span class="form-required">*</span></label>',
        ],
        'input'   => [
          '#type'  => 'html_tag',
          '#tag'   => 'input',
          '#attributes' => [
            'type'        => 'tel',
            'id'          => 'mbway_phone',
            'name'        => 'mbway_phone',
            'required'    => TRUE,
            'placeholder' => '9XXXXXXXX',
            'pattern'     => '[0-9+]{9,15}',
            'maxlength'   => '15',
            'class'       => ['form-text'],
            'autocomplete'=> 'tel',
          ],
        ],
        'description' => [
          '#markup' => '<div class="description">' . $this->t('Introduza o número sem espaços (ex: 912345678 ou 351912345678). O indicativo é adicionado automaticamente.') . '</div>',
        ],
      ],
      'submit' => [
        '#type'  => 'html_tag',
        '#tag'   => 'button',
        '#attributes' => [
          'type'  => 'submit',
          'class' => ['button', 'button--primary'],
        ],
        '#value' => $this->t('Pagar com MB WAY'),
      ],
      'cancel' => [
        '#markup' => '<a href="' . htmlspecialchars($cancel_url, ENT_QUOTES, 'UTF-8') . '" class="button">' . $this->t('Cancelar') . '</a>',
      ],
    ];
  }

  /**
   * Processa a submissão do número de telemóvel MB WAY.
   */
  private function handleMbWayPhoneSubmit(OrderInterface $order, Request $request): RedirectResponse|array {
    // Validar CSRF token.
    $submittedToken = (string) ($request->request->get('csrf_token') ?? '');
    if (!$this->csrfToken()->validate($submittedToken, 'mbway_phone_' . $order->id())) {
      $this->messenger()->addError($this->t('Pedido inválido. Por favor tente novamente.'));
      return new RedirectResponse(Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
        'commerce_order' => $order->id(),
      ])->toString());
    }

    // Validar e sanitizar número de telemóvel.
    $rawPhone = (string) ($request->request->get('mbway_phone') ?? '');
    $phone    = preg_replace('/[^0-9+]/', '', $rawPhone);

    if (empty($phone)) {
      $this->messenger()->addError($this->t('Por favor introduza o número de telemóvel.'));
      return new RedirectResponse(Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
        'commerce_order' => $order->id(),
      ])->toString());
    }

    // Normalizar: adicionar indicativo 351 se necessário.
    $phone = $this->apiService->formatMbWayPhone($phone);

    if (!$this->isValidMbWayPhone($phone)) {
      $this->messenger()->addError($this->t('Número de telemóvel inválido. Por favor verifique o número introduzido.'));
      return new RedirectResponse(Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
        'commerce_order' => $order->id(),
      ])->toString());
    }

    // Carregar o pagamento.
    $paymentId = $request->getSession()->get('ifthenpay_mbway_payment_' . $order->id());
    $payment   = $paymentId ? \Drupal::entityTypeManager()->getStorage('commerce_payment')->load($paymentId) : NULL;

    if (!$payment) {
      $this->messenger()->addError($this->t('Sessão de pagamento expirada. Por favor tente novamente.'));
      return new RedirectResponse(Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step'           => 'payment',
      ])->toString());
    }

    $gatewayPlugin = $payment->getPaymentGateway()->getPlugin();
    $config        = $gatewayPlugin->getConfiguration();
    $amount        = number_format((float) $payment->getAmount()->getNumber(), 2, '.', '');
    $description   = (string) $this->t('Pedido #@id', ['@id' => $order->id()]);

    // Email do cliente (se disponível).
    $email = '';
    if ($order->getEmail()) {
      $email = $order->getEmail();
    }

    try {
      $result = $this->apiService->createMbWayPayment(
        mbWayKey: $config['mbway_key'],
        orderId: (string) $order->id(),
        amount: $amount,
        mobileNumber: $phone,
        description: $description,
        email: $email,
      );

      // Persistir o ID da transação no pagamento.
      $payment->setRemoteId($result['requestId']);
      $payment->setRemoteState('pending');
      $payment->setState('authorization');
      $payment->save();

      // Guardar na sessão para a página de espera.
      $minutesToExpire = (int) ($config['minutes_to_expire'] ?? 4);
      $pendingData = [
        'phone'         => substr($phone, -9), // Mostrar só últimos 9 dígitos
        'amount'        => $amount,
        'requestId' => $result['requestId'],
        'expiresAt'     => time() + ($minutesToExpire * 60),
        'minutesToExpire'=> $minutesToExpire,
      ];
      $request->getSession()->set('ifthenpay_mbway_pending_' . $order->id(), $pendingData);

      // Redirecionar para a mesma página (que agora mostrará a página de espera).
      return new RedirectResponse(Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
        'commerce_order' => $order->id(),
      ])->toString());
    }
    catch (\Exception $e) {
      $this->getLogger('commerce_ifthenpay')->error(
        'Erro ao iniciar pagamento MB WAY. OrderId: @oid, Erro: @err',
        ['@oid' => $order->id(), '@err' => $e->getMessage()]
      );
      $this->messenger()->addError($this->t('Não foi possível iniciar o pagamento MB WAY. @err', [
        '@err' => $e->getMessage(),
      ]));
      return new RedirectResponse(Url::fromRoute('commerce_ifthenpay.mbway_checkout', [
        'commerce_order' => $order->id(),
      ])->toString());
    }
  }

  /**
   * Renderiza a página de espera do MB WAY.
   */
  private function renderMbWayPending(OrderInterface $order, array $pendingData, Request $request): array {
    $statusToken = $this->csrfToken()->get('mbway_status_' . $order->id());

    $cancel_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ])->setAbsolute()->toString();

    $status_url = Url::fromRoute('commerce_ifthenpay.mbway_status', [
      'commerce_order' => $order->id(),
    ])->setAbsolute()->toString();

    $return_url = $this->getOrderCompleteUrl($order);

    return [
      '#theme'        => 'commerce_ifthenpay_mbway',
      '#order'        => $order,
      '#mobile_number'=> $pendingData['phone'] ?? '',
      '#amount'       => $pendingData['amount'] ?? '',
      '#expires_at'   => $pendingData['expiresAt'] ?? (time() + 240),
      '#status_url'   => $status_url,
      '#return_url'   => $return_url,
      '#cancel_url'   => $cancel_url,
      '#attached'     => [
        'library'       => ['commerce_ifthenpay/mbway_status'],
        'drupalSettings'=> [
          'commerceIfthenpay' => [
            'statusUrl'   => $status_url,
            'statusToken' => $statusToken,
            'returnUrl'   => $return_url,
            'cancelUrl'   => $cancel_url,
            'expiresAt'   => $pendingData['expiresAt'] ?? (time() + 240),
          ],
        ],
      ],
    ];
  }

  // =========================================================================
  // Utilitários
  // =========================================================================

  private function isValidMbWayPhone(string $phone): bool {
    // Após formatMbWayPhone() do IfthenpayApiService, o formato correto é:
    // "351#XXXXXXXXX" (indicativo + # + 9 dígitos).
    return (bool) preg_match('/^\d{1,4}#\d{9}$/', $phone);
  }

  private function formatReference(string $reference): string {
    // Formatar referência em grupos de 3 (ex: 123 456 789).
    $clean = preg_replace('/\D/', '', $reference);
    if (strlen($clean) === 9) {
      return substr($clean, 0, 3) . ' ' . substr($clean, 3, 3) . ' ' . substr($clean, 6, 3);
    }
    return $reference;
  }

  private function getOrderAmount(OrderInterface $order): string {
    $total = $order->getTotalPrice();
    if (!$total) {
      return '0.00';
    }
    return number_format((float) $total->getNumber(), 2, ',', '.');
  }

  private function getOrderCompleteUrl(OrderInterface $order): string {
    // /checkout/{order_id}/payment/return → onReturn() → checkout complete.
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step'           => 'payment',
    ])->setAbsolute()->toString();
  }

  private function loadPendingMbWayPayment(OrderInterface $order): ?\Drupal\commerce_payment\Entity\PaymentInterface {
    $storage  = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $storage->loadMultipleByOrder($order);

    foreach ($payments as $payment) {
      if ($payment->getPaymentGatewayId() === 'ifthenpay_mbway') {
        if (in_array($payment->getState()->getId(), ['new', 'authorization', 'pending'], TRUE)) {
          return $payment;
        }
      }
    }
    return NULL;
  }

  private function loadCompletedMbWayPayment(OrderInterface $order): ?\Drupal\commerce_payment\Entity\PaymentInterface {
    $storage  = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $storage->loadMultipleByOrder($order);

    foreach ($payments as $payment) {
      if ($payment->getPaymentGatewayId() === 'ifthenpay_mbway' && $payment->getState()->getId() === 'completed') {
        return $payment;
      }
    }
    return NULL;
  }

  private function loadMultibancoDataFromPayment(OrderInterface $order): ?array {
    $storage  = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $storage->loadMultipleByOrder($order);

    foreach ($payments as $payment) {
      if ($payment->getPaymentGatewayId() === 'ifthenpay_multibanco') {
        $remoteId = $payment->getRemoteId();
        if ($remoteId && str_starts_with($remoteId, '{')) {
          $data = json_decode($remoteId, TRUE);
          if ($data) {
            $data['amount'] = number_format((float) $payment->getAmount()->getNumber(), 2, ',', '.');
            return $data;
          }
        }
      }
    }
    return NULL;
  }

  private function csrfToken(): \Drupal\Core\Access\CsrfTokenGenerator {
    return \Drupal::service('csrf_token');
  }

}

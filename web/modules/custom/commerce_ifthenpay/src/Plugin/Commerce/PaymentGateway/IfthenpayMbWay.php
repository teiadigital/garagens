<?php

namespace Drupal\commerce_ifthenpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\HasPaymentInstructionsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gateway de pagamento MB WAY via ifthenpay.
 *
 * O número de telemóvel é recolhido via add-payment-method form (MbWayPaymentMethodAddForm).
 * createPayment() chama a REST API do ifthenpay com o número de telemóvel.
 * onNotify() trata o callback e marca o pagamento como completo.
 *
 * @CommercePaymentGateway(
 *   id = "ifthenpay_mbway",
 *   label = "MB WAY (ifthenpay)",
 *   display_label = "MB WAY",
 *   forms = {
 *     "add-payment"        = "Drupal\commerce_ifthenpay\PluginForm\ManualPaymentAddForm",
 *     "receive-payment"    = "Drupal\commerce_ifthenpay\PluginForm\PaymentReceiveForm",
 *     "add-payment-method" = "Drupal\commerce_ifthenpay\PluginForm\MbWayPaymentMethodAddForm",
 *   },
 *   payment_method_types = {"ifthenpay_mbway"},
 *   payment_type = "payment_manual",
 *   requires_billing_information = FALSE,
 * )
 */
class IfthenpayMbWay extends PaymentGatewayBase implements OnsitePaymentGatewayInterface, HasPaymentInstructionsInterface, SupportsNotificationsInterface {

  protected $logger;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('commerce_ifthenpay');
    return $instance;
  }

  public function defaultConfiguration() {
    return [
      'mbway_key'         => '',
      'anti_phishing_key' => '',
      'minutes_to_expire' => 4,
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['mbway_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('MBWAY Key'),
      '#description'   => $this->t('Chave MBWAY Key atribuída pelo ifthenpay (ex: ZZZ-000000).'),
      '#default_value' => $this->configuration['mbway_key'] ?? '',
      '#required'      => TRUE,
      '#maxlength'     => 20,
      '#attributes'    => ['autocomplete' => 'off'],
    ];

    $form['anti_phishing_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Chave Anti-Phishing'),
      '#description'   => $this->t('<strong>Recomendada.</strong> Valida os callbacks do ifthenpay. Máx. 50 caracteres.'),
      '#default_value' => $this->configuration['anti_phishing_key'] ?? '',
      '#required'      => FALSE,
      '#maxlength'     => 50,
      '#attributes'    => ['autocomplete' => 'off'],
    ];

    $form['minutes_to_expire'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Minutos até expiração'),
      '#description'   => $this->t('O MB WAY expira ao fim de 4 minutos por defeito.'),
      '#default_value' => $this->configuration['minutes_to_expire'] ?? 4,
      '#required'      => TRUE,
      '#min'           => 1,
      '#max'           => 60,
    ];

    $gateway_entity = \Drupal::routeMatch()->getParameter('commerce_payment_gateway');
    if ($gateway_entity && $gateway_entity->id()) {
      $notify_url   = \Drupal\Core\Url::fromRoute('commerce_payment.notify', [
        'commerce_payment_gateway' => $gateway_entity->id(),
      ])->setAbsolute()->toString();
      $callback_url = $notify_url
        . '?key=[ANTI_PHISHING_KEY]&orderId=[ORDER_ID]&amount=[AMOUNT]'
        . '&requestId=[REQUEST_ID]&payment_datetime=[PAYMENT_DATETIME]';

      $form['callback_info'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('URL do Callback — configurar no backoffice ifthenpay'),
        'url'    => [
          '#markup' => '<code style="word-break:break-all;display:block;background:#f5f5f5;padding:8px;font-size:0.82em">'
            . htmlspecialchars($callback_url, ENT_QUOTES, 'UTF-8') . '</code>',
        ],
      ];
    }

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['mbway_key']         = trim($values['mbway_key']);
      $this->configuration['anti_phishing_key'] = trim($values['anti_phishing_key'] ?? '');
      $this->configuration['minutes_to_expire'] = (int) $values['minutes_to_expire'];
    }
  }

  /**
   * {@inheritdoc}
   *
   * Mostra a página de espera MB WAY com countdown e polling automático.
   * A order já está placed neste ponto — o checkout foi completado.
   */
  public function buildPaymentInstructions(PaymentInterface $payment) {
    // Recarregar o pagamento fresco da BD — o objeto passado pode estar em cache.
    $storage         = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $freshPayment    = $storage->load($payment->id());
    $payment         = $freshPayment ?: $payment;

    $existing = $payment->getRemoteId();
    $data     = ($existing && str_starts_with($existing, '{'))
      ? (json_decode($existing, TRUE) ?? [])
      : [];

    $order   = $payment->getOrder();
    $orderId = $order->id();
    $config  = $this->getConfiguration();

    $state = $payment->getState()->getId();

    // Pagamento já confirmado.
    if ($state === 'completed') {
      return [
        '#theme'    => 'commerce_ifthenpay_mbway_complete',
        '#phone'    => $data['phone'] ?? '',
        '#amount'   => $data['amount'] ?? '',
        '#attached' => ['library' => ['commerce_ifthenpay/ifthenpay']],
      ];
    }

    // Pagamento recusado, rejeitado ou expirado.
    if (in_array($state, ['voided'], TRUE)) {
      $failCode     = $data['failCode'] ?? '';
      $failMessages = [
        '020' => $this->t('O pagamento foi rejeitado na aplicação MB WAY.'),
        '101' => $this->t('O pedido de pagamento expirou.'),
        '122' => $this->t('O pagamento foi recusado.'),
      ];
      $failMsg = $failMessages[$failCode] ?? $this->t('O pagamento não foi confirmado.');
      return [
        '#theme'    => 'commerce_ifthenpay_mbway_failed',
        '#message'  => $failMsg,
        '#attached' => ['library' => ['commerce_ifthenpay/ifthenpay']],
      ];
    }

    // Gerar CSRF token para o endpoint de polling.
    $token     = \Drupal::service('csrf_token')->get('mbway_status_' . $orderId);
    $statusUrl = \Drupal\Core\Url::fromRoute('commerce_ifthenpay.mbway_status', [
      'commerce_order' => $orderId,
    ])->setAbsolute()->toString();

    $phone           = $data['phone'] ?? '';
    $amount          = $data['amount'] ?? '';
    $minutesToExpire = (int) ($config['minutes_to_expire'] ?? 4);
    $expiresAt       = time() + ($minutesToExpire * 60);

    return [
      '#theme'      => 'commerce_ifthenpay_mbway_pending',
      '#phone'      => $phone,
      '#amount'     => $amount,
      '#expires_at' => $expiresAt,
      '#attached'   => [
        'library'        => ['commerce_ifthenpay/mbway_status', 'commerce_ifthenpay/ifthenpay'],
        'drupalSettings' => [
          'commerceIfthenpayMbway' => [
            'statusUrl' => $statusUrl,
            'token'     => $token,
            'expiresAt' => $expiresAt,
            'phone'     => $phone,
            'amount'    => $amount,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Chama a API ifthenpay para iniciar o pagamento MB WAY.
   * O número de telemóvel vem do PaymentMethod (campo mbway_number).
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);

    $order         = $payment->getOrder();
    $config        = $this->getConfiguration();
    $amount        = number_format((float) $payment->getAmount()->getNumber(), 2, '.', '');
    $paymentMethod = $payment->getPaymentMethod();

    if (!$paymentMethod || !$paymentMethod->hasField('mbway_number')) {
      throw new HardDeclineException('Número de telemóvel MB WAY não encontrado.');
    }

    $phone = (string) ($paymentMethod->mbway_number->value ?? '');
    if (empty($phone)) {
      throw new HardDeclineException('Número de telemóvel MB WAY está vazio.');
    }

    try {
      /** @var \Drupal\commerce_ifthenpay\Service\IfthenpayApiService $apiService */
      $apiService     = \Drupal::service('commerce_ifthenpay.api_service');
      $formattedPhone = $apiService->formatMbWayPhone($phone);

      $result = $apiService->createMbWayPayment(
        mbWayKey: $config['mbway_key'],
        orderId: (string) $order->id(),
        amount: $amount,
        mobileNumber: $formattedPhone,
        description: (string) t('Pedido #@id', ['@id' => $order->id()]),
        email: $order->getEmail() ?? '',
      );

      $payment->setRemoteId(json_encode([
        'requestId' => $result['requestId'],
        'phone'     => substr($phone, -9),
        'amount'    => number_format((float) $amount, 2, ',', '.'),
      ]));
      $payment->setState('pending');
      $payment->save();

      $this->logger->info('MB WAY iniciado. orderId:@o requestId:@r', [
        '@o' => $order->id(),
        '@r' => $result['requestId'],
      ]);
    }
    catch (HardDeclineException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      $this->logger->error('MB WAY createPayment erro: @err', ['@err' => $e->getMessage()]);
      throw new HardDeclineException('Não foi possível iniciar o pagamento MB WAY. Por favor tente novamente.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, ?array $payment_details) {
    if (empty($payment_details['mbway_number'])) {
      throw new \InvalidArgumentException('O número de telemóvel MB WAY é obrigatório.');
    }
    $payment_method->setReusable(FALSE);
    $payment_method->mbway_number = $payment_details['mbway_number'];
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $state      = $payment->getState()->value;
    $operations = [];
    $operations['receive'] = [
      'title'       => $this->t('Receber'),
      'page_title'  => $this->t('Receber pagamento'),
      'plugin_form' => 'receive-payment',
      'access'      => $state == 'pending',
    ];
    $operations['void'] = [
      'title'       => $this->t('Anular'),
      'page_title'  => $this->t('Anular pagamento'),
      'plugin_form' => 'void-payment',
      'access'      => $state == 'pending',
    ];
    return $operations;
  }

  public function receivePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['pending']);
    $amount = $amount ?: $payment->getAmount();
    $payment->state = 'completed';
    $payment->setAmount($amount);
    $payment->save();
  }

  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['pending']);
    $payment->state = 'voided';
    $payment->save();
  }

  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $amount         = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);
    $old_refunded   = $payment->getRefundedAmount();
    $new_refunded   = $old_refunded->add($amount);
    $payment->state = $new_refunded->lessThan($payment->getAmount()) ? 'partially_refunded' : 'refunded';
    $payment->setRefundedAmount($new_refunded);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   *
   * Callback do ifthenpay após pagamento MB WAY confirmado.
   * Parâmetros GET: key, orderId, amount, requestId, payment_datetime
   */
  public function onNotify(Request $request) {
    $apk       = (string) ($request->query->get('key') ?? '');
    $orderId   = (string) ($request->query->get('orderId') ?? '');
    $amount    = (string) ($request->query->get('amount') ?? '');
    $requestId = (string) ($request->query->get('requestId') ?? '');

    if (empty($orderId) || empty($amount)) {
      return new Response('INVALID_PARAMS', 200);
    }

    $configuredApk = $this->configuration['anti_phishing_key'] ?? '';
    if (!empty($configuredApk) && !hash_equals($configuredApk, $apk)) {
      $this->logger->error('MB WAY callback APK inválida. orderId:@o', ['@o' => $orderId]);
      return new Response('FORBIDDEN', 200);
    }

    if (!ctype_digit($orderId)) {
      return new Response('INVALID_ORDER_ID', 200);
    }

    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load((int) $orderId);
    if (!$order) {
      return new Response('ORDER_NOT_FOUND', 200);
    }

    // Procurar pagamento pendente do pedido.
    // Não filtramos por gateway ID porque onNotify() já é chamado
    // no gateway correto pela rota /payment/notify/{gateway}.
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $storage */
    $storage  = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $payments = $storage->loadMultipleByOrder($order);
    $payment  = NULL;

    foreach ($payments as $p) {
      if ($p->getState()->getId() === 'completed') {
        // Já foi pago — idempotência.
        return new Response('OK', 200);
      }
      if ($p->getState()->getId() === 'pending') {
        $payment = $p;
      }
    }

    if (!$payment) {
      return new Response('PAYMENT_NOT_FOUND', 200);
    }

    $expected = number_format((float) $payment->getAmount()->getNumber(), 2, '.', '');
    if (abs((float) str_replace(',', '.', $amount) - (float) $expected) > 0.01) {
      return new Response('AMOUNT_MISMATCH', 200);
    }

    // Verificar requestId contra o valor guardado (segurança extra).
    $existing = $payment->getRemoteId();
    if (!empty($requestId) && $existing && str_starts_with($existing, '{')) {
      $stored = json_decode($existing, TRUE) ?? [];
      if (!empty($stored['requestId']) && $stored['requestId'] !== $requestId) {
        $this->logger->error('MB WAY callback requestId inválido. orderId:@o stored:@s received:@r', [
          '@o' => $orderId,
          '@s' => $stored['requestId'],
          '@r' => $requestId,
        ]);
        return new Response('INVALID_REQUEST_ID', 200);
      }
    }

    $payment->setState('completed');
    $payment->save();

    if ($order->getBalance()->isZero() || $order->getBalance()->isNegative()) {
      $order->save();
    }

    $this->logger->info('MB WAY PAGO. orderId:@o requestId:@r', ['@o' => $orderId, '@r' => $requestId]);
    return new Response('OK', 200);
  }

}

<?php

namespace Drupal\commerce_ifthenpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gateway de pagamento Multibanco via ifthenpay.
 *
 * Usa ManualPaymentGatewayInterface: o pagamento fica pendente,
 * buildPaymentInstructions() gera e mostra a referência via REST API,
 * e onNotify() confirma o pagamento via callback do ifthenpay.
 *
 * @CommercePaymentGateway(
 *   id = "ifthenpay_multibanco",
 *   label = "Multibanco (ifthenpay)",
 *   display_label = "Multibanco",
 *   forms = {
 *     "add-payment"     = "Drupal\commerce_ifthenpay\PluginForm\ManualPaymentAddForm",
 *     "receive-payment" = "Drupal\commerce_ifthenpay\PluginForm\PaymentReceiveForm",
 *   },
 *   payment_type = "payment_manual",
 *   requires_billing_information = FALSE,
 * )
 */
class IfthenpayMultibanco extends PaymentGatewayBase implements ManualPaymentGatewayInterface, SupportsNotificationsInterface {

  protected $logger;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('commerce_ifthenpay');
    return $instance;
  }

  public function defaultConfiguration() {
    return [
      'mb_key'            => '',
      'anti_phishing_key' => '',
      'days_to_expire'    => 3,
    ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['mb_key'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('MB Key'),
      '#description'   => $this->t('Chave MB Key atribuída pelo ifthenpay (ex: ZZZ-000000).'),
      '#default_value' => $this->configuration['mb_key'] ?? '',
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

    $form['days_to_expire'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Dias até expiração'),
      '#description'   => $this->t('0 = expira hoje às 23:59. Deixe vazio para sem expiração.'),
      '#default_value' => $this->configuration['days_to_expire'] ?? 3,
      '#required'      => FALSE,
      '#min'           => 0,
      '#max'           => 365,
    ];

    // Mostrar URL do callback após guardar.
    $gateway_entity = \Drupal::routeMatch()->getParameter('commerce_payment_gateway');
    if ($gateway_entity && $gateway_entity->id()) {
      $notify_url  = \Drupal\Core\Url::fromRoute('commerce_payment.notify', [
        'commerce_payment_gateway' => $gateway_entity->id(),
      ])->setAbsolute()->toString();
      $callback_url = $notify_url
        . '?key=[ANTI_PHISHING_KEY]&orderId=[ORDER_ID]&amount=[AMOUNT]'
        . '&requestId=[REQUEST_ID]&entity=[ENTITY]&reference=[REFERENCE]&payment_datetime=[PAYMENT_DATETIME]';

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
      $this->configuration['mb_key']            = trim($values['mb_key']);
      $this->configuration['anti_phishing_key'] = trim($values['anti_phishing_key'] ?? '');
      $this->configuration['days_to_expire']    = ($values['days_to_expire'] !== '') ? (int) $values['days_to_expire'] : NULL;
    }
  }

  /**
   * Mostra a referência Multibanco na página "Concluída" do checkout.
   *
   * Chamado pelo Commerce para mostrar instruções de pagamento.
   * Chama a API do ifthenpay na primeira vez e guarda no payment->remoteId.
   */
  public function buildPaymentInstructions(PaymentInterface $payment) {
    // Recarregar o pagamento fresco da BD.
    $storage      = \Drupal::entityTypeManager()->getStorage('commerce_payment');
    $freshPayment = $storage->load($payment->id());
    $payment      = $freshPayment ?: $payment;

    $state = $payment->getState()->getId();

    // Pagamento anulado/expirado — não mostrar referência.
    if (in_array($state, ['voided', 'authorization_voided'], TRUE)) {
      return [];
    }

    $existing = $payment->getRemoteId();
    $data     = [];

    if ($existing && str_starts_with($existing, '{')) {
      // Referência já gerada — apenas mostrar.
      $data = json_decode($existing, TRUE) ?? [];
    }
    else {
      // Gerar referência via API do ifthenpay (primeira vez).
      $data = $this->generateAndSaveReference($payment);

      // Enviar email com a referência após gerar pela primeira vez.
      if (!empty($data)) {
        try {
          commerce_ifthenpay_send_multibanco_email($payment->getOrder(), $data);
        }
        catch (\Throwable $e) {
          $this->logger->warning('Erro ao enviar email Multibanco: @err', ['@err' => $e->getMessage()]);
        }
      }
    }

    if (empty($data)) {
      return [
        '#markup' => '<p>' . $this->t('Erro ao gerar referência Multibanco. Por favor contacte o suporte.') . '</p>',
      ];
    }

    return $this->buildReferenceDisplay($data, $payment);
  }

  /**
   * Chama a API e guarda a referência no payment.
   */
  private function generateAndSaveReference(PaymentInterface $payment): array {
    $config       = $this->getConfiguration();
    $order        = $payment->getOrder();
    $amount       = number_format((float) $payment->getAmount()->getNumber(), 2, '.', '');
    $sandbox      = $this->getMode() === 'test';
    $daysToExpire = (isset($config['days_to_expire']) && $config['days_to_expire'] !== '')
      ? (int) $config['days_to_expire'] : NULL;

    $clientData = ['url' => \Drupal::request()->getSchemeAndHttpHost()];
    if ($order->getEmail()) {
      $clientData['clientEmail'] = $order->getEmail();
    }

    try {
      /** @var \Drupal\commerce_ifthenpay\Service\IfthenpayApiService $apiService */
      $apiService = \Drupal::service('commerce_ifthenpay.api_service');

      $result = $apiService->createMultibancoReference(
        mbKey: $config['mb_key'],
        orderId: (string) $order->id(),
        amount: $amount,
        description: (string) t('Pedido #@id', ['@id' => $order->id()]),
        daysToExpire: $daysToExpire,
        sandbox: $sandbox,
        clientData: $clientData,
      );

      $data = [
        'requestId'  => $result['requestId'],
        'entity'     => $result['entity'],
        'reference'  => $result['reference'],
        'expiryDate' => $result['expiryDate'],
        'sandbox'    => $sandbox,
      ];

      $payment->setRemoteId(json_encode($data));
      $payment->save();

      $this->logger->info('Multibanco referência gerada. orderId:@o entity:@e reference:@r', [
        '@o' => $order->id(),
        '@e' => $result['entity'],
        '@r' => $result['reference'],
      ]);

      return $data;
    }
    catch (\Throwable $e) {
      $this->logger->error('Multibanco erro ao gerar referência: @err', ['@err' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Constrói o render array com os dados de pagamento Multibanco via tema.
   */
  private function buildReferenceDisplay(array $data, PaymentInterface $payment): array {
    $ref = preg_replace('/\D/', '', $data['reference'] ?? '');
    $ref = (strlen($ref) === 9)
      ? substr($ref, 0, 3) . ' ' . substr($ref, 3, 3) . ' ' . substr($ref, 6, 3)
      : ($data['reference'] ?? '');

    return [
      '#theme'      => 'commerce_ifthenpay_multibanco',
      '#entity'     => $data['entity'] ?? '',
      '#reference'  => $ref,
      '#amount'     => number_format((float) $payment->getAmount()->getNumber(), 2, ',', '.'),
      '#expiry_date'=> $data['expiryDate'] ?? '',
      '#sandbox'    => !empty($data['sandbox']),
      '#attached'   => ['library' => ['commerce_ifthenpay/ifthenpay']],
    ];
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

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $received = FALSE) {
    $this->assertPaymentState($payment, ['new']);
    $payment->state = $received ? 'completed' : 'pending';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function receivePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['pending']);
    $amount = $amount ?: $payment->getAmount();
    $payment->state = 'completed';
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['pending']);
    $payment->state = 'voided';
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    $amount             = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);
    $old_refunded       = $payment->getRefundedAmount();
    $new_refunded       = $old_refunded->add($amount);
    $payment->state     = $new_refunded->lessThan($payment->getAmount()) ? 'partially_refunded' : 'refunded';
    $payment->setRefundedAmount($new_refunded);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   *
   * Callback do ifthenpay após pagamento Multibanco confirmado.
   * Parâmetros GET: key, orderId, amount, requestId, entity, reference, payment_datetime
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
      $this->logger->error('Multibanco callback APK inválida. orderId:@o', ['@o' => $orderId]);
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
      $this->logger->error('Multibanco callback valor inválido. recv:@r exp:@e orderId:@o', [
        '@r' => $amount, '@e' => $expected, '@o' => $orderId,
      ]);
      return new Response('AMOUNT_MISMATCH', 200);
    }

    // Verificar requestId contra o valor guardado (segurança extra).
    $existing = $payment->getRemoteId();
    if (!empty($requestId) && $existing && str_starts_with($existing, '{')) {
      $stored = json_decode($existing, TRUE) ?? [];
      if (!empty($stored['requestId']) && $stored['requestId'] !== $requestId) {
        $this->logger->error('Multibanco callback requestId inválido. orderId:@o stored:@s received:@r', [
          '@o' => $orderId,
          '@s' => $stored['requestId'],
          '@r' => $requestId,
        ]);
        return new Response('INVALID_REQUEST_ID', 200);
      }
    }

    $payment->setState('completed');
    $payment->save();

    // Guardar o pedido para disparar o evento de pagamento.
    if ($order->getBalance()->isZero() || $order->getBalance()->isNegative()) {
      $order->save();
    }

    $this->logger->info('Multibanco PAGO. orderId:@o requestId:@r', ['@o' => $orderId, '@r' => $requestId]);
    return new Response('OK', 200);
  }

}

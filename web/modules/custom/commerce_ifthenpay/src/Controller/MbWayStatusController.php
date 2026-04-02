<?php

namespace Drupal\commerce_ifthenpay\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller para verificar o estado do pagamento MB WAY.
 *
 * Chamado via AJAX pelo polling JS na página "Concluída".
 * Chama a API do ifthenpay e devolve o estado do pagamento.
 */
class MbWayStatusController extends ControllerBase {

  /**
   * Verifica o estado do pagamento MB WAY via API do ifthenpay.
   *
   * Retorna JSON:
   *   { "status": "pending" }   — aguarda aprovação
   *   { "status": "paid" }      — pago (000)
   *   { "status": "failed", "code": "101" } — expirado/recusado
   *   { "status": "error" }     — erro de comunicação
   */
  public function status(OrderInterface $commerce_order, Request $request): JsonResponse {
    $order = $commerce_order;

    // Validar CSRF token.
    $token = (string) ($request->query->get('token') ?? '');
    if (!\Drupal::service('csrf_token')->validate($token, 'mbway_status_' . $order->id())) {
      return new JsonResponse(['status' => 'error', 'message' => 'Token inválido.'], 403);
    }

    // Carregar o pagamento MB WAY pendente do pedido.
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $storage */
    $storage  = $this->entityTypeManager()->getStorage('commerce_payment');
    $payments = $storage->loadMultipleByOrder($order);

    $payment = NULL;
    foreach ($payments as $p) {
      // Se já foi completado pelo callback do ifthenpay.
      if ($p->getState()->getId() === 'completed') {
        return new JsonResponse(['status' => 'paid']);
      }
      if ($p->getState()->getId() === 'pending') {
        $payment = $p;
      }
    }

    if (!$payment) {
      return new JsonResponse(['status' => 'error', 'message' => 'Pagamento não encontrado.']);
    }

    // Obter requestId e mbwayKey dos dados guardados.
    $remoteId = $payment->getRemoteId();
    if (!$remoteId || !str_starts_with($remoteId, '{')) {
      return new JsonResponse(['status' => 'error', 'message' => 'Dados do pagamento inválidos.']);
    }

    $data      = json_decode($remoteId, TRUE) ?? [];
    $requestId = $data['requestId'] ?? '';

    if (empty($requestId)) {
      return new JsonResponse(['status' => 'error', 'message' => 'RequestId não encontrado.']);
    }

    // Obter a configuração do gateway MB WAY.
    $gateway = $payment->getPaymentGateway();
    if (!$gateway) {
      return new JsonResponse(['status' => 'error', 'message' => 'Gateway não encontrado.']);
    }
    $config   = $gateway->getPlugin()->getConfiguration();
    $mbwayKey = $config['mbway_key'] ?? '';

    if (empty($mbwayKey)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Chave MB WAY não configurada.']);
    }

    // Chamar a API do ifthenpay para verificar o estado.
    try {
      /** @var \Drupal\commerce_ifthenpay\Service\IfthenpayApiService $apiService */
      $apiService  = \Drupal::service('commerce_ifthenpay.api_service');
      $apiStatus   = $apiService->getMbWayPaymentStatus($mbwayKey, $requestId);

      switch ($apiStatus) {
        case '000':
          // Pago — atualizar o pagamento.
          $payment->setState('completed');
          $payment->save();
          if ($order->getBalance()->isZero() || $order->getBalance()->isNegative()) {
            $order->save();
          }
          \Drupal::logger('commerce_ifthenpay')->info(
            'MB WAY pago via polling. orderId:@o requestId:@r',
            ['@o' => $order->id(), '@r' => $requestId]
          );
          return new JsonResponse(['status' => 'paid']);

        case '020':
        case '101':
        case '122':
          // Guardar o código de falha no remoteId para mostrar na página ao recarregar.
          $storedData = json_decode($payment->getRemoteId() ?? '{}', TRUE) ?? [];
          $storedData['failCode'] = $apiStatus;
          $payment->setRemoteId(json_encode($storedData));
          $payment->setState('voided');
          $payment->save();
          return new JsonResponse(['status' => 'failed', 'code' => $apiStatus]);

        default:
          // Pendente (ou estado desconhecido) — continuar polling.
          return new JsonResponse(['status' => 'pending']);
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('commerce_ifthenpay')->warning(
        'MB WAY status check erro: @err',
        ['@err' => $e->getMessage()]
      );
      // Em caso de erro de comunicação, devolver pending para continuar polling.
      return new JsonResponse(['status' => 'pending']);
    }
  }

}

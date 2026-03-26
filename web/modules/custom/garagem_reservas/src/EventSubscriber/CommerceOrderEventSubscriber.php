<?php

namespace Drupal\garagem_reservas\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Database\Connection;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber para detetar pagamentos concluídos no Commerce.
 */
class CommerceOrderEventSubscriber implements EventSubscriberInterface {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // User submeteu o checkout — mudar para aguarda_pagamento.
      'commerce_order.place.post_transition' => ['onOrderPlace', 0],
      // Pagamento confirmado pelo admin ou Ifthenpay callback.
      OrderEvents::ORDER_PAID => ['onOrderPaid', 0],
    ];
  }

  /**
   * Quando o user faz o checkout — muda reserva para aguarda_pagamento.
   */
  public function onOrderPlace(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();

    $reserva = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr', ['id', 'estado'])
      ->condition('commerce_order_id', $order->id())
      ->execute()
      ->fetchObject();

    if (!$reserva || !in_array($reserva->estado, ['aprovado'])) {
      return;
    }

    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'aguarda_pagamento'])
      ->condition('id', $reserva->id)
      ->execute();

    \Drupal::logger('garagem_reservas')->info(
      'Reserva #@id mudou para aguarda_pagamento.',
      ['@id' => $reserva->id]
    );
  }

  /**
   * Quando o pagamento é confirmado — muda reserva para pago.
   */
  public function onOrderPaid(OrderEvent $event): void {
    $order = $event->getOrder();
    $this->processOrderCompletion($order);
  }

  /**
   * Processa a conclusão de uma order — atualiza a reserva para 'pago'.
   */
  protected function processOrderCompletion($order): void {
    // Verificar se esta order está associada a uma reserva.
    $reserva = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr', ['id', 'estado'])
      ->condition('commerce_order_id', $order->id())
      ->execute()
      ->fetchObject();

    if (!$reserva || $reserva->estado === 'pago') {
      return;
    }

    // Atualizar estado da reserva para pago.
    $this->database->update('garagem_reserva')
      ->fields([
        'estado' => 'pago',
        'data_pagamento' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $reserva->id)
      ->execute();

    \Drupal::logger('garagem_reservas')->info(
      'Reserva #@id marcada como paga via Commerce Order #@order.',
      ['@id' => $reserva->id, '@order' => $order->id()]
    );

    // Enviar notificações — num try/catch para não bloquear o pagamento.
    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaPaga($reserva->id);
    }
    catch (\Exception $e) {
      \Drupal::logger('garagem_reservas')->warning(
        'Erro ao enviar notificação para reserva #@id: @msg',
        ['@id' => $reserva->id, '@msg' => $e->getMessage()]
      );
    }
  }

}

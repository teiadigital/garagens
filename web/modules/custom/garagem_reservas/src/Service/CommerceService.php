<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Serviço de integração com Drupal Commerce.
 */
class CommerceService {

  protected $database;
  protected $entityTypeManager;

  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Cria uma order Commerce quando uma reserva é aprovada.
   *
   * @param int $reserva_id
   *   ID da reserva.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   A order criada ou NULL em caso de erro.
   */
  public function criarOrderReserva(int $reserva_id): ?object {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) {
      return NULL;
    }

    // Carregar garagem e user.
    $garagem = $this->entityTypeManager->getStorage('node')->load($reserva->garagem_id);
    $user = $this->entityTypeManager->getStorage('user')->load($reserva->user_id);

    if (!$garagem || !$user) {
      return NULL;
    }

    // Carregar a variação do produto taxa de reserva (Variation ID: 1).
    $variation = $this->entityTypeManager
      ->getStorage('commerce_product_variation')
      ->load(1);

    if (!$variation) {
      \Drupal::logger('garagem_reservas')->error('Variação do produto taxa de reserva não encontrada.');
      return NULL;
    }

    // Formatar datas.
    $data_inicio = date('d/m/Y', $reserva->data_inicio);
    $data_fim = $reserva->renovacao_automatica ? $this->t('Renovação automática') : ($reserva->data_fim ? date('d/m/Y', $reserva->data_fim) : '-');

    // Criar Order Item com preço dinâmico e campos da reserva.
    $taxa = (string) number_format($reserva->taxa_plataforma, 2, '.', '');
    $order_item = OrderItem::create([
      'type' => 'taxa_reserva_garagem',
      'purchased_entity' => $variation,
      'quantity' => 1,
      'field_reserva_id' => $reserva_id,
      'field_garagem_nome' => $garagem->getTitle(),
      'field_data_inicio' => $data_inicio,
      'field_data_fim' => $data_fim,
    ]);
    $order_item->setUnitPrice(new Price($taxa, 'EUR'), TRUE);
    $order_item->save();

    // Carregar a loja (store ID: 1).
    $store = $this->entityTypeManager->getStorage('commerce_store')->load(1);

    // Criar a Order.
    $order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $store->id(),
      'order_items' => [$order_item],
      'state' => 'draft',
    ]);
    $order->save();

    // Associar a order à reserva.
    $this->database->update('garagem_reserva')
      ->fields(['commerce_order_id' => $order->id()])
      ->condition('id', $reserva_id)
      ->execute();

    return $order;
  }

  /**
   * Obtém dados da reserva.
   */
  protected function getReserva(int $reserva_id): ?object {
    return $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

}

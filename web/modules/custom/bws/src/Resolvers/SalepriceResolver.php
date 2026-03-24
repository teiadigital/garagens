<?php

namespace Drupal\bws\Resolvers;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;

/**
 * Resolve price for bws nodes.
 *
 * @package Drupal\bws\Resolvers
 */
class SalepriceResolver implements PriceResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    if ($entity->bundle() != 'bws') {
      return;
    }

    $store = $context->getStore();

    if ($cart = \Drupal::service('commerce_cart.cart_provider')->getCart('default', $store)) {
      $order_items = $cart->getItems();
      foreach ($order_items as $order_item) {
        if ($order_item->bundle() == 'bws') {
          return $order_item->getUnitPrice();
        }
      }
    }
  }

}

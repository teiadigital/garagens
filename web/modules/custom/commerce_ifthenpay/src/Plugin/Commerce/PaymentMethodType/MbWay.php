<?php

namespace Drupal\commerce_ifthenpay\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * Tipo de método de pagamento MB WAY.
 *
 * @CommercePaymentMethodType(
 *   id = "ifthenpay_mbway",
 *   label = @Translation("MB WAY"),
 *   create_label = @Translation("MB WAY"),
 * )
 */
class MbWay extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $phone = NULL;
    if ($payment_method->hasField('mbway_number')) {
      $phone = $payment_method->mbway_number->value;
    }
    return $phone
      ? (string) $this->t('MB WAY (@phone)', ['@phone' => $phone])
      : (string) $this->t('MB WAY');
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['mbway_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Número de telemóvel MB WAY'))
      ->setDescription(t('Número de telemóvel para o pagamento MB WAY.'))
      ->setRequired(TRUE);

    return $fields;
  }

}

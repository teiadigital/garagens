<?php

namespace Drupal\commerce_ifthenpay\PluginForm;

use Drupal\commerce_price\Price;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;

class PaymentReceiveForm extends PaymentGatewayFormBase {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    $form['#success_message'] = t('Pagamento recebido.');
    $form['amount'] = [
      '#type'          => 'commerce_price',
      '#title'         => t('Valor'),
      '#default_value' => $payment->getAmount()->toArray(),
      '#required'      => TRUE,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values  = $form_state->getValue($form['#parents']);
    $amount  = Price::fromArray($values['amount']);
    $payment = $this->entity;
    $this->plugin->receivePayment($payment, $amount);
  }

}

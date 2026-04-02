<?php

namespace Drupal\commerce_ifthenpay\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;

class ManualPaymentAddForm extends PaymentGatewayFormBase {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order   = $payment->getOrder();

    if (!$order) {
      throw new \InvalidArgumentException('Payment entity with no order reference given to ManualPaymentAddForm.');
    }

    $form['amount'] = [
      '#type'          => 'commerce_price',
      '#title'         => t('Valor'),
      '#default_value' => $order->getTotalPrice()->toArray(),
      '#required'      => TRUE,
    ];
    $form['received'] = [
      '#type'  => 'checkbox',
      '#title' => t('O pagamento já foi recebido.'),
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values  = $form_state->getValue($form['#parents']);
    $payment = $this->entity;
    $payment->amount = $values['amount'];
    $this->plugin->createPayment($payment, $values['received']);
  }

}

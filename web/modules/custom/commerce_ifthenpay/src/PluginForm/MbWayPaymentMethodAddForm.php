<?php

namespace Drupal\commerce_ifthenpay\PluginForm;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentMethodFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário de introdução do número de telemóvel MB WAY.
 *
 * Mostrado no passo "Informação de pagamento" do checkout.
 */
class MbWayPaymentMethodAddForm extends PaymentMethodFormBase {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type'    => 'container',
    ];

    $form['payment_details']['mbway_number'] = [
      '#type'        => 'tel',
      '#title'       => t('Número de telemóvel'),
      '#description' => t('Introduza o número de telemóvel para receber a notificação MB WAY (ex: 912345678).'),
      '#required'    => TRUE,
      '#maxlength'   => 15,
      '#placeholder' => '9XXXXXXXX',
      '#attributes'  => ['autocomplete' => 'tel'],
    ];

    if (isset($form['billing_information'])) {
      $form['billing_information']['#weight'] = 10;
    }

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;
    $values         = $form_state->getValue($form['#parents']);

    try {
      $this->plugin->createPaymentMethod($payment_method, $values['payment_details']);
    }
    catch (DeclineException $e) {
      \Drupal::logger('commerce_ifthenpay')->warning($e->getMessage());
      throw new DeclineException('Erro ao processar o método de pagamento. Por favor verifique os dados.');
    }
    catch (PaymentGatewayException $e) {
      \Drupal::logger('commerce_ifthenpay')->error($e->getMessage());
      throw new PaymentGatewayException('Erro inesperado ao processar o método de pagamento. Por favor tente novamente.');
    }
  }

}

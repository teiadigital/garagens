<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário de definições globais do módulo.
 */
class GaragemReservasSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['garagem_reservas.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'garagem_reservas_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('garagem_reservas.settings');

    $form['prazo_pagamento_horas'] = [
      '#type' => 'number',
      '#title' => $this->t('Prazo de pagamento após aprovação (horas)'),
      '#default_value' => $config->get('prazo_pagamento_horas') ?? 48,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Número de horas que o utilizador tem para pagar após a reserva ser aprovada.'),
    ];

    $form['taxa_fixa'] = [
      '#type' => 'number',
      '#title' => $this->t('Taxa fixa da plataforma (€)'),
      '#default_value' => $config->get('taxa_fixa') ?? 5.00,
      '#min' => 0,
      '#step' => 0.01,
      '#required' => TRUE,
      '#description' => $this->t('Taxa fixa cobrada pela plataforma por cada reserva.'),
    ];

    $form['percentagem_plataforma'] = [
      '#type' => 'number',
      '#title' => $this->t('Percentagem da plataforma (%)'),
      '#default_value' => $config->get('percentagem_plataforma') ?? 10,
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.1,
      '#required' => TRUE,
      '#description' => $this->t('Percentagem do valor total da reserva cobrada pela plataforma.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('garagem_reservas.settings')
      ->set('prazo_pagamento_horas', $form_state->getValue('prazo_pagamento_horas'))
      ->set('taxa_fixa', $form_state->getValue('taxa_fixa'))
      ->set('percentagem_plataforma', $form_state->getValue('percentagem_plataforma'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

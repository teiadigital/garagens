<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário de definições globais do módulo.
 */
class GaragemReservasSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['garagem_reservas.settings'];
  }

  public function getFormId() {
    return 'garagem_reservas_settings_form';
  }

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

    $form['contrato_template_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('Node ID do template do contrato'),
      '#default_value' => $config->get('contrato_template_nid') ?? 236,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('ID do node do tipo "Contrato Template" que define as cláusulas do PDF.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('garagem_reservas.settings');
    $config->set('prazo_pagamento_horas', $form_state->getValue('prazo_pagamento_horas'));
    $config->set('taxa_fixa', $form_state->getValue('taxa_fixa'));
    $config->set('percentagem_plataforma', $form_state->getValue('percentagem_plataforma'));
    $config->set('contrato_template_nid', $form_state->getValue('contrato_template_nid'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

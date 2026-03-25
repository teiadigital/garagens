<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Formulário de criação de reserva com Flatpickr.
 */
class ReservaForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'garagem_reservas_reserva_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node) {
      return [];
    }

    $form_state->set('garagem_node', $node);

    // Anexar biblioteca Flatpickr + JS custom.
    $form['#attached']['library'][] = 'garagem_reservas/flatpickr';
    $form['#attached']['drupalSettings']['garagemReservas'] = [
      'disponibilidadeUrl' => '/garagem/' . $node->id() . '/disponibilidade',
    ];

    // Data de início.
    $form['data_inicio'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data de início'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['garagem-flatpickr-inicio'],
        'placeholder' => $this->t('Selecione a data de início'),
        'readonly' => 'readonly',
      ],
    ];

    // Hidden fields para guardar os valores timestamp.
    $form['data_inicio_value'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'data-inicio-value'],
    ];

    // Checkbox indefinido.
    $form['indefinido'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reserva por tempo indefinido'),
      '#default_value' => FALSE,
      '#attributes' => ['id' => 'edit-indefinido'],
    ];

    // Container para data fim.
    $form['data_fim_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'data-fim-wrapper'],
      '#states' => [
        'invisible' => [
          'input[name="indefinido"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Data de fim.
    $form['data_fim_wrapper']['data_fim'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data de fim'),
      '#required' => FALSE,
      '#attributes' => [
        'class' => ['garagem-flatpickr-fim'],
        'placeholder' => $this->t('Selecione a data de fim'),
        'readonly' => 'readonly',
      ],
    ];

    $form['data_fim_wrapper']['data_fim_value'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'data-fim-value'],
    ];

    // Info do preço calculado.
    $form['preco_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'preco-wrapper'],
    ];

    $form['preco_wrapper']['preco_info'] = [
      '#type' => 'item',
      '#markup' => '<div id="preco-calculado" class="preco-info"></div>',
    ];

    // Notas.
    $form['notas'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notas adicionais'),
      '#required' => FALSE,
      '#rows' => 3,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pedir reserva'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $indefinido = $form_state->getValue('indefinido');
    $input = $form_state->getUserInput();

    $data_inicio_str = $input['data_inicio'] ?? NULL;
    $data_fim_str = $input['data_fim'] ?? NULL;

    if (empty($data_inicio_str)) {
      $form_state->setErrorByName('data_inicio', $this->t('A data de início é obrigatória.'));
      return;
    }

    if (!$indefinido && empty($data_fim_str)) {
      $form_state->setErrorByName('data_fim_wrapper][data_fim', $this->t('A data de fim é obrigatória quando a reserva não é por tempo indefinido.'));
      return;
    }

    // Converter formato d/m/Y H:00 para timestamp.
    $inicio_ts = $this->parseFlatpickrDate($data_inicio_str);
    $fim_ts = (!$indefinido && $data_fim_str) ? $this->parseFlatpickrDate($data_fim_str) : NULL;

    if (!$inicio_ts) {
      $form_state->setErrorByName('data_inicio', $this->t('Data de início inválida.'));
      return;
    }

    if ($fim_ts && $fim_ts <= $inicio_ts) {
      $form_state->setErrorByName('data_fim_wrapper][data_fim', $this->t('A data de fim deve ser posterior à data de início.'));
      return;
    }

    // Verificar disponibilidade.
    $node = $form_state->get('garagem_node');
    if ($node) {
      $servico = \Drupal::service('garagem_reservas.disponibilidade');
      if (!$servico->verificarDisponibilidade($node->id(), $inicio_ts, $fim_ts)) {
        $form_state->setErrorByName('data_inicio', $this->t('A garagem não está disponível para o período selecionado.'));
        return;
      }
    }

    // Guardar timestamps para o submit.
    $form_state->set('inicio_ts', $inicio_ts);
    $form_state->set('fim_ts', $fim_ts);
  }

  /**
   * Converte data no formato d/m/Y para timestamp.
   */
  protected function parseFlatpickrDate(string $date_str): ?int {
    $date = \DateTime::createFromFormat('d/m/Y', trim($date_str));
    return $date ? $date->getTimestamp() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('garagem_node');
    $indefinido = $form_state->getValue('indefinido');
    $notas = $form_state->getValue('notas');
    $inicio_ts = $form_state->get('inicio_ts');
    $fim_ts = $form_state->get('fim_ts');

    // Calcular preço.
    $servico_preco = \Drupal::service('garagem_reservas.preco');
    $calculo = $servico_preco->calcularPreco($node, $inicio_ts, $fim_ts, $indefinido);

    // Calcular taxa da plataforma.
    $config = \Drupal::config('garagem_reservas.settings');
    $taxa_fixa = $config->get('taxa_fixa');
    $percentagem = $config->get('percentagem_plataforma');
    $taxa_plataforma = $taxa_fixa + ($calculo['preco_total'] * $percentagem / 100);

    // Guardar reserva.
    $database = \Drupal::database();
    $id = $database->insert('garagem_reserva')
      ->fields([
        'garagem_id' => $node->id(),
        'user_id' => \Drupal::currentUser()->id(),
        'proprietario_id' => $node->getOwnerId(),
        'data_inicio' => $inicio_ts,
        'data_fim' => $fim_ts,
        'indefinido' => $indefinido ? 1 : 0,
        'tipo_preco' => $calculo['tipo_preco'],
        'preco_total' => $calculo['preco_total'],
        'taxa_plataforma' => $taxa_plataforma,
        'estado' => 'pendente',
        'data_criacao' => \Drupal::time()->getRequestTime(),
        'notas' => $notas,
      ])
      ->execute();

    \Drupal::service('garagem_reservas.notificacao')->reservaCriada($id);

    $this->messenger()->addStatus($this->t('O seu pedido de reserva foi submetido com sucesso. Aguarde a aprovação do proprietário.'));
    $form_state->setRedirect('garagem_reservas.reserva_view', ['reserva' => $id]);
  }

}

<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário de edição de reserva pelo admin.
 */
class ReservaAdminEditForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'garagem_reservas_admin_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $reserva = NULL) {
    $reserva_data = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva)
      ->execute()
      ->fetchObject();

    if (!$reserva_data) {
      $this->messenger()->addError($this->t('Reserva não encontrada.'));
      return $form;
    }

    $form_state->set('reserva_id', $reserva);

    $garagem = \Drupal::entityTypeManager()->getStorage('node')->load($reserva_data->garagem_id);
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($reserva_data->user_id);

    $form['info'] = [
      '#type' => 'item',
      '#markup' => '<p><strong>' . $this->t('Reserva #@id', ['@id' => $reserva_data->id]) . '</strong> — '
        . ($garagem ? $garagem->getTitle() : '-') . ' — '
        . ($user ? $user->getDisplayName() : '-') . '</p>',
    ];

    $form['estado'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => [
        'pendente' => $this->t('Pendente'),
        'aprovado' => $this->t('Aprovado'),
        'aguarda_pagamento' => $this->t('Aguarda pagamento'),
        'pago' => $this->t('Pago'),
        'rejeitado' => $this->t('Rejeitado'),
        'cancelado' => $this->t('Cancelado'),
        'expirado' => $this->t('Expirado'),
      ],
      '#default_value' => $reserva_data->estado,
      '#required' => TRUE,
    ];

    $form['notas_admin'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notas do admin'),
      '#default_value' => $reserva_data->notas ?? '',
      '#rows' => 3,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Guardar'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => \Drupal\Core\Url::fromRoute('garagem_reservas.admin_lista'),
      '#attributes' => ['class' => ['btn', 'btn-secondary', 'ms-2']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $reserva_id = $form_state->get('reserva_id');
    $estado = $form_state->getValue('estado');
    $notas = $form_state->getValue('notas_admin');

    $fields = [
      'estado' => $estado,
      'notas' => $notas,
    ];

    // Se passou para aprovado, registar data de aprovação.
    if ($estado === 'aprovado') {
      $fields['data_aprovacao'] = \Drupal::time()->getRequestTime();
    }

    // Se passou para pago, registar data de pagamento.
    if ($estado === 'pago') {
      $fields['data_pagamento'] = \Drupal::time()->getRequestTime();
    }

    $this->database->update('garagem_reserva')
      ->fields($fields)
      ->condition('id', $reserva_id)
      ->execute();

    $this->messenger()->addStatus($this->t('Reserva atualizada com sucesso.'));
    $form_state->setRedirect('garagem_reservas.admin_lista');
  }

}

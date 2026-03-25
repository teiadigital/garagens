<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário de confirmação de apagar reserva.
 */
class ReservaAdminDeleteForm extends ConfirmFormBase {

  protected $database;
  protected $reservaId;

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
    return 'garagem_reservas_admin_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Tem a certeza que quer apagar a reserva #@id?', ['@id' => $this->reservaId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('garagem_reservas.admin_lista');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Apagar');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $reserva = NULL) {
    $this->reservaId = $reserva;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->delete('garagem_reserva')
      ->condition('id', $this->reservaId)
      ->execute();

    $this->messenger()->addStatus($this->t('Reserva #@id apagada.', ['@id' => $this->reservaId]));
    $form_state->setRedirect('garagem_reservas.admin_lista');
  }

}

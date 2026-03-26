<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário de confirmação de cancelamento de reserva pelo utilizador.
 */
class ReservaCancelarForm extends ConfirmFormBase {

  protected $database;
  protected $reservaId;
  protected $reservaData;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'garagem_reservas_cancelar_form';
  }

  public function getQuestion() {
    return $this->t('Tem a certeza que quer cancelar a reserva #@id?', ['@id' => $this->reservaId]);
  }

  public function getDescription() {
    return $this->t('Esta ação não pode ser desfeita. A garagem ficará disponível para outros utilizadores.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('garagem_reservas.reserva_view', ['reserva' => $this->reservaId]);
  }

  public function getConfirmText() {
    return $this->t('Sim, cancelar reserva');
  }

  public function getCancelText() {
    return $this->t('Voltar');
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $reserva = NULL) {
    $this->reservaId = $reserva;

    $this->reservaData = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva)
      ->execute()
      ->fetchObject();

    if (!$this->reservaData) {
      $this->messenger()->addError($this->t('Reserva não encontrada.'));
      return $this->redirect('garagem_reservas.lista_user');
    }

    // Verificar que é o próprio user.
    $current_user = \Drupal::currentUser();
    if ($current_user->id() != $this->reservaData->user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Verificar que o estado permite cancelar.
    $estados_cancelaveis = ['pendente', 'aprovado'];
    if (!in_array($this->reservaData->estado, $estados_cancelaveis)) {
      $this->messenger()->addError($this->t('Não é possível cancelar esta reserva no estado atual.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'cancelado'])
      ->condition('id', $this->reservaId)
      ->execute();

    // Notificar o proprietário.
    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaCancelada($this->reservaId);
    }
    catch (\Exception $e) {
      // Não bloquear o cancelamento se a notificação falhar.
    }

    $this->messenger()->addStatus($this->t('Reserva #@id cancelada com sucesso.', ['@id' => $this->reservaId]));
    $form_state->setRedirect('garagem_reservas.lista_user');
  }

}

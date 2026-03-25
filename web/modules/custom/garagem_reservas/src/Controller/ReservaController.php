<?php

namespace Drupal\garagem_reservas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller de reservas.
 */
class ReservaController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Ver detalhes da reserva.
   */
  public function view(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $current_user = \Drupal::currentUser();
    $is_proprietario = $current_user->id() == $reserva_data->proprietario_id;
    $is_user = $current_user->id() == $reserva_data->user_id;

    // Verificar acesso.
    if (!$is_proprietario && !$is_user && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $garagem = $this->entityTypeManager()->getStorage('node')->load($reserva_data->garagem_id);
    $user = $this->entityTypeManager()->getStorage('user')->load($reserva_data->user_id);

    $config = \Drupal::config('garagem_reservas.settings');
    $taxa_fixa = $config->get('taxa_fixa');
    $percentagem = $config->get('percentagem_plataforma');
    $taxa_percentual = $reserva_data->preco_total * $percentagem / 100;

    return [
      '#theme' => 'garagem_reserva',
      '#reserva' => $reserva_data,
      '#garagem' => $garagem,
      '#user' => $user,
      '#preco_total' => $reserva_data->preco_total,
      '#taxa_plataforma' => $reserva_data->taxa_plataforma,
      '#taxa_fixa' => $taxa_fixa,
      '#taxa_percentual' => $taxa_percentual,
      '#is_proprietario' => $is_proprietario,
      '#is_user' => $is_user,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Aprovar reserva.
   */
  public function aprovar(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data || $reserva_data->estado !== 'pendente') {
      $this->messenger()->addError($this->t('Não é possível aprovar esta reserva.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    $this->database->update('garagem_reserva')
      ->fields([
        'estado' => 'aprovado',
        'data_aprovacao' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $reserva)
      ->execute();

    \Drupal::service('garagem_reservas.notificacao')->reservaAprovada($reserva);

    $this->messenger()->addStatus($this->t('Reserva aprovada com sucesso.'));
    return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
  }

  /**
   * Rejeitar reserva.
   */
  public function rejeitar(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data || $reserva_data->estado !== 'pendente') {
      $this->messenger()->addError($this->t('Não é possível rejeitar esta reserva.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'rejeitado'])
      ->condition('id', $reserva)
      ->execute();

    \Drupal::service('garagem_reservas.notificacao')->reservaRejeitada($reserva);

    $this->messenger()->addStatus($this->t('Reserva rejeitada.'));
    return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
  }

  /**
   * Gerar PDF da reserva.
   */
  public function pdf(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $garagem = $this->entityTypeManager()->getStorage('node')->load($reserva_data->garagem_id);
    $user = $this->entityTypeManager()->getStorage('user')->load($reserva_data->user_id);
    $proprietario = $this->entityTypeManager()->getStorage('user')->load($reserva_data->proprietario_id);

    $build = [
      '#theme' => 'garagem_reserva_pdf',
      '#reserva' => $reserva_data,
      '#garagem' => $garagem,
      '#user' => $user,
      '#proprietario' => $proprietario,
      '#preco_total' => $reserva_data->preco_total,
      '#taxa_plataforma' => $reserva_data->taxa_plataforma,
      '#data_geracao' => date('d/m/Y H:i'),
    ];

    $html = \Drupal::service('renderer')->renderRoot($build);

    // TODO: Integrar com biblioteca PDF (ex: dompdf) quando disponível.
    // Por agora retorna HTML.
    return new Response($html);
  }

  /**
   * Lista de reservas do proprietário.
   */
  public function listaProprietario() {
    $current_user = \Drupal::currentUser();

    $reservas = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('proprietario_id', $current_user->id())
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    return [
      '#theme' => 'garagem_reservas_lista',
      '#reservas' => $reservas,
      '#tipo' => 'proprietario',
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Lista de reservas do utilizador.
   */
  public function listaUser() {
    $current_user = \Drupal::currentUser();

    $reservas = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('user_id', $current_user->id())
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    return [
      '#theme' => 'garagem_reservas_lista',
      '#reservas' => $reservas,
      '#tipo' => 'user',
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Página de notificações.
   */
  public function notificacoes() {
    $current_user = \Drupal::currentUser();

    $message_storage = \Drupal::entityTypeManager()->getStorage('message');
    $ids = $message_storage->getQuery()
      ->condition('uid', $current_user->id())
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $messages = $message_storage->loadMultiple($ids);

    $items = [];
    foreach ($messages as $message) {
      $texts = $message->getText();
      $text_rendered = '';
      foreach ($texts as $text) {
        if (is_array($text)) {
          $text_rendered .= \Drupal::service('renderer')->renderRoot($text);
        } else {
          $text_rendered .= $text;
        }
      }

      $items[] = [
        'bundle' => $message->bundle(),
        'created' => $message->getCreatedTime(),
        'text' => $text_rendered,
      ];
    }

    return [
      '#theme' => 'garagem_reservas_notificacoes',
      '#items' => $items,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Endpoint de disponibilidade (JSON para calendário).
   */
  public function disponibilidade(int $node) {
    $datas = \Drupal::service('garagem_reservas.disponibilidade')->getDatasOcupadas($node);
    return new JsonResponse($datas);
  }

  /**
   * Obtém dados da reserva.
   */
  protected function getReserva(int $reserva_id): ?object {
    return $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

}

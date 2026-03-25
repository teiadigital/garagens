<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;

/**
 * Serviço de notificações de reservas usando o módulo Message.
 */
class NotificacaoService {

  use StringTranslationTrait;

  protected $database;
  protected $languageManager;
  protected $entityTypeManager;
  protected $messageNotifier;

  public function __construct(
    Connection $database,
    LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    MessageNotifier $messageNotifier
  ) {
    $this->database = $database;
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->messageNotifier = $messageNotifier;
  }

  /**
   * Notifica quando uma reserva é criada.
   */
  public function reservaCriada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;

    $proprietario = $this->entityTypeManager->getStorage('user')->load($reserva->proprietario_id);
    $this->enviarMensagem('reserva_criada_proprietario', $proprietario, $reserva_id);
  }

  /**
   * Notifica quando uma reserva é aprovada.
   */
  public function reservaAprovada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;

    $user = $this->entityTypeManager->getStorage('user')->load($reserva->user_id);
    $this->enviarMensagem('reserva_aprovada_user', $user, $reserva_id);
  }

  /**
   * Notifica quando uma reserva é rejeitada.
   */
  public function reservaRejeitada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;

    $user = $this->entityTypeManager->getStorage('user')->load($reserva->user_id);
    $this->enviarMensagem('reserva_rejeitada_user', $user, $reserva_id);
  }

  /**
   * Notifica quando uma reserva expira.
   */
  public function reservaExpirada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;

    $user = $this->entityTypeManager->getStorage('user')->load($reserva->user_id);
    $this->enviarMensagem('reserva_expirada', $user, $reserva_id);
  }

  /**
   * Notifica proprietário quando pagamento é recebido.
   */
  public function reservaPaga(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;

    $proprietario = $this->entityTypeManager->getStorage('user')->load($reserva->proprietario_id);
    $this->enviarMensagem('reserva_paga_proprietario', $proprietario, $reserva_id);
  }

  /**
   * Cria e envia uma mensagem via Message + Message Notify.
   */
  protected function enviarMensagem(string $template, $destinatario, int $reserva_id): void {
    $message = Message::create([
      'template' => $template,
      'uid' => $destinatario->id(),
      'field_reserva_id' => $reserva_id,
    ]);
    $message->save();

    // Enviar por email via Message Notify.
    $this->messageNotifier->send($message, [], 'email');
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

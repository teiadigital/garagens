<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;

/**
 * Serviço de notificações de reservas.
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
   * Reserva criada pelo user.
   * → Proprietário: nova reserva para aprovar.
   * → User: confirmação de submissão.
   */
  public function reservaCriada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $this->enviar('reserva_criada_proprietario', $this->loadUser($reserva->proprietario_id), $reserva_id, $garagem_titulo);
    $this->enviar('reserva_criada_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo);
  }

  /**
   * Reserva aprovada pelo proprietário.
   * → User: reserva aprovada, ir pagar.
   */
  public function reservaAprovada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $this->enviar('reserva_aprovada_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo);
  }

  /**
   * Reserva rejeitada pelo proprietário.
   * → User: reserva rejeitada.
   */
  public function reservaRejeitada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $motivo = $reserva->motivo ?? '';
    $this->enviar('reserva_rejeitada_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo, $motivo);
  }

  /**
   * User iniciou o checkout.
   * → User: lembrete para concluir o pagamento.
   */
  public function reservaAguardaPagamento(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $this->enviar('reserva_aguarda_pagamento_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo);
  }

  /**
   * Pagamento confirmado.
   * → Proprietário: pagamento recebido.
   * → User: confirmação de pagamento.
   */
  public function reservaPaga(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $this->enviar('reserva_paga_proprietario', $this->loadUser($reserva->proprietario_id), $reserva_id, $garagem_titulo);
    $this->enviar('reserva_paga_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo);
  }

  /**
   * Reserva cancelada pelo user.
   * → Proprietário: reserva cancelada pelo arrendatário.
   * → User: confirmação de cancelamento.
   */
  public function reservaCanceladaPeloUser(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $motivo = $reserva->motivo ?? '';
    $this->enviar('reserva_cancelada_proprietario', $this->loadUser($reserva->proprietario_id), $reserva_id, $garagem_titulo, $motivo);
    $this->enviar('reserva_cancel_user_confirm', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo, $motivo);
  }

  /**
   * Reserva cancelada pelo proprietário.
   * → User: reserva cancelada pelo proprietário.
   * → Proprietário: confirmação de cancelamento.
   */
  public function reservaCanceladaPeloProprietario(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $motivo = $reserva->motivo ?? '';
    $this->enviar('reserva_cancel_prop_user', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo, $motivo);
    $this->enviar('reserva_cancel_prop_confirm', $this->loadUser($reserva->proprietario_id), $reserva_id, $garagem_titulo, $motivo);
  }

  /**
   * Reserva expirada (cron).
   * → User: reserva expirou.
   * → Proprietário: garagem disponível novamente.
   */
  public function reservaExpirada(int $reserva_id): void {
    $reserva = $this->getReserva($reserva_id);
    if (!$reserva) return;
    $garagem_titulo = $this->getGaragemTitulo($reserva->garagem_id);
    $this->enviar('reserva_expirada', $this->loadUser($reserva->user_id), $reserva_id, $garagem_titulo);
    $this->enviar('reserva_expirada_proprietario', $this->loadUser($reserva->proprietario_id), $reserva_id, $garagem_titulo);
  }

  /**
   * Alias legado.
   */
  public function reservaCancelada(int $reserva_id): void {
    $this->reservaCanceladaPeloUser($reserva_id);
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  protected function enviar(string $template, $destinatario, int $reserva_id, string $garagem_titulo = '', string $motivo = ''): void {
    if (!$destinatario) return;
    try {
      $message = Message::create([
        'template' => $template,
        'uid' => $destinatario->id(),
      ]);

      $arguments = [
        '@reserva_id' => $reserva_id,
      ];
      if ($garagem_titulo) {
        $arguments['@garagem'] = $garagem_titulo;
      }
      // Se há motivo, formata como " Motivo: texto" inline na frase.
      $arguments['@motivo'] = $motivo ? ' ' . t('Motivo') . ': ' . $motivo : '';
      $message->set('arguments', $arguments);

      $message->save();
      $this->messageNotifier->send($message, [], 'email');
    }
    catch (\Exception $e) {
      \Drupal::logger('garagem_reservas')->warning(
        'Erro ao enviar notificação @template para user @uid: @msg',
        ['@template' => $template, '@uid' => $destinatario->id(), '@msg' => $e->getMessage()]
      );
    }
  }

  protected function getGaragemTitulo(int $garagem_id): string {
    $node = $this->entityTypeManager->getStorage('node')->load($garagem_id);
    return $node ? $node->getTitle() : '';
  }

  protected function loadUser(int $uid) {
    return $this->entityTypeManager->getStorage('user')->load($uid);
  }

  protected function getReserva(int $reserva_id): ?object {
    return $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

}

<?php

namespace Drupal\management_armazens\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;

/**
 * Serviço de notificações de garagens.
 */
class NotificacaoGaragemService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected MessageNotifier $messageNotifier;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MessageNotifier $messageNotifier
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->messageNotifier = $messageNotifier;
  }

  /**
   * Garagem criada — notifica o proprietário e os gestores.
   */
  public function garagemCriada(NodeInterface $node): void {
    $proprietario = $this->entityTypeManager->getStorage('user')
      ->load($node->getOwnerId());

    if ($proprietario) {
      $this->enviar('garagem_criada_user', $proprietario, $node->getTitle());
    }

    $gestores = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'status' => 1,
      'roles' => 'gestor',
    ]);

    foreach ($gestores as $gestor) {
      $this->enviar('garagem_criada_gestor', $gestor, $node->getTitle());
    }
  }

  /**
   * Garagem aprovada — notifica o proprietário.
   */
  public function garagemAprovada(NodeInterface $node): void {
    $proprietario = $this->entityTypeManager->getStorage('user')
      ->load($node->getOwnerId());

    if ($proprietario) {
      $this->enviar('garagem_aprovada_user', $proprietario, $node->getTitle());
    }
  }

  /**
   * Garagem com erros — notifica o proprietário com a descrição.
   */
  public function garagemComErros(NodeInterface $node, string $descricao = ''): void {
    $proprietario = $this->entityTypeManager->getStorage('user')
      ->load($node->getOwnerId());

    if ($proprietario) {
      $this->enviar('garagem_com_erros_user', $proprietario, $node->getTitle(), ['@descricao' => $descricao]);
    }
  }

  /**
   * Garagem rejeitada — notifica o proprietário com o motivo.
   */
  public function garagemRejeitada(NodeInterface $node, string $motivo = ''): void {
    $proprietario = $this->entityTypeManager->getStorage('user')
      ->load($node->getOwnerId());

    if ($proprietario) {
      $this->enviar('garagem_rejeitada_user', $proprietario, $node->getTitle(), ['@motivo' => $motivo]);
    }
  }

  protected function enviar(string $template, $destinatario, string $garagem_titulo, array $extra_arguments = []): void {
    try {
      $message = Message::create([
        'template' => $template,
        'uid'      => $destinatario->id(),
      ]);
      $message->set('arguments', array_merge(['@garagem' => $garagem_titulo], $extra_arguments));
      $message->save();
      $this->messageNotifier->send($message, [], 'email');
    }
    catch (\Exception $e) {
      \Drupal::logger('management_armazens')->warning(
        'Erro ao enviar notificação @template para user @uid: @msg',
        ['@template' => $template, '@uid' => $destinatario->id(), '@msg' => $e->getMessage()]
      );
    }
  }

}

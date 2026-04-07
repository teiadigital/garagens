<?php

namespace Drupal\management_armazens\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Aprova um armazém (estado = 3, publicado).
 */
class ApproveArmazemController extends ControllerBase {

  public function build(NodeInterface $node, Request $request) {
    if (!$node->hasField('field_estado')) {
      $this->messenger()->addError($this->t('Campo de estado não encontrado.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    $node->set('field_estado', 3);
    $node->setPublished();
    $node->save();

    try {
      \Drupal::service('management_armazens.notificacao_garagem')->garagemAprovada($node);
    }
    catch (\Exception $e) {
      \Drupal::logger('management_armazens')->warning('Erro ao notificar aprovação: @m', ['@m' => $e->getMessage()]);
    }

    $this->messenger()->addStatus($this->t('O armazém foi aprovado e publicado.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}

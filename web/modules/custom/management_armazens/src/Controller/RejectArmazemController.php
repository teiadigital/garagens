<?php

namespace Drupal\management_armazens\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rejeita um armazém (estado = 2, despublicado).
 */
class RejectArmazemController extends ControllerBase {

  public function build(NodeInterface $node, Request $request) {
    if (!$node->hasField('field_estado')) {
      $this->messenger()->addError($this->t('Campo de estado não encontrado.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    $motivo = substr(strip_tags($request->query->get('motivo', '')), 0, 1000);

    $node->set('field_estado', 2);
    $node->setUnpublished();
    $node->save();

    try {
      \Drupal::service('management_armazens.notificacao_garagem')->garagemRejeitada($node, $motivo);
    }
    catch (\Exception $e) {
      \Drupal::logger('management_armazens')->warning('Erro ao notificar rejeição: @m', ['@m' => $e->getMessage()]);
    }

    $this->messenger()->addStatus($this->t('O armazém foi rejeitado.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}

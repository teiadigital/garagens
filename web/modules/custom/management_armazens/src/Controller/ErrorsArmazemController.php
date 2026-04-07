<?php

namespace Drupal\management_armazens\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Marca armazém com erros (estado = 1, despublicado).
 */
class ErrorsArmazemController extends ControllerBase {

  public function build(NodeInterface $node, Request $request) {
    if (!$node->hasField('field_estado')) {
      $this->messenger()->addError($this->t('Campo de estado não encontrado.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    $descricao = substr(strip_tags($request->query->get('descricao', '')), 0, 1000);

    $node->set('field_estado', 1);
    $node->setUnpublished();
    $node->save();

    try {
      \Drupal::service('management_armazens.notificacao_garagem')->garagemComErros($node, $descricao);
    }
    catch (\Exception $e) {
      \Drupal::logger('management_armazens')->warning('Erro ao notificar erros: @m', ['@m' => $e->getMessage()]);
    }

    $this->messenger()->addStatus($this->t('Foi enviada uma mensagem ao proprietário para corrigir os erros.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}

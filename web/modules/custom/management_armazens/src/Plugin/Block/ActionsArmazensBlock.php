<?php

declare(strict_types=1);

namespace Drupal\management_armazens\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use \Drupal\user\Entity\User;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\node\NodeInterface;

/**
 * Provides an actions armazens block.
 *
 * @Block(
 *   id = "management_armazens_actions_armazens",
 *   admin_label = @Translation("Actions Armazens"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
final class ActionsArmazensBlock extends BlockBase
{


  private function states($state)
  {

    switch ($state) {
      case '-1':
        return [
          'class' => 'nao-acabado',
          'state' => t("Unfinished")
        ];
      case '0':
        return [
          'class' => 'aguardar-aprovacao',
          'state' => t("Awaiting approval")
        ];
      case '1':
        return [
          'class' => 'com-erros',
          'state' => t("Warehouse with errors")
        ];
      case '2':
        return [
          'class' => 'nao-aprovado',
          'state' => t("Not Approved")
        ];
      case '3':
        return [
          'class' => 'aprovado',
          'state' => t("Approved")
        ];
    }
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $form = NULL;
    $data = NULL;

    // 1) Tenta contexto; se falhar, tenta node da rota.
    $node = NULL;
    try {
      $node = $this->getContextValue('node');
    } catch (\Throwable $e) {
      // ignorar
    }
    if (!$node instanceof NodeInterface) {
      $route_node = \Drupal::routeMatch()->getParameter('node');
      if ($route_node instanceof NodeInterface) {
        $node = $route_node;
      }
    }
    if (!$node instanceof NodeInterface || $node->isNew() || $node->bundle() !== 'armazem') {
      return [];
    }

    $uid  = (int) \Drupal::currentUser()->id();
    $user = User::load($uid);

    // Estado (seguro).
    $estado = NULL;
    if ($node->hasField('field_estado') && !$node->get('field_estado')->isEmpty()) {
      $estado = (string) $node->get('field_estado')->value;
    }
    $data = ['estado' => $this->states($estado)];

    // 2) Gestor/admin: mostra o formulário (se existir) quando não é rascunho.
    if ($user && (in_array('gestor', $user->getRoles(), TRUE) || in_array('administrator', $user->getRoles(), TRUE))) {
      if ($estado !== '-1' && $estado !== '' && class_exists('Drupal\\management_armazens\\Form\\ActionsArmazensForm')) {
        $form = \Drupal::formBuilder()->getForm('Drupal\management_armazens\Form\ActionsArmazensForm', $node);
      }
    }

    // 3) “Editar” para quem tem acesso de update (mais fiável que só papel 'utilizador').
    if ($node->access('update')) {
      $url  = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
      $link = Link::fromTextAndUrl(t('Edit'), $url)->toRenderable();
      $data['edit'] = $link;
    }

    return [
      '#theme' => 'actions_armazens',
      '#data'  => $data,
      '#form'  => $form,
      '#cache' => [
        'contexts' => ['route', 'user', 'user.roles', 'languages:language_interface'],
        'tags'     => $node->getCacheTags(),
        'max-age'  => 0,
      ],
    ];
  }

  public function getCacheMaxAge()
  {
    return 0;
  }
}

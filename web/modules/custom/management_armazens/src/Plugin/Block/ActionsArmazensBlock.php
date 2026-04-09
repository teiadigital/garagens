<?php

declare(strict_types=1);

namespace Drupal\management_armazens\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
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
        return ['class' => 'nao-acabado',        'state' => t("Unfinished")];
      case '0':
        return ['class' => 'aguardar-aprovacao', 'state' => t("Awaiting approval")];
      case '1':
        return ['class' => 'com-erros',          'state' => t("Garage with errors")];
      case '2':
        return ['class' => 'nao-aprovado',       'state' => t("Not Approved")];
      case '3':
        return ['class' => 'aprovado',           'state' => t("Approved")];
    }
    return FALSE;
  }

  public function blockAccess(AccountInterface $account) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name !== 'entity.node.canonical') {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }

    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'armazem') {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }

    $is_owner = (int) $node->getOwnerId() === (int) $account->id();
    $is_gestor = in_array('gestor', $account->getRoles(), TRUE)
      || in_array('administrator', $account->getRoles(), TRUE);

    return AccessResult::allowedIf($is_owner || $is_gestor)
      ->addCacheContexts(['route', 'user', 'user.roles']);
  }

  public function build()
  {
    // Obter o node do contexto ou da rota.
    $node = NULL;
    try {
      $node = $this->getContextValue('node');
    } catch (\Throwable $e) {}

    if (!$node instanceof NodeInterface) {
      $node = \Drupal::routeMatch()->getParameter('node');
    }

    if (!$node instanceof NodeInterface || $node->isNew() || $node->bundle() !== 'armazem') {
      return [];
    }

    $uid  = (int) \Drupal::currentUser()->id();
    $user = User::load($uid);

    $estado = NULL;
    if ($node->hasField('field_estado') && !$node->get('field_estado')->isEmpty()) {
      $estado = (string) $node->get('field_estado')->value;
    }

    $data = ['estado' => $this->states($estado)];

    // Link de editar para o próprio proprietário.
    if ($node->access('update')) {
      $url  = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
      $link = Link::fromTextAndUrl(t('Edit'), $url)->toRenderable();
      $data['edit'] = $link;
    }

    // URLs das ações para gestor/admin.
    $is_gestor = $user && (
      in_array('gestor', $user->getRoles(), TRUE) ||
      in_array('administrator', $user->getRoles(), TRUE)
    );

    if ($is_gestor && $estado !== '-1' && $estado !== '') {
      $data['url_approve'] = Url::fromRoute('management_armazens.armazem_approve', ['node' => $node->id()])->toString();
      $data['url_errors']  = Url::fromRoute('management_armazens.armazem_errors',  ['node' => $node->id()])->toString();
      $data['url_reject']  = Url::fromRoute('management_armazens.armazem_reject',  ['node' => $node->id()])->toString();
    }

    return [
      '#theme' => 'actions_armazens',
      '#data'  => $data,
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

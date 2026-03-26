<?php

namespace Drupal\garagem_reservas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Bloco com ações do proprietário da garagem.
 *
 * @Block(
 *   id = "garagem_proprietario_block",
 *   admin_label = @Translation("Ações Proprietário Garagem"),
 *   category = @Translation("Garagem Reservas")
 * )
 */
class ProprietarioBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $currentUser;
  protected $routeMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match')
    );
  }

  protected function blockAccess(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    $node = $this->routeMatch->getParameter('node');
    if (!$node || $node->getType() !== 'armazem') {
      return AccessResult::forbidden();
    }

    if ($node->getOwnerId() != $account->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if (!$node) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['garagem-proprietario-block']],
      'todas' => [
        '#type' => 'link',
        '#title' => t('Ver reservas desta garagem'),
        '#url' => \Drupal\Core\Url::fromRoute('garagem_reservas.lista_garagem', ['node' => $node->id()]),
        '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm', 'd-block', 'mb-2']],
      ],
      'disponibilidade' => [
        '#type' => 'link',
        '#title' => t('Gerir disponibilidade'),
        '#url' => \Drupal\Core\Url::fromRoute('garagem_reservas.garagem_disponibilidade', ['node' => $node->id()]),
        '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm', 'd-block']],
      ],
      '#cache' => ['contexts' => ['user', 'route'], 'max-age' => 0],
    ];

    return $build;
  }

}

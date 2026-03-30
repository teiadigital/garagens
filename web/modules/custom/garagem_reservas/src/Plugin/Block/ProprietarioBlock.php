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

    $estilo = 'display:block;width:100%;text-align:center;padding:12px 24px;background:#c7d2fe;color:#312e81;border-radius:9999px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;text-decoration:none;margin-bottom:8px;';

    $build = [
      '#type' => 'inline_template',
      '#template' => '
        <div>
          <a href="{{ url_reservas }}" style="{{ estilo }}">{{ label_reservas }}</a>
          <a href="{{ url_disponibilidade }}" style="{{ estilo }}">{{ label_disponibilidade }}</a>
        </div>',
      '#context' => [
        'estilo' => $estilo,
        'url_reservas' => \Drupal\Core\Url::fromRoute('garagem_reservas.lista_garagem', ['node' => $node->id()])->toString(),
        'label_reservas' => t('Ver reservas'),
        'url_disponibilidade' => \Drupal\Core\Url::fromRoute('garagem_reservas.garagem_disponibilidade', ['node' => $node->id()])->toString(),
        'label_disponibilidade' => t('Gerir disponibilidade'),
      ],
      '#cache' => ['contexts' => ['user', 'route'], 'max-age' => 0],
    ];

    return $build;
  }

}

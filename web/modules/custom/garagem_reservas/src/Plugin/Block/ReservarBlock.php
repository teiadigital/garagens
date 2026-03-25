<?php

namespace Drupal\garagem_reservas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Bloco com botão de reserva de garagem.
 *
 * @Block(
 *   id = "garagem_reservar_block",
 *   admin_label = @Translation("Botão Reservar Garagem"),
 *   category = @Translation("Garagem Reservas")
 * )
 */
class ReservarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
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

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Só aparece para utilizadores autenticados.
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    // Verificar se estamos na página de um node do tipo armazem.
    $node = $this->routeMatch->getParameter('node');
    if (!$node || $node->getType() !== 'armazem') {
      return AccessResult::forbidden();
    }

    // Não mostrar ao proprietário da garagem.
    if ($node->getOwnerId() == $account->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');

    if (!$node) {
      return [];
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Reservar'),
      '#url' => \Drupal\Core\Url::fromRoute('garagem_reservas.reserva_add', ['node' => $node->id()]),
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'btn-reservar'],
      ],
      '#cache' => [
        'contexts' => ['user', 'route'],
      ],
    ];
  }

}

<?php

namespace Drupal\garagem_reservas\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Card lateral unificado — mostra formulário de reserva ou ações de gestão.
 *
 * @Block(
 *   id = "garagem_card_lateral_block",
 *   admin_label = @Translation("Card Lateral Garagem"),
 *   category = @Translation("Garagem Reservas")
 * )
 */
class GaragemCardLateralBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $currentUser;
  protected $routeMatch;
  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, RouteMatchInterface $route_match, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('form_builder'),
    );
  }

  protected function blockAccess(AccountInterface $account) {
    $node = $this->routeMatch->getParameter('node');
    if (!$node || $node->getType() !== 'armazem') {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed()->addCacheContexts(['user', 'user.roles', 'route']);
  }

  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if (!$node) {
      return [];
    }

    $account   = $this->currentUser;
    $is_owner  = (int) $node->getOwnerId() === (int) $account->id();
    $roles     = $account->getRoles();
    $is_gestor = in_array('gestor', $roles, TRUE) || in_array('administrator', $roles, TRUE);

    // Visitante autenticado — mostrar formulário de reserva.
    if (!$is_owner && !$is_gestor) {
      if ($account->isAnonymous()) {
        return [];
      }
      return $this->formBuilder->getForm('Drupal\garagem_reservas\Form\ReservaForm', $node);
    }

    // Proprietário ou gestor — mostrar card de gestão.
    $estados_map = [
      '-1' => ['label' => t('Não acabado'),       'class' => 'nao-acabado'],
      '0'  => ['label' => t('Aguardar aprovação'), 'class' => 'aguardar-aprovacao'],
      '1'  => ['label' => t('Com erros'),          'class' => 'com-erros'],
      '2'  => ['label' => t('Não aprovado'),       'class' => 'nao-aprovado'],
      '3'  => ['label' => t('Aprovado'),           'class' => 'aprovado'],
    ];

    $estado_val  = $node->hasField('field_estado') ? (string) $node->get('field_estado')->value : NULL;
    $estado_info = isset($estados_map[$estado_val]) ? $estados_map[$estado_val] : NULL;

    $urls = [];

    if ($is_owner) {
      $urls['reservas']        = Url::fromRoute('garagem_reservas.lista_garagem', ['node' => $node->id()])->toString();
      $urls['disponibilidade'] = Url::fromRoute('garagem_reservas.garagem_disponibilidade', ['node' => $node->id()])->toString();
    }

    if ($node->access('update', $account)) {
      $urls['edit'] = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])->toString();
    }

    if ($is_gestor) {
      $urls['reservar'] = Url::fromRoute('garagem_reservas.reserva_add', ['node' => $node->id()])->toString();
    }

    if ($is_gestor && !in_array($estado_val, ['-1', '', NULL], TRUE)) {
      if ($estado_val !== '3') {
        $urls['approve'] = Url::fromRoute('management_armazens.armazem_approve', ['node' => $node->id()])->toString();
      }
      if ($estado_val !== '1') {
        $urls['errors'] = Url::fromRoute('management_armazens.armazem_errors', ['node' => $node->id()])->toString();
      }
      if ($estado_val !== '2') {
        $urls['reject'] = Url::fromRoute('management_armazens.armazem_reject', ['node' => $node->id()])->toString();
      }
    }

    return [
      '#theme'     => 'garagem_card_lateral',
      '#estado'    => $estado_info,
      '#urls'      => $urls,
      '#is_owner'  => $is_owner,
      '#is_gestor' => $is_gestor,
      '#cache'     => ['contexts' => ['user', 'user.roles', 'route'], 'max-age' => 0],
    ];
  }

  public function getCacheMaxAge() {
    return 0;
  }

}

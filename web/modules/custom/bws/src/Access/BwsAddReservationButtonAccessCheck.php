<?php

namespace Drupal\bws\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Check access to the Add reservation.
 */
class BwsAddReservationButtonAccessCheck implements AccessInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a BeeAddReservationAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Access method.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\node\Entity\Node $node
   *   A BEE node.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(AccountInterface $account, Node $node = NULL) {

    $bundle = FALSE;
    $route = \Drupal::routeMatch();
    $node = $route->getParameter('node');

    $current_user = $account->id();
    $uid = $node->getOwnerId();

    $estado = $node->get('field_estado')->getString();

    if ($node instanceof NodeInterface) {

      $units = $node->get('field_availability_daily')->getValue();
      
      if (empty($units)) {
        return AccessResult::forbidden();
      }

      if ($current_user == $uid) {
        return AccessResult::forbidden();
      }

      if ($estado != "3") {
        return AccessResult::forbidden();
      }
    }
    
    return AccessResult::allowed();
  }

}

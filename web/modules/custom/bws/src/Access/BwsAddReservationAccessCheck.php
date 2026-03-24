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
class BwsAddReservationAccessCheck implements AccessInterface {

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
   * The node type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodetypeStorage;

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
    $this->nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
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

    $nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
    $node_type = $nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bws_settings = $node_type->getThirdPartySetting('bws', 'bws');

    $current_user = $account->id();
    $uid = $node->getOwnerId();

    $estado = $node->get('field_estado')->getString();
    $units = $node->get('field_availability_daily')->getValue();


    if (isset($bws_settings['bookable']) && $bws_settings['bookable']) {
      if ($account->hasPermission('create bws reservation') && $current_user != $uid && $estado == "3" ) {
        return AccessResult::allowed();
      }
    }
    
    return AccessResult::forbidden();
  }

}

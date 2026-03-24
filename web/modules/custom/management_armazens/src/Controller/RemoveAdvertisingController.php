<?php

namespace Drupal\management_armazens\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;


/**
 * Returns responses for Gestão de Ofertas routes.
 */
class RemoveAdvertisingController extends ControllerBase {


  /**
   * Builds the response.
   */
  public function build(\Drupal\node\Entity\Node $nid, Request $request) {

    if($nid) {
      $nid->setUnPublished(TRUE)->save();
    }

    return $this->redirect('entity.node.canonical', ['node' => $nid->id()]);

  }

}

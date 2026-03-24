<?php
namespace Drupal\management_armazens\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class JsonApiCoordinatesArmazensController
 * @package Drupal\management_armazens\Controller
 */
class JsonApiCoordinatesArmazensController {

  /**
   * @return JsonResponse
   */
  public function index( $country, Request $request) {


    return new JsonResponse([ 'data' => $this->getData($country), 'method' => 'GET', 'status'=> 200]);
  }

  /**
   * @return array
   */
  public function getData($country) {
    $result=[];

    if($country == null || $country == "All") {

      $query = \Drupal::entityQuery('node')
      ->condition('type', 'armazem')
      ->condition('status', 1)
      ->accessCheck(FALSE);

    } else {
      
      $query = \Drupal::entityQuery('node')
      ->condition('type', 'armazem')
      ->condition('status', 1)
      ->condition('field_localidade', $country)
      ->accessCheck(FALSE);

    }
  
      
    $nodes_ids = $query->execute();
    if ($nodes_ids) {
      foreach ($nodes_ids as $node_id) {
        $node = \Drupal\node\Entity\Node::load($node_id);

        $field_coordenadas = $node->get('field_coordenadas')->getString();
    
        if($field_coordenadas) {
            $coordenadas = explode(",", $field_coordenadas);
            $result[] = [
            "title" => $node->getTitle(),
            "latitude" => $coordenadas[0],
            "longitude" => $coordenadas[1],
            ];
        }
      }
    }
    return $result;
  }
}
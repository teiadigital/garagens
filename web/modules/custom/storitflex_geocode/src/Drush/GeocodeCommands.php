<?php

namespace Drupal\storitflex_geocode\Drush;

use Drush\Attributes as CLI;
use Drush\Drush;
use Drush\Commands\DrushCommands; // Adicione esta linha
use Drupal\node\Entity\Node;
use Drupal\geocoder\GeocoderInterface;

class GeocodeCommands extends DrushCommands {

  #[CLI\Command(name: 'storitflex:geocode-armazens', aliases: ['st:geo'])]
  #[CLI\Help(description: 'Preenche coordenadas de todos os armazéns usando o campo de morada')]
  public function geocodeArmazens(): void {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'armazem')
      ->accessCheck(FALSE)
      ->execute();

    $atualizados = 0;
    $coords_map = [];

    // 1. Primeiro passo: calcular as coordenadas base de todos os nodes
    $nodes = Node::loadMultiple($nids);
    foreach ($nodes as $node) {
      if ($node->hasField('field_localidade') && $node->hasField('field_geo_coordenadas')) {
        if (!$node->get('field_localidade')->isEmpty()) {
          $address = $node->get('field_localidade')->first();
          $street = $address->address_line1 ?? '';
          $locality = $address->locality ?? '';
          $postal_code = $address->postal_code ?? '';
          $admin_area = $address->administrative_area ?? '';
          $country_name = $this->getCountryName($address->country_code);

          // Monta o endereço mais completo possível
          $morada = trim("{$postal_code} {$locality}, {$admin_area}, {$country_name}");

          $geocoder = \Drupal::service('geocoder');
          $result = $geocoder->geocode($morada, ['googlemaps']);

          if ($result && $result->first() && $result->first()->getCoordinates()) {
            $coordinates = $result->first()->getCoordinates();
            $lat = $coordinates->getLatitude();
            $lng = $coordinates->getLongitude();
            $key = sprintf('%F,%F', $lat, $lng);
            $coords_map[$key][] = $node;
          }
        }
      }
    }

    // 2. Segundo passo: distribuir os nodes de cada grupo em círculo
    foreach ($coords_map as $key => $nodes_group) {
      $count = count($nodes_group);

      // Ordena os nodes por nid para garantir consistência
      usort($nodes_group, function($a, $b) {
        return $a->id() <=> $b->id();
      });

      // Extrai as coordenadas base do grupo (atenção à ordem: lng,lat)
      list($lat, $lng) = explode(',', $key);
      $lat = floatval($lat);
      $lng = floatval($lng);

      foreach ($nodes_group as $i => $node) {
        if ($count > 1) {
          $angle = 2 * M_PI * $i / $count;
          $radius = 0.045; // 5km
          $lat_offset = $lat + cos($angle) * $radius;
          $lng_offset = $lng + sin($angle) * $radius;
        } else {
          $lat_offset = $lat;
          $lng_offset = $lng;
        }

        // WKT espera ordem: lng lat
        $wkt = sprintf('POINT(%F %F)', $lng_offset, $lat_offset);

        $node->set('field_geo_coordenadas', [
          'value' => $wkt,
        ]);
        $node->save();
        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
        $atualizados++;
        Drush::output()->writeln("📍 Gravado em {$node->label()}: $wkt");
      }
    }

    Drush::output()->writeln("✅ $atualizados armazéns atualizados com coordenadas.");
  }


  private function getCountryName(string $country_code): string {
    $countries = \Drupal::service('country_manager')->getList();
    return $countries[$country_code] ?? $country_code;
  }
}

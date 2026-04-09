<?php

namespace Drupal\storitflex_geocode\Drush;

use Drush\Attributes as CLI;
use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;

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

    // 1. Calcular coordenadas base de todos os nodes.
    $nodes = Node::loadMultiple($nids);
    foreach ($nodes as $node) {
      if (!$node->hasField('field_localidade') || !$node->hasField('field_geo_coordenadas')) {
        continue;
      }
      if ($node->get('field_localidade')->isEmpty()) {
        continue;
      }

      $address      = $node->get('field_localidade')->first();
      $postal_code  = trim($address->postal_code ?? '');
      $locality     = trim($address->locality ?? '');
      $administrative = trim($address->administrative_area ?? '');
      $countries    = \Drupal::service('country_manager')->getList();
      $country_name = (string) ($countries[$address->country_code] ?? $address->country_code);

      if (empty($postal_code)) {
        Drush::output()->writeln("⚠️  Sem código postal: {$node->label()}");
        continue;
      }

      $postal_short = explode('-', $postal_code)[0];
      $parts  = array_filter([$postal_short, $locality, $administrative, $country_name]);
      $morada = implode(', ', $parts);

      $geocoder = \Drupal::service('geocoder');
      $result   = $geocoder->geocode($morada, ['nominatim']);

      if ($result && $result->first() && $result->first()->getCoordinates()) {
        $coordinates = $result->first()->getCoordinates();
        $lat = $coordinates->getLatitude();
        $lng = $coordinates->getLongitude();
        $key = sprintf('%.6f,%.6f', $lat, $lng);
        $coords_map[$key][] = $node;
      }
      else {
        Drush::output()->writeln("⚠️  Sem resultado para: {$node->label()} ($morada)");
      }
    }

    // 2. Distribuir nodes com a mesma coordenada base em círculo.
    foreach ($coords_map as $key => $nodes_group) {
      $count = count($nodes_group);

      usort($nodes_group, fn($a, $b) => $a->id() <=> $b->id());

      [$lat, $lng] = array_map('floatval', explode(',', $key));

      foreach ($nodes_group as $i => $node) {
        if ($count > 1) {
          $angle      = 2 * M_PI * $i / $count;
          $radius     = 0.045; // ~5km
          $lat_offset = $lat + cos($angle) * $radius;
          $lng_offset = $lng + sin($angle) * $radius;
        }
        else {
          $lat_offset = $lat;
          $lng_offset = $lng;
        }

        $wkt = 'POINT(' . number_format($lng_offset, 6, '.', '') . ' ' . number_format($lat_offset, 6, '.', '') . ')';

        // Escreve diretamente na DB para contornar o entity_presave (que re-geocodificaria).
        $db = \Drupal::database();
        $vid = $node->getRevisionId();

        foreach (['node__field_geo_coordenadas', 'node_revision__field_geo_coordenadas'] as $table) {
          $exists = $db->select($table, 't')
            ->condition('entity_id', $node->id())
            ->countQuery()->execute()->fetchField();

          if ($exists) {
            $db->update($table)
              ->fields(['field_geo_coordenadas_value' => $wkt])
              ->condition('entity_id', $node->id())
              ->execute();
          }
          else {
            $db->insert($table)->fields([
              'bundle'                     => 'armazem',
              'deleted'                    => 0,
              'entity_id'                  => $node->id(),
              'revision_id'                => $vid,
              'langcode'                   => $node->language()->getId(),
              'delta'                      => 0,
              'field_geo_coordenadas_value' => $wkt,
            ])->execute();
          }
        }

        \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
        $atualizados++;
        Drush::output()->writeln("📍 {$node->label()}: $wkt");
      }
    }

    Drush::output()->writeln("✅ $atualizados armazéns atualizados.");
  }
}

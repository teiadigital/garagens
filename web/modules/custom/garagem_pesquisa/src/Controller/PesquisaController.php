<?php

declare(strict_types=1);

namespace Drupal\garagem_pesquisa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\garagem_reservas\Service\DisponibilidadeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PesquisaController extends ControllerBase {

  public function __construct(
    protected DisponibilidadeService $disponibilidade,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('garagem_reservas.disponibilidade'),
    );
  }

  public function page(): array {
    $tipos = [];
    $campos = ['dia' => 'field_preco_dia', 'mes' => 'field_preco_mes', 'ano' => 'field_preco_ano'];
    foreach ($campos as $tipo => $campo) {
      $count = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->condition('type', 'armazem')
        ->condition('status', 1)
        ->condition('field_estado', 3)
        ->condition($campo, NULL, 'IS NOT NULL')
        ->condition($campo, '', '<>')
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      if ($count > 0) {
        $tipos[] = $tipo;
      }
    }

    return [
      '#theme'    => 'garagem_pesquisa',
      '#attached' => [
        'library'        => ['garagem_pesquisa/pesquisa'],
        'drupalSettings' => [
          'garagemPesquisa' => [
            'ajaxUrl' => Url::fromRoute('garagem_pesquisa.ajax')->toString(),
            'tipos'   => $tipos,
          ],
        ],
      ],
    ];
  }

  public function ajax(Request $request): JsonResponse {
    $lat        = (float) $request->query->get('lat', 0);
    $lng        = (float) $request->query->get('lng', 0);
    $radius     = (float) $request->query->get('radius', 25);
    $tipo       = $request->query->get('tipo', '');
    $date_start = $request->query->get('date_start', '');
    $date_end   = $request->query->get('date_end', '');
    $limit      = (int) $request->query->get('limit', 12);
    $offset     = (int) $request->query->get('offset', 0);
    $map_only   = (bool) $request->query->get('map_only', 0);
    $bbox_n     = $request->query->get('bbox_n', '');
    $bbox_s     = $request->query->get('bbox_s', '');
    $bbox_e     = $request->query->get('bbox_e', '');
    $bbox_w     = $request->query->get('bbox_w', '');
    $tem_bbox   = $bbox_n !== '' && $bbox_s !== '' && $bbox_e !== '' && $bbox_w !== '';

    $tem_localizacao = $lat && $lng;

    // Calcular timestamps de disponibilidade
    $ts_start = NULL;
    $ts_end   = NULL;
    if ($date_start) {
      $ts_start = strtotime($date_start);
      if ($tipo === 'mes') {
        $ts_end = strtotime('+1 month', $ts_start);
      }
      elseif ($tipo === 'ano') {
        $ts_end = strtotime('+1 year', $ts_start);
      }
      elseif ($date_end) {
        $ts_end = strtotime($date_end);
      }
    }

    // Query base
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'armazem')
      ->condition('status', 1)
      ->condition('field_estado', 3)
      ->accessCheck(FALSE);

    // Filtrar por tipo de preço apenas quando há datas selecionadas
    if ($date_start) {
      if ($tipo === 'dia') {
        $query->condition('field_preco_dia', NULL, 'IS NOT NULL');
        $query->condition('field_preco_dia', '', '<>');
      }
      elseif ($tipo === 'mes') {
        $query->condition('field_preco_mes', NULL, 'IS NOT NULL');
        $query->condition('field_preco_mes', '', '<>');
      }
      elseif ($tipo === 'ano') {
        $query->condition('field_preco_ano', NULL, 'IS NOT NULL');
        $query->condition('field_preco_ano', '', '<>');
      }
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return new JsonResponse(['results' => []]);
    }

    $nodes   = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);
    $results = [];

    foreach ($nodes as $node) {
      if ($node->get('field_geo_coordenadas')->isEmpty()) {
        continue;
      }

      $wkt = $node->get('field_geo_coordenadas')->value;
      if (!preg_match('/POINT\s*\(\s*([^\s]+)\s+([^\)\s]+)\s*\)/', $wkt, $m)) {
        continue;
      }
      $node_lng = (float) $m[1];
      $node_lat = (float) $m[2];

      // Filtrar por bbox (modo mapa) ou por raio (modo lista)
      $distance = NULL;
      if ($tem_bbox) {
        if ($node_lat < (float) $bbox_s || $node_lat > (float) $bbox_n ||
            $node_lng < (float) $bbox_w || $node_lng > (float) $bbox_e) {
          continue;
        }
      }
      elseif ($tem_localizacao) {
        $distance = $this->haversine($lat, $lng, $node_lat, $node_lng);
        if ($distance > $radius) {
          continue;
        }
      }

      // Verificar disponibilidade usando o DisponibilidadeService
      if ($ts_start && $ts_end) {
        if (!$this->disponibilidade->verificarDisponibilidade((int) $node->id(), $ts_start, $ts_end)) {
          continue;
        }
      }

      // Foto
      $foto_url = '/themes/armazens_theme/images/placeholder_armazem.png';
      if ($node->hasField('field_fotos') && !$node->get('field_fotos')->isEmpty()) {
        $media = $node->get('field_fotos')->entity;
        if ($media) {
          $file = NULL;
          if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
            $file = $media->get('field_media_image')->entity;
          }
          elseif ($media->hasField('thumbnail') && !$media->get('thumbnail')->isEmpty()) {
            $file = $media->get('thumbnail')->entity;
          }
          if ($file) {
            $foto_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
      }

      // Preços
      $preco_dia = $node->hasField('field_preco_dia') && !$node->get('field_preco_dia')->isEmpty()
        ? (float) $node->get('field_preco_dia')->value : NULL;
      $preco_mes = $node->hasField('field_preco_mes') && !$node->get('field_preco_mes')->isEmpty()
        ? (float) $node->get('field_preco_mes')->value : NULL;
      $preco_ano = $node->hasField('field_preco_ano') && !$node->get('field_preco_ano')->isEmpty()
        ? (float) $node->get('field_preco_ano')->value : NULL;

      // Localidade
      $locality = '';
      if (!$node->get('field_localidade')->isEmpty()) {
        $address  = $node->get('field_localidade')->first();
        $locality = trim(($address->locality ?? '') . ', ' . ($address->administrative_area ?? ''), ', ');
      }

      $results[] = [
        'nid'       => $node->id(),
        'title'     => $node->label(),
        'locality'  => $locality,
        'preco_dia' => $preco_dia,
        'preco_mes' => $preco_mes,
        'preco_ano' => $preco_ano,
        'foto'      => $foto_url,
        'lat'       => $node_lat,
        'lng'       => $node_lng,
        'distance'  => $distance !== NULL ? round($distance, 1) : NULL,
        'url'       => Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(),
      ];
    }

    usort($results, fn($a, $b) => ($a['distance'] ?? PHP_INT_MAX) <=> ($b['distance'] ?? PHP_INT_MAX));

    // Modo mapa — devolver só markers, sem paginação
    if ($map_only) {
      $markers = array_map(fn($r) => [
        'nid'       => $r['nid'],
        'lat'       => $r['lat'],
        'lng'       => $r['lng'],
        'title'     => $r['title'],
        'locality'  => $r['locality'],
        'preco_dia' => $r['preco_dia'],
        'preco_mes' => $r['preco_mes'],
        'preco_ano' => $r['preco_ano'],
        'foto'      => $r['foto'],
        'url'       => $r['url'],
      ], $results);
      return new JsonResponse(['markers' => $markers]);
    }

    $total = count($results);
    $pagina = array_slice($results, $offset, $limit);

    return new JsonResponse([
      'results'  => $pagina,
      'total'    => $total,
      'has_more' => ($offset + $limit) < $total,
    ]);
  }

  private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
  }

}

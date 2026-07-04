<?php

declare(strict_types=1);

namespace Drupal\garagem_pesquisa\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\garagem_reservas\Service\DisponibilidadeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PesquisaController extends ControllerBase {

  private const DEFAULT_RADIUS = 15.0;

  private const PAGE_LIMIT = 12;

  public function __construct(
    protected DisponibilidadeService $disponibilidade,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('garagem_reservas.disponibilidade'),
    );
  }

  public function page(Request $request): array {
    $tipos = $this->getAvailableTypes();
    $filters = $this->buildFilters($request, $tipos);
    $all_results = $this->searchResults($filters);
    $page_results = array_slice($all_results, 0, self::PAGE_LIMIT);
    $has_geolocation = $filters['lat'] !== NULL && $filters['lng'] !== NULL;
    $has_bbox = $this->hasBbox($filters);
    $should_show_map = TRUE;
    $meta_description = $this->buildMetaDescription($filters);

    $heading = $filters['q'] !== ''
      ? $this->t('Garagens em @localidade', ['@localidade' => $filters['q']])
      : $this->t('Garagens disponíveis');

    $intro = $filters['q'] !== ''
      ? $this->t('Explore garagens disponíveis perto de @localidade e refine a procura por datas ou pela área do mapa.', ['@localidade' => $filters['q']])
      : $this->t('Encontre garagens disponíveis e refine a pesquisa por localização, datas e área do mapa.');

    return [
      '#theme' => 'garagem_pesquisa',
      '#heading' => $heading,
      '#intro' => $intro,
      '#results' => $page_results,
      '#total' => count($all_results),
      '#has_results' => !empty($page_results),
      '#show_map' => $should_show_map,
      '#query' => $filters['q'],
      '#lat' => $filters['lat'] !== NULL ? (string) $filters['lat'] : '',
      '#lng' => $filters['lng'] !== NULL ? (string) $filters['lng'] : '',
      '#radius' => (string) $filters['radius'],
      '#bbox_n' => $filters['bbox_n'] !== NULL ? (string) $filters['bbox_n'] : '',
      '#bbox_s' => $filters['bbox_s'] !== NULL ? (string) $filters['bbox_s'] : '',
      '#bbox_e' => $filters['bbox_e'] !== NULL ? (string) $filters['bbox_e'] : '',
      '#bbox_w' => $filters['bbox_w'] !== NULL ? (string) $filters['bbox_w'] : '',
      '#tipo' => $filters['tipo'],
      '#date_start' => $filters['date_start'],
      '#date_end' => $filters['date_end'],
      '#date_display' => $this->buildDateDisplay($filters['date_start'], $filters['date_end']),
      '#attached' => [
        'library' => ['garagem_pesquisa/pesquisa'],
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'name' => 'description',
                'content' => $meta_description,
              ],
            ],
            'garagem_pesquisa_meta_description',
          ],
        ],
        'drupalSettings' => [
          'garagemPesquisa' => [
            'ajaxUrl' => Url::fromRoute('garagem_pesquisa.ajax')->toString(),
            'tipos' => $tipos,
            'pageLimit' => self::PAGE_LIMIT,
            'initialState' => [
              'total' => count($all_results),
              'offset' => count($page_results),
              'params' => $this->buildAjaxParams($filters),
              'markers' => $this->buildMarkers($all_results),
              'showMap' => $should_show_map,
              'lat' => $filters['lat'],
              'lng' => $filters['lng'],
            ],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function buildMetaDescription(array $filters): string {
    $parts = [];

    if ($filters['q'] !== '') {
      $parts[] = $this->t('Pesquise garagens disponíveis em @localidade.', [
        '@localidade' => $filters['q'],
      ])->render();
    }
    else {
      $parts[] = $this->t('Pesquise garagens disponíveis em Portugal.')->render();
    }

    if ($filters['tipo'] === 'dia') {
      $parts[] = $this->t('Refine por datas diárias e localização no mapa.')->render();
    }
    elseif ($filters['tipo'] === 'mes') {
      $parts[] = $this->t('Compare opções de aluguer ao mês por localização.')->render();
    }
    elseif ($filters['tipo'] === 'ano') {
      $parts[] = $this->t('Compare opções de aluguer ao ano por localização.')->render();
    }

    return Unicode::truncate(trim(implode(' ', $parts)), 155, TRUE, TRUE);
  }

  public function ajax(Request $request): JsonResponse {
    $filters = $this->buildFilters($request, $this->getAvailableTypes());
    $limit = max(1, (int) $request->query->get('limit', self::PAGE_LIMIT));
    $offset = max(0, (int) $request->query->get('offset', 0));
    $map_only = (bool) $request->query->get('map_only', 0);
    $results = $this->searchResults($filters);

    if ($map_only) {
      return new JsonResponse([
        'markers' => $this->buildMarkers($results),
      ]);
    }

    return new JsonResponse([
      'results' => array_slice($results, $offset, $limit),
      'total' => count($results),
      'has_more' => ($offset + $limit) < count($results),
    ]);
  }

  private function getAvailableTypes(): array {
    $tipos = [];
    $campos = [
      'dia' => 'field_preco_dia',
      'mes' => 'field_preco_mes',
      'ano' => 'field_preco_ano',
    ];

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

    return $tipos;
  }

  private function buildFilters(Request $request, array $tipos): array {
    $filters = [
      'q' => trim((string) $request->query->get('q', '')),
      'lat' => $this->getFloatQuery($request, 'lat'),
      'lng' => $this->getFloatQuery($request, 'lng'),
      'radius' => (float) $request->query->get('radius', self::DEFAULT_RADIUS),
      'tipo' => (string) $request->query->get('tipo', $tipos[0] ?? 'dia'),
      'date_start' => trim((string) $request->query->get('date_start', '')),
      'date_end' => trim((string) $request->query->get('date_end', '')),
      'bbox_n' => $this->getFloatQuery($request, 'bbox_n'),
      'bbox_s' => $this->getFloatQuery($request, 'bbox_s'),
      'bbox_e' => $this->getFloatQuery($request, 'bbox_e'),
      'bbox_w' => $this->getFloatQuery($request, 'bbox_w'),
    ];

    if (!in_array($filters['tipo'], $tipos, TRUE) && !empty($tipos)) {
      $filters['tipo'] = $tipos[0];
    }

    if (!$this->hasBbox($filters) && $filters['q'] !== '' && ($filters['lat'] === NULL || $filters['lng'] === NULL)) {
      $coords = $this->resolveSearchCoordinates($filters['q']);
      if ($coords !== NULL) {
        $filters['lat'] = $coords['lat'];
        $filters['lng'] = $coords['lng'];
      }
    }

    if ($filters['tipo'] === 'dia' && $filters['date_start'] !== '' && $filters['date_end'] !== '') {
      $start = strtotime($filters['date_start']);
      $end = strtotime($filters['date_end']);
      if ($start !== FALSE && $end !== FALSE && $end <= $start) {
        $filters['date_end'] = '';
      }
    }

    return $filters;
  }

  private function searchResults(array $filters): array {
    $tem_localizacao = $filters['lat'] !== NULL && $filters['lng'] !== NULL;
    $tem_bbox = $this->hasBbox($filters);

    if (!$tem_bbox && !$tem_localizacao && $filters['q'] !== '') {
      return [];
    }

    $ts_start = NULL;
    $ts_end = NULL;
    if ($filters['date_start'] !== '') {
      $ts_start = strtotime($filters['date_start']);
      if ($filters['tipo'] === 'mes') {
        $ts_end = strtotime('+1 month', $ts_start);
      }
      elseif ($filters['tipo'] === 'ano') {
        $ts_end = strtotime('+1 year', $ts_start);
      }
      elseif ($filters['date_end'] !== '') {
        $ts_end = strtotime($filters['date_end']);
      }
    }

    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'armazem')
      ->condition('status', 1)
      ->condition('field_estado', 3)
      ->accessCheck(FALSE);

    if ($filters['date_start'] !== '') {
      if ($filters['tipo'] === 'dia') {
        $query->condition('field_preco_dia', NULL, 'IS NOT NULL');
        $query->condition('field_preco_dia', '', '<>');
      }
      elseif ($filters['tipo'] === 'mes') {
        $query->condition('field_preco_mes', NULL, 'IS NOT NULL');
        $query->condition('field_preco_mes', '', '<>');
      }
      elseif ($filters['tipo'] === 'ano') {
        $query->condition('field_preco_ano', NULL, 'IS NOT NULL');
        $query->condition('field_preco_ano', '', '<>');
      }
    }

    $nids = $query->execute();
    if (empty($nids)) {
      return [];
    }

    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);
    $results = [];

    foreach ($nodes as $node) {
      if ($node->get('field_geo_coordenadas')->isEmpty()) {
        continue;
      }

      $wkt = $node->get('field_geo_coordenadas')->value;
      if (!preg_match('/POINT\s*\(\s*([^\s]+)\s+([^\)\s]+)\s*\)/', $wkt, $matches)) {
        continue;
      }

      $node_lng = (float) $matches[1];
      $node_lat = (float) $matches[2];
      $distance = NULL;

      if ($tem_bbox) {
        if ($node_lat < $filters['bbox_s'] || $node_lat > $filters['bbox_n'] || $node_lng < $filters['bbox_w'] || $node_lng > $filters['bbox_e']) {
          continue;
        }
      }
      elseif ($tem_localizacao) {
        $distance = $this->haversine($filters['lat'], $filters['lng'], $node_lat, $node_lng);
        if ($distance > $filters['radius']) {
          continue;
        }
      }

      if ($ts_start && $ts_end && !$this->disponibilidade->verificarDisponibilidade((int) $node->id(), $ts_start, $ts_end)) {
        continue;
      }

      $results[] = $this->normalizeResult($node, $node_lat, $node_lng, $distance);
    }

    usort($results, static fn(array $a, array $b): int => ($a['distance'] ?? PHP_INT_MAX) <=> ($b['distance'] ?? PHP_INT_MAX));

    return $results;
  }

  private function normalizeResult($node, float $node_lat, float $node_lng, ?float $distance): array {
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

    $preco_dia = $node->hasField('field_preco_dia') && !$node->get('field_preco_dia')->isEmpty()
      ? (float) $node->get('field_preco_dia')->value : NULL;
    $preco_mes = $node->hasField('field_preco_mes') && !$node->get('field_preco_mes')->isEmpty()
      ? (float) $node->get('field_preco_mes')->value : NULL;
    $preco_ano = $node->hasField('field_preco_ano') && !$node->get('field_preco_ano')->isEmpty()
      ? (float) $node->get('field_preco_ano')->value : NULL;

    $locality = '';
    if (!$node->get('field_localidade')->isEmpty()) {
      $address = $node->get('field_localidade')->first();
      $locality = trim(($address->locality ?? '') . ', ' . ($address->administrative_area ?? ''), ', ');
    }

    return [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'locality' => $locality,
      'preco_dia' => $preco_dia,
      'preco_mes' => $preco_mes,
      'preco_ano' => $preco_ano,
      'foto' => $foto_url,
      'lat' => $node_lat,
      'lng' => $node_lng,
      'distance' => $distance !== NULL ? round($distance, 1) : NULL,
      'url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(),
    ];
  }

  private function buildMarkers(array $results): array {
    return array_map(static fn(array $result): array => [
      'nid' => $result['nid'],
      'lat' => $result['lat'],
      'lng' => $result['lng'],
      'title' => $result['title'],
      'locality' => $result['locality'],
      'preco_dia' => $result['preco_dia'],
      'preco_mes' => $result['preco_mes'],
      'preco_ano' => $result['preco_ano'],
      'foto' => $result['foto'],
      'url' => $result['url'],
    ], $results);
  }

  private function buildDateDisplay(string $date_start, string $date_end): ?string {
    if ($date_start === '') {
      return NULL;
    }

    $start = date('d/m/Y', strtotime($date_start));
    if ($date_end === '') {
      return $start;
    }

    return $start . ' - ' . date('d/m/Y', strtotime($date_end));
  }

  private function buildAjaxParams(array $filters): array {
    $params = [
      'q' => $filters['q'],
      'tipo' => $filters['tipo'],
      'date_start' => $filters['date_start'],
      'date_end' => $filters['date_end'],
      'lat' => $filters['lat'],
      'lng' => $filters['lng'],
      'bbox_n' => $filters['bbox_n'],
      'bbox_s' => $filters['bbox_s'],
      'bbox_e' => $filters['bbox_e'],
      'bbox_w' => $filters['bbox_w'],
    ];

    if ((float) $filters['radius'] !== self::DEFAULT_RADIUS) {
      $params['radius'] = $filters['radius'];
    }

    return array_filter($params, static fn($value): bool => $value !== NULL && $value !== '');
  }

  private function getFloatQuery(Request $request, string $key): ?float {
    $value = $request->query->get($key);
    if ($value === NULL || $value === '') {
      return NULL;
    }

    return (float) $value;
  }

  private function hasBbox(array $filters): bool {
    return isset($filters['bbox_n'], $filters['bbox_s'], $filters['bbox_e'], $filters['bbox_w'])
      && $filters['bbox_n'] !== NULL
      && $filters['bbox_s'] !== NULL
      && $filters['bbox_e'] !== NULL
      && $filters['bbox_w'] !== NULL;
  }

  private function resolveSearchCoordinates(string $query): ?array {
    $query = trim($query);
    if ($query === '') {
      return NULL;
    }

    $queries = [
      $query,
      $query . ', Portugal',
    ];

    try {
      $geocoder = \Drupal::service('geocoder');
      foreach ($queries as $candidate) {
        $result = $geocoder->geocode($candidate, ['nominatim']);
        if ($result && $result->first() && $result->first()->getCoordinates()) {
          $coordinates = $result->first()->getCoordinates();
          return [
            'lat' => (float) $coordinates->getLatitude(),
            'lng' => (float) $coordinates->getLongitude(),
          ];
        }
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('garagem_pesquisa')->warning('Falha ao resolver a localização "@query": @message', [
        '@query' => $query,
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $radius = 6371;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng / 2) ** 2;

    return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
  }

}

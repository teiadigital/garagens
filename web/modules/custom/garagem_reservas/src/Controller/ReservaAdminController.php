<?php

namespace Drupal\garagem_reservas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller de administração de reservas.
 */
class ReservaAdminController extends ControllerBase {

  protected $database;
  protected $request;

  public function __construct(Connection $database, RequestStack $request_stack) {
    $this->database = $database;
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('request_stack')
    );
  }

  public function lista() {
    // Filtros via GET.
    $filtro_estado = $this->request->query->get('estado', '');
    $filtro_search = trim($this->request->query->get('search', ''));

    // Estatísticas gerais.
    $stats = [];
    foreach (['pendente', 'aprovado', 'aguarda_pagamento', 'pago', 'cancelado', 'rejeitado', 'expirado'] as $estado) {
      $stats[$estado] = (int) $this->database->select('garagem_reserva', 'gr')
        ->condition('estado', $estado)
        ->countQuery()->execute()->fetchField();
    }
    $total = array_sum($stats);

    // Total de receita (reservas pagas).
    $receita = (float) $this->database->select('garagem_reserva', 'gr')
      ->fields('gr', ['taxa_plataforma'])
      ->condition('estado', 'pago')
      ->execute()->fetchCol();
    $receita_total = array_sum(
      $this->database->select('garagem_reserva', 'gr')
        ->fields('gr', ['taxa_plataforma'])
        ->condition('estado', 'pago')
        ->execute()->fetchCol()
    );

    // Query principal com filtros.
    $query = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->orderBy('data_criacao', 'DESC');

    if ($filtro_estado) {
      $query->condition('estado', $filtro_estado);
    }

    $reservas = $query->execute()->fetchAll();

    // Enriquecer com dados de garagem/utilizador.
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $user_storage = $this->entityTypeManager()->getStorage('user');

    $rows = [];
    foreach ($reservas as $reserva) {
      $garagem = $node_storage->load($reserva->garagem_id);
      $user = $user_storage->load($reserva->user_id);
      $proprietario = $user_storage->load($reserva->proprietario_id);

      $garagem_titulo = $garagem ? $garagem->getTitle() : '-';
      $user_nome = $user ? $user->getDisplayName() : '-';
      $prop_nome = $proprietario ? $proprietario->getDisplayName() : '-';

      // Filtro de pesquisa.
      if ($filtro_search) {
        $haystack = strtolower($garagem_titulo . ' ' . $user_nome . ' ' . $prop_nome . ' ' . $reserva->id);
        if (strpos($haystack, strtolower($filtro_search)) === FALSE) {
          continue;
        }
      }

      $rows[] = [
        'id' => $reserva->id,
        'garagem' => $garagem_titulo,
        'user' => $user_nome,
        'proprietario' => $prop_nome,
        'inicio' => date('d/m/Y', $reserva->data_inicio),
        'fim' => $reserva->renovacao_automatica ? '∞' : ($reserva->data_fim ? date('d/m/Y', $reserva->data_fim) : '-'),
        'preco' => number_format($reserva->preco_total, 2) . '€',
        'taxa' => number_format($reserva->taxa_plataforma, 2) . '€',
        'estado' => $reserva->estado,
        'data_criacao' => date('d/m/Y H:i', $reserva->data_criacao),
        'url_ver' => Url::fromRoute('garagem_reservas.reserva_view', ['reserva' => $reserva->id])->toString(),
        'url_editar' => Url::fromRoute('garagem_reservas.admin_reserva_editar', ['reserva' => $reserva->id])->toString(),
        'url_apagar' => Url::fromRoute('garagem_reservas.admin_reserva_apagar', ['reserva' => $reserva->id])->toString(),
      ];
    }

    $estado_cores = [
      'pendente' => 'bg-yellow-100 text-yellow-800',
      'aprovado' => 'bg-green-100 text-green-700',
      'aguarda_pagamento' => 'bg-orange-100 text-orange-700',
      'pago' => 'bg-green-200 text-green-800',
      'rejeitado' => 'bg-red-100 text-red-600',
      'cancelado' => 'bg-gray-100 text-gray-500',
      'expirado' => 'bg-gray-100 text-gray-400',
    ];

    return [
      '#type' => 'inline_template',
      '#template' => '
        <div class="w-full">

          <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ "Reservas"|t }}</h1>
            <span class="text-xs text-gray-400">{{ total }} {{ "total"|t }}</span>
          </div>

          {# Filtros #}
          <form method="get" class="flex flex-wrap gap-3 mb-8">
            <input type="text" name="search" value="{{ search }}"
              placeholder="{{ "Pesquisar..."|t }}"
              class="bg-white border border-gray-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 w-64">
            <select name="estado"
              class="bg-white border border-gray-200 rounded-full px-5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300">
              <option value="">{{ "- Qualquer estado -"|t }}</option>
              {% for e in estados %}
                <option value="{{ e }}" {% if e == filtro_estado %}selected{% endif %}>{{ e|capitalize }}</option>
              {% endfor %}
            </select>
            <button type="submit"
              class="px-6 py-2 text-sm font-bold uppercase text-white bg-black rounded-full hover:bg-gray-800 transition">
              {{ "Filtrar"|t }}
            </button>
            {% if filtro_estado or search %}
              <a href="?"
                 class="px-6 py-2 text-sm font-bold uppercase text-gray-600 border border-gray-300 rounded-full hover:bg-gray-100 transition">
                {{ "Limpar"|t }}
              </a>
            {% endif %}
          </form>

          {# Tabela #}
          {% if rows is empty %}
            <p class="text-sm text-gray-400">{{ "Não existem reservas."|t }}</p>
          {% else %}
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-900">
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "ID"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Garagem"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Arrendatário"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Proprietário"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Início"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Fim"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Preço"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Taxa"|t }}</th>
                    <th class="pb-3 text-left text-xs font-bold uppercase tracking-widest text-gray-900">{{ "Estado"|t }}</th>
                    <th class="pb-3"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  {% for row in rows %}
                    <tr>
                      <td class="py-4 pr-4 font-bold text-gray-900">{{ row.id }}</td>
                      <td class="py-4 pr-6 text-gray-700">{{ row.garagem }}</td>
                      <td class="py-4 pr-6 text-gray-700">{{ row.user }}</td>
                      <td class="py-4 pr-6 text-gray-500">{{ row.proprietario }}</td>
                      <td class="py-4 pr-4 text-gray-600 whitespace-nowrap">{{ row.inicio }}</td>
                      <td class="py-4 pr-4 text-gray-600 whitespace-nowrap">{{ row.fim }}</td>
                      <td class="py-4 pr-4 font-medium text-gray-900 whitespace-nowrap">{{ row.preco }}</td>
                      <td class="py-4 pr-4 text-gray-500 whitespace-nowrap">{{ row.taxa }}</td>
                      <td class="py-4 pr-4">
                        <span class="px-3 py-1 text-xs font-bold uppercase tracking-wide rounded-full {{ estado_cores[row.estado] ?? "bg-gray-100 text-gray-500" }}">
                          {{ row.estado }}
                        </span>
                      </td>
                      <td class="py-4 text-right whitespace-nowrap">
                        <a href="{{ row.url_ver }}"
                           class="inline-flex px-5 py-2 text-xs font-bold uppercase text-white bg-black rounded-full hover:bg-gray-800 transition mr-1">
                          {{ "Saber mais"|t }}
                        </a>
                        <a href="{{ row.url_editar }}"
                           class="inline-flex px-5 py-2 text-xs font-bold uppercase text-gray-700 border border-gray-300 rounded-full hover:bg-gray-100 transition mr-1">
                          {{ "Editar"|t }}
                        </a>
                        <a href="{{ row.url_apagar }}"
                           class="inline-flex px-5 py-2 text-xs font-bold uppercase text-red-600 border border-red-200 rounded-full hover:bg-red-50 transition">
                          {{ "Apagar"|t }}
                        </a>
                      </td>
                    </tr>
                  {% endfor %}
                </tbody>
              </table>
            </div>
            <p class="text-xs text-gray-400 mt-4">{{ rows|length }} {{ "reservas encontradas"|t }}</p>
          {% endif %}

        </div>
      ',
      '#context' => [
        'rows' => $rows,
        'stats' => $stats,
        'total' => $total,
        'receita_total' => $receita_total,
        'filtro_estado' => $filtro_estado,
        'search' => $filtro_search,
        'estados' => ['pendente', 'aprovado', 'aguarda_pagamento', 'pago', 'cancelado', 'rejeitado', 'expirado'],
        'estado_cores' => $estado_cores,
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}

<?php

namespace Drupal\garagem_reservas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller de administração de reservas.
 */
class ReservaAdminController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Lista todas as reservas para o admin.
   */
  public function lista() {
    $reservas = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    $header = [
      'id' => ['data' => $this->t('#'), 'field' => 'id', 'sort' => 'desc'],
      'garagem' => $this->t('Garagem'),
      'user' => $this->t('Utilizador'),
      'proprietario' => $this->t('Proprietário'),
      'inicio' => $this->t('Início'),
      'fim' => $this->t('Fim'),
      'preco' => $this->t('Preço total'),
      'taxa' => $this->t('Taxa plataforma'),
      'estado' => $this->t('Estado'),
      'acoes' => $this->t('Ações'),
    ];

    $rows = [];
    foreach ($reservas as $reserva) {
      $garagem = $this->entityTypeManager()->getStorage('node')->load($reserva->garagem_id);
      $user = $this->entityTypeManager()->getStorage('user')->load($reserva->user_id);
      $proprietario = $this->entityTypeManager()->getStorage('user')->load($reserva->proprietario_id);

      $rows[] = [
        'id' => $reserva->id,
        'garagem' => $garagem ? $garagem->getTitle() : '-',
        'user' => $user ? $user->getDisplayName() : '-',
        'proprietario' => $proprietario ? $proprietario->getDisplayName() : '-',
        'inicio' => date('d/m/Y H:i', $reserva->data_inicio),
        'fim' => $reserva->renovacao_automatica ? $this->t('Renovação automática') : ($reserva->data_fim ? date('d/m/Y H:i', $reserva->data_fim) : '-'),
        'preco' => number_format($reserva->preco_total, 2) . '€',
        'taxa' => number_format($reserva->taxa_plataforma, 2) . '€',
        'estado' => [
          'data' => [
            '#markup' => '<span class="badge badge-' . $reserva->estado . '">' . ucfirst($reserva->estado) . '</span>',
          ],
        ],
        'acoes' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'ver' => [
                'title' => $this->t('Ver'),
                'url' => Url::fromRoute('garagem_reservas.reserva_view', ['reserva' => $reserva->id]),
              ],
              'editar' => [
                'title' => $this->t('Editar estado'),
                'url' => Url::fromRoute('garagem_reservas.admin_reserva_editar', ['reserva' => $reserva->id]),
              ],
              'apagar' => [
                'title' => $this->t('Apagar'),
                'url' => Url::fromRoute('garagem_reservas.admin_reserva_apagar', ['reserva' => $reserva->id]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Não existem reservas.'),
      '#attached' => [
        'library' => ['garagem_reservas/reserva_form'],
      ],
    ];
  }

}

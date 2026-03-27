<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário de gestão de disponibilidade da garagem.
 */
class GaragemDisponibilidadeForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'garagem_disponibilidade_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node) {
      return [];
    }

    // Verificar que é o proprietário da garagem.
    $current_user = \Drupal::currentUser();
    if ($node->getOwnerId() != $current_user->id() && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $form_state->set('garagem_node', $node);

    $form['#attached']['library'][] = 'garagem_reservas/flatpickr';
    $form['#attached']['drupalSettings']['garagemDisponibilidade'] = [
      'disponibilidadeUrl' => '/garagem/' . $node->id() . '/disponibilidade',
    ];

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Selecione datas ou intervalos que pretende bloquear para a garagem <strong>@titulo</strong>.', ['@titulo' => $node->getTitle()]) . '</p>',
    ];

    $form['datas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datas a bloquear'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['garagem-disponibilidade-picker'],
        'placeholder' => $this->t('Selecione uma data ou intervalo'),
        'readonly' => 'readonly',
      ],
      '#description' => $this->t('Pode selecionar um dia único ou um intervalo de datas.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bloquear datas'),
      '#button_type' => 'primary',
    ];

    // Lista de datas bloqueadas existentes.
    $bloqueios = $this->database->select('garagem_indisponibilidade', 'gi')
      ->fields('gi')
      ->condition('garagem_id', $node->id())
      ->orderBy('data_inicio', 'ASC')
      ->execute()
      ->fetchAll();

    if (!empty($bloqueios)) {
      $rows = [];
      foreach ($bloqueios as $bloqueio) {
        $inicio = date('d/m/Y', $bloqueio->data_inicio);
        $fim = date('d/m/Y', $bloqueio->data_fim);
        $periodo = ($inicio === $fim) ? $inicio : $inicio . ' - ' . $fim;
        $rows[] = [
          $periodo,
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Remover'),
              '#url' => \Drupal\Core\Url::fromRoute('garagem_reservas.disponibilidade_remover', [
                'node' => $node->id(),
                'bloqueio' => $bloqueio->id,
              ]),
              '#attributes' => ['class' => ['btn', 'btn-sm', 'btn-outline-danger']],
            ],
          ],
        ];
      }

      $form['bloqueios'] = [
        '#type' => 'table',
        '#caption' => $this->t('Datas bloqueadas'),
        '#header' => [$this->t('Período'), $this->t('Ação')],
        '#rows' => $rows,
        '#empty' => $this->t('Sem datas bloqueadas.'),
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $datas_str = trim($input['datas'] ?? '');

    if (empty($datas_str)) {
      $form_state->setErrorByName('datas', $this->t('Por favor selecione as datas.'));
      return;
    }

    $data_inicio_str = NULL;
    $data_fim_str = NULL;

    $separadores = [' - ', ' até ', ' to '];
    foreach ($separadores as $sep) {
      if (strpos($datas_str, $sep) !== FALSE) {
        $partes = explode($sep, $datas_str, 2);
        $data_inicio_str = trim($partes[0]);
        $data_fim_str = trim($partes[1]);
        break;
      }
    }

    if (!$data_inicio_str) {
      $data_inicio_str = $datas_str;
      $data_fim_str = $datas_str;
    }

    $inicio_ts = \DateTime::createFromFormat('d/m/Y', $data_inicio_str);
    $fim_ts = \DateTime::createFromFormat('d/m/Y', $data_fim_str);

    if (!$inicio_ts || !$fim_ts) {
      $form_state->setErrorByName('datas', $this->t('Datas inválidas.'));
      return;
    }

    $form_state->set('inicio_ts', $inicio_ts->setTime(0,0,0)->getTimestamp());
    $form_state->set('fim_ts', $fim_ts->setTime(23,59,59)->getTimestamp());
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('garagem_node');

    $this->database->insert('garagem_indisponibilidade')
      ->fields([
        'garagem_id' => $node->id(),
        'data_inicio' => $form_state->get('inicio_ts'),
        'data_fim' => $form_state->get('fim_ts'),
        'data_criacao' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Datas bloqueadas com sucesso.'));
    $form_state->setRedirect('garagem_reservas.garagem_disponibilidade', ['node' => $node->id()]);
  }

}

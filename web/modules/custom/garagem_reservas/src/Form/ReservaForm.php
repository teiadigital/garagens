<?php

namespace Drupal\garagem_reservas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Formulário de criação de reserva.
 */
class ReservaForm extends FormBase {

  public function getFormId() {
    return 'garagem_reservas_reserva_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node) {
      return [];
    }

    // Verificar que a garagem está publicada.
    if (!$node->isPublished()) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Não permitir reservar a própria garagem.
    $current_user = \Drupal::currentUser();
    if ($node->getOwnerId() == $current_user->id()) {
      $form['erro'] = [
        '#markup' => '<p>' . $this->t('Não pode reservar a sua própria garagem.') . '</p>',
      ];
      return $form;
    }

    // Rate limiting — máximo 5 reservas pendentes por utilizador.
    $pendentes = \Drupal::database()->select('garagem_reserva', 'gr')
      ->condition('user_id', $current_user->id())
      ->condition('estado', ['pendente', 'aprovado'], 'IN')
      ->countQuery()->execute()->fetchField();

    if ($pendentes >= 5) {
      $form['erro'] = [
        '#markup' => '<p>' . $this->t('Tem demasiadas reservas pendentes. Cancele ou aguarde aprovação antes de fazer nova reserva.') . '</p>',
      ];
      return $form;
    }

    $form_state->set('garagem_node', $node);

    $servico_preco = \Drupal::service('garagem_reservas.preco');
    $servico_disponibilidade = \Drupal::service('garagem_reservas.disponibilidade');

    $tipos_ativos = $servico_preco->getTiposAtivos($node);
    $aceita_renovacao = $servico_preco->aceitaRenovacaoAutomatica($node);
    $tem_reservas_futuras = $servico_disponibilidade->temReservasFuturas($node->id());

    if (empty($tipos_ativos)) {
      $form['erro'] = [
        '#markup' => '<p>' . $this->t('Esta garagem não tem preços configurados.') . '</p>',
      ];
      return $form;
    }

    // Ler parâmetros de URL para pré-preenchimento.
    $request = \Drupal::request();
    $url_tipo = $request->query->get('tipo', '');
    $url_data_inicio = $request->query->get('data_inicio', '');
    $url_data_fim = $request->query->get('data_fim', '');

    // Sanitizar.
    if (!in_array($url_tipo, array_keys($tipos_ativos))) {
      $url_tipo = '';
    }
    // Datas vêm no formato YYYY-MM-DD da página de pesquisa.
    if ($url_data_inicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $url_data_inicio)) {
      $url_data_inicio = '';
    }
    if ($url_data_fim && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $url_data_fim)) {
      $url_data_fim = '';
    }

    $tipo_inicial = $url_tipo ?: array_key_first($tipos_ativos);

    $form['#attached']['library'][] = 'garagem_reservas/flatpickr';
    $form['#attached']['drupalSettings']['garagemReservas'] = [
      'disponibilidadeUrl' => '/garagem/' . $node->id() . '/disponibilidade',
      'tiposAtivos' => $tipos_ativos,
      'aceitaRenovacao' => $aceita_renovacao,
      'defaultTipo' => $url_tipo,
      'defaultDataInicio' => $url_data_inicio,
      'defaultDataFim' => $url_data_fim,
    ];

    // Campo hidden para guardar o tipo selecionado — botões renderizados via JS.
    $form['tipo_preco'] = [
      '#type' => 'hidden',
      '#default_value' => $tipo_inicial,
      '#attributes' => ['id' => 'tipo-preco-value'],
    ];

    // Container para os botões de tipo.
    $labels_tipo = ['dia' => $this->t('Por dia'), 'mes' => $this->t('Por mês'), 'ano' => $this->t('Por ano')];
    $botoes = [];
    foreach ($tipos_ativos as $tipo => $preco) {
      $botoes[] = [
        'tipo' => $tipo,
        'label' => $labels_tipo[$tipo],
        'ativo' => $tipo === $tipo_inicial,
      ];
    }

    $form['tipo_preco_botoes'] = [
      '#type' => 'inline_template',
      '#template' => '
        <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;">
          {% for btn in botoes %}
            <button type="button" class="tipo-preco-btn"
              data-tipo="{{ btn.tipo }}"
              style="padding:10px 24px;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border:2px solid {{ btn.ativo ? "#111827" : "#e5e7eb" }};background:{{ btn.ativo ? "#111827" : "transparent" }};color:{{ btn.ativo ? "#fff" : "#374151" }};">
              {{ btn.label }}
            </button>
          {% endfor %}
        </div>',
      '#context' => ['botoes' => $botoes],
    ];

    // Campo de data — range para dia, single para mês/ano.
    // Converter YYYY-MM-DD para d/m/Y (formato que flatpickr usa no input).
    $isoParaDmY = function (string $iso): string {
      $dt = \DateTime::createFromFormat('Y-m-d', $iso);
      return $dt ? $dt->format('d/m/Y') : '';
    };
    $default_datas = '';
    if ($url_data_inicio) {
      $d1 = $isoParaDmY($url_data_inicio);
      $default_datas = ($tipo_inicial === 'dia' && $url_data_fim)
        ? $d1 . ' - ' . $isoParaDmY($url_data_fim)
        : $d1;
    }
    $form['datas'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Período de reserva'),
      '#required' => FALSE,
      '#default_value' => $default_datas,
      '#attributes' => [
        'class' => ['garagem-flatpickr-datas'],
        'placeholder' => $this->t('Selecione as datas'),
        'readonly' => 'readonly',
      ],
    ];

    // Info calculada automaticamente (para mês/ano).
    $form['info_periodo'] = [
      '#type' => 'item',
      '#markup' => '<div id="garagem-info-periodo" class="text-sm text-gray-600 mt-2"></div>',
    ];

    // Preço calculado — aparece no topo (order via JS).
    $form['preco_info'] = [
      '#type' => 'item',
      '#markup' => '<div id="preco-calculado"></div>',
      '#weight' => -20,
    ];

    // Renovação automática — só aparece para mês/ano e se a garagem aceitar
    // e não houver reservas futuras DEPOIS da data selecionada (controlado por JS).
    if ($aceita_renovacao) {
      $form['renovacao_automatica'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Renovação automática'),
        '#default_value' => FALSE,
        '#attributes' => [
          'id' => 'edit-renovacao-automatica',
          'class' => ['garagem-renovacao-automatica'],
        ],
        '#description' => $this->t('A reserva renova automaticamente no fim de cada período.'),
        // Inicialmente escondido — JS controla visibilidade.
        '#wrapper_attributes' => ['id' => 'renovacao-wrapper', 'style' => 'display:none'],
      ];
    }
    else {
      $form['renovacao_automatica'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
    }

$form['actions'] = ['#type' => 'actions', '#weight' => 90];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pedir reserva'),
      '#button_type' => 'primary',
      '#attributes' => ['style' => 'width:100%;'],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $tipo = $input['tipo_preco'] ?? $form_state->getValue('tipo_preco');
    $renovacao = (bool) $form_state->getValue('renovacao_automatica');

    $datas_str = trim($input['datas'] ?? '');

    if (empty($datas_str)) {
      $form_state->setErrorByName('datas', $this->t('Por favor selecione as datas.'));
      return;
    }

    $inicio_ts = NULL;
    $fim_ts = NULL;

    if ($tipo === 'dia') {
      // Modo range — separar início e fim.
      $separadores = [' - ', ' até ', ' to '];
      foreach ($separadores as $sep) {
        if (strpos($datas_str, $sep) !== FALSE) {
          $partes = explode($sep, $datas_str, 2);
          $inicio_ts = $this->parseFlatpickrDate(trim($partes[0]));
          $fim_ts = $this->parseFlatpickrDate(trim($partes[1]));
          break;
        }
      }
      if (!$inicio_ts || !$fim_ts) {
        $form_state->setErrorByName('datas', $this->t('Por favor selecione a data de início e de fim.'));
        return;
      }
      if ($fim_ts <= $inicio_ts) {
        $form_state->setErrorByName('datas', $this->t('A data de fim deve ser posterior à data de início.'));
        return;
      }
      // Verificar mínimo de dias definido na garagem.
      $node = $form_state->get('garagem_node');
      $min_dias = (int) ($node->get('field_min_dias_reserva')->value ?? 1);
      if ($min_dias > 1) {
        $num_dias = (int) round(($fim_ts - $inicio_ts) / 86400);
        if ($num_dias < $min_dias) {
          $form_state->setErrorByName('datas', $this->t('O mínimo de dias para esta garagem é @min dias.', ['@min' => $min_dias]));
          return;
        }
      }
    }
    else {
      // Modo mês/ano — só data de início.
      $inicio_ts = $this->parseFlatpickrDate($datas_str);
      if (!$inicio_ts) {
        $form_state->setErrorByName('datas', $this->t('Data de início inválida.'));
        return;
      }
      // Calcular fim automaticamente sem overflow de mês.
      $fim_dt = (new \DateTime())->setTimestamp($inicio_ts);
      if ($tipo === 'ano') {
        $fim_dt->modify('+1 year');
      } else {
        $dia_inicio = (int) $fim_dt->format('j');
        $fim_dt->modify('+1 month');
        // Se houve overflow (ex: 31 maio → 1 julho), recuar para o último dia do mês correto.
        if ((int) $fim_dt->format('j') !== $dia_inicio) {
          $fim_dt->modify('last day of last month');
        }
      }
      $fim_ts = $fim_dt->getTimestamp();
    }

    // Verificar disponibilidade.
    $node = $form_state->get('garagem_node');
    $servico = \Drupal::service('garagem_reservas.disponibilidade');

    // Se renovação automática, fim é NULL (bloqueia tudo).
    $fim_para_verificar = $renovacao ? NULL : $fim_ts;
    if (!$servico->verificarDisponibilidade($node->id(), $inicio_ts, $fim_para_verificar)) {
      $form_state->setErrorByName('data_inicio', $this->t('A garagem não está disponível para o período selecionado.'));
      return;
    }

    $form_state->set('inicio_ts', $inicio_ts);
    $form_state->set('fim_ts', $renovacao ? NULL : $fim_ts);
  }

  protected function parseFlatpickrDate(string $date_str): ?int {
    $date = \DateTime::createFromFormat('d/m/Y', trim($date_str));
    if (!$date) return NULL;
    $date->setTime(0, 0, 0);
    return $date->getTimestamp();
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('garagem_node');
    $tipo = $form_state->getValue('tipo_preco');
    $renovacao = (bool) $form_state->getValue('renovacao_automatica');
    $notas = $form_state->getValue('notas');
    // Sanitizar notas — remover HTML e limitar tamanho.
    $notas = $notas ? mb_substr(strip_tags((string) $notas), 0, 1000) : NULL;
    $inicio_ts = $form_state->get('inicio_ts');
    $fim_ts = $form_state->get('fim_ts');

    // Calcular preço.
    $fim_para_preco = $fim_ts ?? strtotime('+1 ' . ($tipo === 'ano' ? 'year' : 'month'), $inicio_ts);
    $servico_preco = \Drupal::service('garagem_reservas.preco');
    $calculo = $servico_preco->calcularPreco($node, $tipo, $inicio_ts, $fim_para_preco);

    // Taxa da plataforma.
    $config = \Drupal::config('garagem_reservas.settings');
    $taxa_fixa = $config->get('taxa_fixa');
    $percentagem = $config->get('percentagem_plataforma');
    $taxa_plataforma = $taxa_fixa + ($calculo['preco_total'] * $percentagem / 100);

    $database = \Drupal::database();
    $id = $database->insert('garagem_reserva')
      ->fields([
        'garagem_id' => $node->id(),
        'user_id' => \Drupal::currentUser()->id(),
        'proprietario_id' => $node->getOwnerId(),
        'data_inicio' => $inicio_ts,
        'data_fim' => $fim_ts,
        'renovacao_automatica' => $renovacao ? 1 : 0,
        'tipo_preco' => $calculo['tipo_preco'],
        'preco_total' => $calculo['preco_total'],
        'taxa_plataforma' => $taxa_plataforma,
        'estado' => 'pendente',
        'data_criacao' => \Drupal::time()->getRequestTime(),
        'notas' => $notas,
      ])
      ->execute();

    \Drupal::service('garagem_reservas.notificacao')->reservaCriada($id);

    $this->messenger()->addStatus($this->t('O seu pedido de reserva foi submetido com sucesso. Aguarde a aprovação do proprietário.'));
    $form_state->setRedirect('garagem_reservas.reserva_view', ['reserva' => $id]);
  }

}

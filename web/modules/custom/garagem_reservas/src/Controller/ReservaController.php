<?php

namespace Drupal\garagem_reservas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller de reservas.
 */
class ReservaController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Ver detalhes da reserva.
   */
  public function view(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $current_user = \Drupal::currentUser();
    $is_proprietario = $current_user->id() == $reserva_data->proprietario_id;
    $is_user = $current_user->id() == $reserva_data->user_id;

    // Verificar acesso.
    if (!$is_proprietario && !$is_user && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $garagem = $this->entityTypeManager()->getStorage('node')->load($reserva_data->garagem_id);
    $user = $this->entityTypeManager()->getStorage('user')->load($reserva_data->user_id);

    $config = \Drupal::config('garagem_reservas.settings');
    $taxa_fixa = $config->get('taxa_fixa');
    $percentagem = $config->get('percentagem_plataforma');
    $taxa_percentual = $reserva_data->preco_total * $percentagem / 100;

    return [
      '#theme' => 'garagem_reserva',
      '#reserva' => $reserva_data,
      '#garagem' => $garagem,
      '#user' => $user,
      '#preco_total' => $reserva_data->preco_total,
      '#taxa_plataforma' => $reserva_data->taxa_plataforma,
      '#taxa_fixa' => $taxa_fixa,
      '#taxa_percentual' => $taxa_percentual,
      '#is_proprietario' => $is_proprietario,
      '#is_user' => $is_user,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Cancelar reserva diretamente pelo utilizador (sem página de confirmação).
   */
  public function cancelarUser(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $current_user = \Drupal::currentUser();
    if ($current_user->id() != $reserva_data->user_id) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $estados_cancelaveis = ['pendente', 'aprovado', 'aguarda_pagamento', 'pago'];
    if (!in_array($reserva_data->estado, $estados_cancelaveis)) {
      $this->messenger()->addError($this->t('Não é possível cancelar esta reserva no estado atual.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'cancelado'])
      ->condition('id', $reserva)
      ->execute();

    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaCancelada($reserva);
    }
    catch (\Exception $e) {
      \Drupal::logger('garagem_reservas')->warning(
        'Erro ao notificar cancelamento da reserva #@id: @msg',
        ['@id' => $reserva, '@msg' => $e->getMessage()]
      );
    }

    $this->messenger()->addStatus($this->t('Reserva cancelada com sucesso.'));
    return $this->redirect('garagem_reservas.lista_user');
  }

  /**
   * Cancelar reserva pelo proprietário.
   */
  public function cancelarProprietario(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $current_user = \Drupal::currentUser();
    if ($current_user->id() != $reserva_data->proprietario_id && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Proprietário pode cancelar em qualquer estado exceto já cancelado/rejeitado/expirado.
    $estados_cancelaveis = ['pendente', 'aprovado', 'aguarda_pagamento', 'pago'];
    if (!in_array($reserva_data->estado, $estados_cancelaveis)) {
      $this->messenger()->addError($this->t('Não é possível cancelar esta reserva no estado atual.'));
      return $this->redirect('garagem_reservas.lista_garagem', ['node' => $reserva_data->garagem_id]);
    }

    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'cancelado'])
      ->condition('id', $reserva)
      ->execute();

    // Notificar o arrendatário.
    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaCancelada($reserva);
    }
    catch (\Exception $e) {
      \Drupal::logger('garagem_reservas')->warning(
        'Erro ao notificar cancelamento da reserva #@id: @msg',
        ['@id' => $reserva, '@msg' => $e->getMessage()]
      );
    }

    $this->messenger()->addStatus($this->t('Reserva #@id cancelada com sucesso.', ['@id' => $reserva]));
    return $this->redirect('garagem_reservas.lista_garagem', ['node' => $reserva_data->garagem_id]);
  }

  /**
   * Aprovar reserva.
   */
  public function aprovar(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data || $reserva_data->estado !== 'pendente') {
      $this->messenger()->addError($this->t('Não é possível aprovar esta reserva.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    // Verificar que é o proprietário.
    $current_user = \Drupal::currentUser();
    if ($current_user->id() != $reserva_data->proprietario_id && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $this->database->update('garagem_reserva')
      ->fields([
        'estado' => 'aprovado',
        'data_aprovacao' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $reserva)
      ->execute();

    // Criar order Commerce para pagamento.
    $order = \Drupal::service('garagem_reservas.commerce')->criarOrderReserva($reserva);

    if ($order) {
      \Drupal::logger('garagem_reservas')->info(
        'Commerce Order #@order criada para reserva #@reserva.',
        ['@order' => $order->id(), '@reserva' => $reserva]
      );
    }

    \Drupal::service('garagem_reservas.notificacao')->reservaAprovada($reserva);

    $this->messenger()->addStatus($this->t('Reserva aprovada com sucesso. O utilizador foi notificado para efetuar o pagamento.'));
    return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
  }

  /**
   * Rejeitar reserva.
   */
  public function rejeitar(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data || $reserva_data->estado !== 'pendente') {
      $this->messenger()->addError($this->t('Não é possível rejeitar esta reserva.'));
      return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
    }

    $this->database->update('garagem_reserva')
      ->fields(['estado' => 'rejeitado'])
      ->condition('id', $reserva)
      ->execute();

    \Drupal::service('garagem_reservas.notificacao')->reservaRejeitada($reserva);

    $this->messenger()->addStatus($this->t('Reserva rejeitada.'));
    return $this->redirect('garagem_reservas.reserva_view', ['reserva' => $reserva]);
  }

  /**
   * Gerar PDF da reserva.
   */
  public function pdf(int $reserva) {
    $reserva_data = $this->getReserva($reserva);

    if (!$reserva_data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Verificar acesso.
    $current_user = \Drupal::currentUser();
    $is_proprietario = $current_user->id() == $reserva_data->proprietario_id;
    $is_user = $current_user->id() == $reserva_data->user_id;
    if (!$is_proprietario && !$is_user && !$current_user->hasPermission('administer garagem reservas')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $garagem = $this->entityTypeManager()->getStorage('node')->load($reserva_data->garagem_id);
    $user = $this->entityTypeManager()->getStorage('user')->load($reserva_data->user_id);
    $proprietario = $this->entityTypeManager()->getStorage('user')->load($reserva_data->proprietario_id);

    // Morada de faturação do Commerce Order.
    $billing_address = NULL;
    if ($reserva_data->commerce_order_id) {
      $order = $this->entityTypeManager()->getStorage('commerce_order')->load($reserva_data->commerce_order_id);
      if ($order) {
        $billing_profile = $order->getBillingProfile();
        if ($billing_profile) {
          $billing_address = $billing_profile->get('address')->first()->getValue();
        }
      }
    }

    // Morada da garagem.
    $garagem_address = NULL;
    if ($garagem && $garagem->hasField('field_localidade') && !$garagem->get('field_localidade')->isEmpty()) {
      $garagem_address = $garagem->get('field_localidade')->first()->getValue();
    }

    // Dados do proprietário.
    $proprietario_nif = NULL;
    $proprietario_morada = NULL;
    if ($proprietario) {
      if ($proprietario->hasField('field_nif') && !$proprietario->get('field_nif')->isEmpty()) {
        $proprietario_nif = $proprietario->get('field_nif')->value;
      }
      if ($proprietario->hasField('field_morada') && !$proprietario->get('field_morada')->isEmpty()) {
        $proprietario_morada = $proprietario->get('field_morada')->first()->getValue();
      }
    }

    // Dados do arrendatário.
    $user_nif = NULL;
    $user_morada = NULL;
    if ($user) {
      if ($user->hasField('field_nif') && !$user->get('field_nif')->isEmpty()) {
        $user_nif = $user->get('field_nif')->value;
      }
      if ($user->hasField('field_morada') && !$user->get('field_morada')->isEmpty()) {
        $user_morada = $user->get('field_morada')->first()->getValue();
      }
    }

    // Tipo de preço usado.
    $tipo_preco = $reserva_data->tipo_preco ?? 'dia';

    // Carregar node do contrato template.
    $config = \Drupal::config('garagem_reservas.settings');
    $nid = $config->get('contrato_template_nid') ?? 236;
    $template_node = $this->entityTypeManager()->getStorage('node')->load($nid);

    // Construir mapa de tokens.
    $garagem_morada_str = '___________________________';
    if ($garagem_address) {
      $partes = array_filter([
        $garagem_address['address_line1'] ?? '',
        $garagem_address['address_line2'] ?? '',
        ($garagem_address['postal_code'] ?? '') . ' ' . ($garagem_address['locality'] ?? ''),
        $garagem_address['administrative_area'] ?? '',
      ]);
      $garagem_morada_str = implode(', ', $partes);
    }

    $proprietario_morada_str = '___________________________';
    if ($proprietario_morada) {
      $proprietario_morada_str = ($proprietario_morada['address_line1'] ?? '') . ', ' .
        ($proprietario_morada['postal_code'] ?? '') . ' ' . ($proprietario_morada['locality'] ?? '');
    }

    $user_morada_str = '___________________________';
    if ($user_morada) {
      $user_morada_str = ($user_morada['address_line1'] ?? '') . ', ' .
        ($user_morada['postal_code'] ?? '') . ' ' . ($user_morada['locality'] ?? '');
    } elseif ($billing_address) {
      $user_morada_str = ($billing_address['address_line1'] ?? '') . ', ' .
        ($billing_address['postal_code'] ?? '') . ' ' . ($billing_address['locality'] ?? '');
    }

    $arrendatario_nome = $billing_address
      ? (($billing_address['given_name'] ?? '') . ' ' . ($billing_address['family_name'] ?? ''))
      : ($user->hasField('field_nome') ? $user->get('field_nome')->value : $user->name->value);

    $renovacao_automatica = (int) $reserva_data->renovacao_automatica === 1;

    // Checkboxes de prazo.
    $check_on = '[X]';
    $check_off = '[  ]';
    $checkbox_prazo = (!$renovacao_automatica ? $check_on : $check_off) . ' Prazo determinado até ' .
      (!$renovacao_automatica && $reserva_data->data_fim ? date('d/m/Y', $reserva_data->data_fim) : '___/___/______') .
      '<br>' .
      ($renovacao_automatica ? $check_on : $check_off) . ' Renovação automática';

    // Preços da garagem.
    $preco_dia_garagem = '';
    $preco_mes_garagem = '';
    if ($garagem) {
      if ($garagem->hasField('field_preco_dia_ativo') && $garagem->get('field_preco_dia_ativo')->value
        && $garagem->hasField('field_preco_dia') && !$garagem->get('field_preco_dia')->isEmpty()) {
        $preco_dia_garagem = number_format((float) $garagem->get('field_preco_dia')->value, 2) . '€';
      }
      if ($garagem->hasField('field_preco_mes_ativo') && $garagem->get('field_preco_mes_ativo')->value
        && $garagem->hasField('field_preco_mes') && !$garagem->get('field_preco_mes')->isEmpty()) {
        $preco_mes_garagem = number_format((float) $garagem->get('field_preco_mes')->value, 2) . '€';
      }
    }

    $linhas_preco = [];
    if ($preco_mes_garagem) {
      $linhas_preco[] = $preco_mes_garagem . ' por mês';
    }
    if ($preco_dia_garagem) {
      $linhas_preco[] = $preco_dia_garagem . ' por dia';
    }
    $checkbox_preco = implode('<br>', $linhas_preco);

    $tokens = [
      '[garagem:nome]'           => $garagem ? $garagem->getTitle() : '___',
      '[garagem:morada]'         => $garagem_morada_str,
      '[proprietario:nome]'      => $proprietario->hasField('field_nome') ? $proprietario->get('field_nome')->value : $proprietario->name->value,
      '[proprietario:nif]'       => $proprietario_nif ?? '___________',
      '[proprietario:morada]'    => $proprietario_morada_str,
      '[arrendatario:nome]'      => $arrendatario_nome,
      '[arrendatario:nif]'       => $user_nif ?? '___________',
      '[arrendatario:morada]'    => $user_morada_str,
      '[reserva:data_inicio]'    => date('d/m/Y', $reserva_data->data_inicio),
      '[reserva:data_fim]'       => $reserva_data->data_fim ? date('d/m/Y', $reserva_data->data_fim) : '___/___/______',
      '[reserva:preco]'          => number_format($reserva_data->preco_total, 2) . '€',
      '[reserva:taxa]'           => number_format($reserva_data->taxa_plataforma, 2) . '€',
      '[reserva:tipo_preco]'     => $tipo_preco === 'mes' ? 'mês' : 'dia',
      '[contrato:checkbox_prazo]'=> $checkbox_prazo,
      '[contrato:checkbox_preco]'=> $checkbox_preco,
    ];

    // Carregar cláusulas do node template.
    $clausulas = [];
    $intro = '';
    $feito_em = '';
    $contrato_titulo = 'CONTRATO DE ARRENDAMENTO DE ESPAÇO (GARAGEM/ARRUMOS)';

    if ($template_node) {
      $contrato_titulo = $template_node->getTitle();

      if ($template_node->hasField('field_contrato_intro') && !$template_node->get('field_contrato_intro')->isEmpty()) {
        $intro = strtr($template_node->get('field_contrato_intro')->value, $tokens);
      }
      if ($template_node->hasField('field_contrato_feito_em') && !$template_node->get('field_contrato_feito_em')->isEmpty()) {
        $feito_em = strtr($template_node->get('field_contrato_feito_em')->value, $tokens);
      }

      if ($template_node->hasField('field_clausulas')) {
        foreach ($template_node->get('field_clausulas') as $item) {
          $paragraph = $item->entity;
          if (!$paragraph) continue;

          $titulo = '';
          $corpo = '';

          if ($paragraph->hasField('field_titulo') && !$paragraph->get('field_titulo')->isEmpty()) {
            $titulo = strtr($paragraph->get('field_titulo')->value, $tokens);
          }
          if ($paragraph->hasField('field_corpo') && !$paragraph->get('field_corpo')->isEmpty()) {
            $corpo = strtr($paragraph->get('field_corpo')->value, $tokens);
            // Converter quebras de linha em <br> se não for HTML.
            if (strpos($corpo, '<') === FALSE) {
              $corpo = nl2br(htmlspecialchars($corpo));
            }
          }

          $clausulas[] = ['titulo' => $titulo, 'corpo' => $corpo];
        }
      }
    }

    $build = [
      '#theme' => 'garagem_reserva_pdf',
      '#reserva' => $reserva_data,
      '#garagem' => $garagem,
      '#user' => $user,
      '#proprietario' => $proprietario,
      '#preco_total' => $reserva_data->preco_total,
      '#taxa_plataforma' => $reserva_data->taxa_plataforma,
      '#billing_address' => $billing_address,
      '#garagem_address' => $garagem_address,
      '#proprietario_nif' => $proprietario_nif,
      '#proprietario_morada' => $proprietario_morada,
      '#user_nif' => $user_nif,
      '#user_morada' => $user_morada,
      '#tipo_preco' => $tipo_preco,
      '#contrato_titulo' => $contrato_titulo,
      '#contrato_intro' => $intro,
      '#contrato_feito_em' => $feito_em,
      '#clausulas' => $clausulas,
      '#arrendatario_nome' => $arrendatario_nome,
      '#data_geracao' => date('d/m/Y'),
    ];

    $html = (string) \Drupal::service('renderer')->renderRoot($build);

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdf_content = $dompdf->output();
    $filename = 'contrato-reserva-' . $reserva . '-' . date('Ymd') . '.pdf';

    $response = new \Symfony\Component\HttpFoundation\Response($pdf_content);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Content-Length', strlen($pdf_content));

    return $response;
  }

  /**
   * Lista de reservas do proprietário.
   */
  public function listaDashboard() {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();

    $reservas_user = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('user_id', $uid)
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    $reservas_proprietario = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('proprietario_id', $uid)
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    $node_storage = $this->entityTypeManager()->getStorage('node');
    $user_storage = $this->entityTypeManager()->getStorage('user');

    $this->enriquecerReservas($reservas_user, $node_storage, $user_storage);
    $this->enriquecerReservas($reservas_proprietario, $node_storage, $user_storage);

    return [
      '#theme' => 'garagem_reservas_lista',
      '#reservas_user' => $reservas_user,
      '#reservas_proprietario' => $reservas_proprietario,
      '#tipo' => 'dashboard',
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

  /**
   * Enriquece reservas com dados de garagem e utilizador.
   */
  protected function enriquecerReservas(array &$reservas, $node_storage, $user_storage): void {
    foreach ($reservas as $reserva) {
      $garagem = $node_storage->load($reserva->garagem_id);
      $user = $user_storage->load($reserva->user_id);
      $reserva->garagem_titulo = $garagem ? $garagem->getTitle() : '—';
      $reserva->user_nome = $user ? $user->getDisplayName() : '—';
      $reserva->user_email = $user ? $user->getEmail() : '—';
    }
  }

  /**
   * Página de notificações.
   */
  public function notificacoes() {
    $current_user = \Drupal::currentUser();

    $message_storage = \Drupal::entityTypeManager()->getStorage('message');
    $ids = $message_storage->getQuery()
      ->condition('uid', $current_user->id())
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $messages = $message_storage->loadMultiple($ids);

    $items = [];
    foreach ($messages as $message) {
      $texts = $message->getText();
      $text_rendered = '';
      foreach ($texts as $text) {
        if (is_array($text)) {
          $text_rendered .= \Drupal::service('renderer')->renderRoot($text);
        } else {
          $text_rendered .= $text;
        }
      }

      $items[] = [
        'bundle' => $message->bundle(),
        'created' => $message->getCreatedTime(),
        'text' => $text_rendered,
      ];
    }

    return [
      '#theme' => 'garagem_reservas_notificacoes',
      '#items' => $items,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Lista de reservas de uma garagem específica (para o proprietário).
   */
  public function listaGaragem(int $node) {
    $current_user = \Drupal::currentUser();
    $garagem = $this->entityTypeManager()->getStorage('node')->load($node);

    if (!$garagem || ($garagem->getOwnerId() != $current_user->id() && !$current_user->hasPermission('administer garagem reservas'))) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $reservas = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('garagem_id', $node)
      ->orderBy('data_criacao', 'DESC')
      ->execute()
      ->fetchAll();

    $node_storage = $this->entityTypeManager()->getStorage('node');
    $user_storage = $this->entityTypeManager()->getStorage('user');
    $this->enriquecerReservas($reservas, $node_storage, $user_storage);

    return [
      '#theme' => 'garagem_reservas_lista',
      '#reservas' => $reservas,
      '#tipo' => 'proprietario',
      '#titulo' => $this->t('Reservas de "@garagem"', ['@garagem' => $garagem->getTitle()]),
      '#attached' => ['library' => ['garagem_reservas/flatpickr']],
    ];
  }

  /**
   * Remover bloqueio de disponibilidade.
   */
  public function removerBloqueio(int $node, int $bloqueio) {
    $current_user = \Drupal::currentUser();

    // Verificar que o bloqueio pertence a uma garagem do utilizador.
    $garagem = $this->entityTypeManager()->getStorage('node')->load($node);
    if (!$garagem || ($garagem->getOwnerId() != $current_user->id() && !$current_user->hasPermission('administer garagem reservas'))) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $this->database->delete('garagem_indisponibilidade')
      ->condition('id', $bloqueio)
      ->condition('garagem_id', $node)
      ->execute();

    $this->messenger()->addStatus($this->t('Bloqueio removido com sucesso.'));
    return $this->redirect('garagem_reservas.garagem_disponibilidade', ['node' => $node]);
  }

  /**
   * Endpoint de disponibilidade (JSON para calendário).
   */
  public function disponibilidade(int $node) {
    $servico = \Drupal::service('garagem_reservas.disponibilidade');
    $datas = $servico->getDatasOcupadas($node);
    $tem_futuras = $servico->temReservasFuturas($node);

    return new JsonResponse([
      'datas' => $datas,
      'tem_reservas_futuras' => $tem_futuras,
    ]);
  }

  /**
   * Obtém dados da reserva.
   */
  protected function getReserva(int $reserva_id): ?object {
    return $this->database->select('garagem_reserva', 'gr')
      ->fields('gr')
      ->condition('id', $reserva_id)
      ->execute()
      ->fetchObject() ?: NULL;
  }

}

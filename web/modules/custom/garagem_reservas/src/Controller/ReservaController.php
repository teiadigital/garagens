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
   * Formulário de reserva com informações da garagem.
   */
  public function add(\Drupal\node\NodeInterface $node) {
    $form = \Drupal::formBuilder()->getForm('\Drupal\garagem_reservas\Form\ReservaForm', $node);

    $view_builder = $this->entityTypeManager()->getViewBuilder('node');

    // Localidade.
    $localidade = [];
    if ($node->hasField('field_localidade') && !$node->get('field_localidade')->isEmpty()) {
      $localidade = $view_builder->viewField($node->get('field_localidade'), ['label' => 'above']);
    }

    // Descrição.
    $descricao = [];
    if ($node->hasField('field_descricao') && !$node->get('field_descricao')->isEmpty()) {
      $descricao = $view_builder->viewField($node->get('field_descricao'), ['label' => 'above']);
    }

    // Área individual.
    $area = [];
    if ($node->hasField('field_area_individual') && !$node->get('field_area_individual')->isEmpty()) {
      $area = $view_builder->viewField($node->get('field_area_individual'), ['label' => 'above']);
    }

    // Fotos.
    $fotos = [];
    if ($node->hasField('field_fotos') && !$node->get('field_fotos')->isEmpty()) {
      $fotos = $view_builder->viewField($node->get('field_fotos'), 'full');
    }

    // Primeira foto apenas.
    $primeira_foto = [];
    if ($node->hasField('field_fotos') && !$node->get('field_fotos')->isEmpty()) {
      $foto_item = $node->get('field_fotos')->first();
      if ($foto_item) {
        $media = $foto_item->entity;
        if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
          $file = $media->get('field_media_image')->entity;
          if ($file) {
            $primeira_foto = [
              '#theme' => 'image_style',
              '#style_name' => 'large',
              '#uri' => $file->getFileUri(),
              '#attributes' => ['style' => 'width:100%;height:100%;object-fit:cover;'],
            ];
          }
        }
      }
    }

    // Localidade simplificada.
    $localidade = [];
    if ($node->hasField('field_localidade') && !$node->get('field_localidade')->isEmpty()) {
      $loc = $node->get('field_localidade')->first();
      $localidade = [
        'locality' => $loc->locality,
        'administrative_area' => $loc->administrative_area,
        'country' => $loc->country_code,
      ];
    }

    return [
      '#theme' => 'garagem_reserva_form',
      '#form' => $form,
      '#garagem' => $node,
      '#garagem_titulo' => $node->getTitle(),
      '#garagem_primeira_foto' => $primeira_foto,
      '#garagem_localidade' => $localidade,
      '#garagem_descricao' => $descricao,
      '#garagem_fotos' => $fotos,
      '#garagem_area' => $area,
    ];
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
    $proprietario = $this->entityTypeManager()->getStorage('user')->load($reserva_data->proprietario_id);

    $config = \Drupal::config('garagem_reservas.settings');
    $taxa_fixa = $config->get('taxa_fixa');
    $percentagem = $config->get('percentagem_plataforma');
    $taxa_percentual = $reserva_data->preco_total * $percentagem / 100;

    // Contactos do proprietário — só visíveis para o arrendatário após pagamento.
    $proprietario_contacto = NULL;
    if ($is_user && $reserva_data->estado === 'pago' && $proprietario) {
      $proprietario_contacto = [
        'nome' => $proprietario->hasField('field_nome') && !$proprietario->get('field_nome')->isEmpty()
          ? $proprietario->get('field_nome')->value
          : $proprietario->getDisplayName(),
        'email' => $proprietario->getEmail(),
        'telefone' => $proprietario->hasField('field_telefone') && !$proprietario->get('field_telefone')->isEmpty()
          ? $proprietario->get('field_telefone')->value
          : NULL,
        'telemovel' => $proprietario->hasField('field_telemovel') && !$proprietario->get('field_telemovel')->isEmpty()
          ? $proprietario->get('field_telemovel')->value
          : NULL,
      ];
    }

    // Contactos do arrendatário — só visíveis para o proprietário após pagamento.
    $arrendatario_contacto = NULL;
    if ($is_proprietario && $reserva_data->estado === 'pago' && $user) {
      $arrendatario_contacto = [
        'nome' => $user->hasField('field_nome') && !$user->get('field_nome')->isEmpty()
          ? $user->get('field_nome')->value
          : $user->getDisplayName(),
        'email' => $user->getEmail(),
        'telefone' => $user->hasField('field_telefone') && !$user->get('field_telefone')->isEmpty()
          ? $user->get('field_telefone')->value
          : NULL,
        'telemovel' => $user->hasField('field_telemovel') && !$user->get('field_telemovel')->isEmpty()
          ? $user->get('field_telemovel')->value
          : NULL,
      ];
    }

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
      '#proprietario_contacto' => $proprietario_contacto,
      '#arrendatario_contacto' => $arrendatario_contacto,
      '#cache' => ['max-age' => 0],
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

    $motivo = \Drupal::request()->query->get('motivo', '');
    $fields = ['estado' => 'cancelado'];
    if (!empty($motivo)) {
      $fields['motivo'] = substr(strip_tags($motivo), 0, 1000);
    }
    $this->database->update('garagem_reserva')
      ->fields($fields)
      ->condition('id', $reserva)
      ->execute();

    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaCanceladaPeloUser($reserva);
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

    $motivo = \Drupal::request()->query->get('motivo', '');
    $fields = ['estado' => 'cancelado'];
    if (!empty($motivo)) {
      $fields['motivo'] = substr(strip_tags($motivo), 0, 1000);
    }
    $this->database->update('garagem_reserva')
      ->fields($fields)
      ->condition('id', $reserva)
      ->execute();

    // Notificar o arrendatário e confirmar ao proprietário.
    try {
      \Drupal::service('garagem_reservas.notificacao')->reservaCanceladaPeloProprietario($reserva);
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

    $motivo = \Drupal::request()->query->get('motivo', '');
    $fields = ['estado' => 'rejeitado'];
    if (!empty($motivo)) {
      $fields['motivo'] = substr(strip_tags($motivo), 0, 1000);
    }
    $this->database->update('garagem_reserva')
      ->fields($fields)
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

    // PDF só disponível para reservas pagas.
    if ($reserva_data->estado !== 'pago' && !$current_user->hasPermission('administer garagem reservas')) {
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
      '#cache' => ['max-age' => 0],
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
    }
  }


  /**
   * Lista de encomendas do utilizador.
   */
  public function listaEncomendas() {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();

    $order_storage = $this->entityTypeManager()->getStorage('commerce_order');
    $ids = $order_storage->getQuery()
      ->condition('uid', $uid)
      ->condition('state', 'draft', '!=')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $orders = $order_storage->loadMultiple($ids);

    $items = [];
    foreach ($orders as $order) {
      $payment_info = $this->getPaymentInfo($order);

      // Reserva associada.
      $reserva = \Drupal::database()->select('garagem_reserva', 'gr')
        ->fields('gr', ['id', 'garagem_id'])
        ->condition('commerce_order_id', $order->id())
        ->execute()
        ->fetchObject();

      $garagem_titulo = '';
      if ($reserva) {
        $node = $this->entityTypeManager()->getStorage('node')->load($reserva->garagem_id);
        $garagem_titulo = $node ? $node->getTitle() : '';
      }

      $items[] = [
        'id' => $order->id(),
        'numero' => $order->getOrderNumber() ?: '#' . $order->id(),
        'estado' => $order->getState()->getId(),
        'estado_label' => $order->getState()->getLabel(),
        'total' => $order->getTotalPrice() ? number_format((float)$order->getTotalPrice()->getNumber(), 2, ',', '.') . ' €' : '—',
        'data' => \Drupal::service('date.formatter')->format($order->getCreatedTime(), 'custom', 'd/m/Y H:i'),
        'garagem' => $garagem_titulo,
        'reserva_id' => $reserva ? $reserva->id : NULL,
        'gateway' => $payment_info['gateway'] ?? '',
        'payment_estado' => $payment_info['estado'] ?? '',
      ];
    }

    return [
      '#theme' => 'garagem_encomendas_lista',
      '#items' => $items,
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Detalhe de uma encomenda.
   */
  public function detalheEncomenda(int $order_id) {
    $current_user = \Drupal::currentUser();

    $order_storage = $this->entityTypeManager()->getStorage('commerce_order');
    $order = $order_storage->load($order_id);

    if (!$order) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Só o dono pode ver.
    if ($order->getCustomerId() != $current_user->id() && !$current_user->hasPermission('administer commerce_order')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Itens da encomenda.
    $order_items = [];
    foreach ($order->getItems() as $item) {
      $order_items[] = [
        'titulo' => $item->getTitle(),
        'quantidade' => $item->getQuantity(),
        'preco_unit' => number_format((float)$item->getUnitPrice()->getNumber(), 2, ',', '.') . ' €',
        'total' => number_format((float)$item->getTotalPrice()->getNumber(), 2, ',', '.') . ' €',
      ];
    }

    // Morada de faturação.
    $billing_address = NULL;
    $billing_profile = $order->getBillingProfile();
    if ($billing_profile) {
      $addr = $billing_profile->get('address')->first();
      if ($addr) $billing_address = $addr->getValue();
    }

    // Pagamento.
    $payment_info = $this->getPaymentInfo($order);

    // Reserva associada.
    $reserva = \Drupal::database()->select('garagem_reserva', 'gr')
      ->fields('gr', ['id', 'garagem_id', 'data_inicio', 'data_fim', 'estado'])
      ->condition('commerce_order_id', $order_id)
      ->execute()
      ->fetchObject();

    $garagem_titulo = '';
    if ($reserva) {
      $node = $this->entityTypeManager()->getStorage('node')->load($reserva->garagem_id);
      $garagem_titulo = $node ? $node->getTitle() : '';
    }

    return [
      '#theme' => 'garagem_encomenda_detalhe',
      '#order' => [
        'id' => $order->id(),
        'numero' => $order->getOrderNumber() ?: '#' . $order->id(),
        'estado' => $order->getState()->getId(),
        'estado_label' => $order->getState()->getLabel(),
        'total' => $order->getTotalPrice() ? number_format((float)$order->getTotalPrice()->getNumber(), 2, ',', '.') . ' €' : '—',
        'data' => \Drupal::service('date.formatter')->format($order->getCreatedTime(), 'custom', 'd/m/Y H:i'),
        'email' => $order->getEmail(),
      ],
      '#items' => $order_items,
      '#billing_address' => $billing_address,
      '#payment' => $payment_info,
      '#reserva' => $reserva ? [
        'id' => $reserva->id,
        'garagem' => $garagem_titulo,
        'data_inicio' => $reserva->data_inicio ? date('d/m/Y', $reserva->data_inicio) : '—',
        'data_fim' => $reserva->data_fim ? date('d/m/Y', $reserva->data_fim) : '—',
        'estado' => $reserva->estado,
      ] : NULL,
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Helper: obter informação de pagamento de uma order.
   */
  protected function getPaymentInfo($order): array {
    $payments = $this->entityTypeManager()->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    if (empty($payments)) return [];

    $payment = reset($payments);
    $gateway_id = $payment->getPaymentGatewayId();
    $remote_id = $payment->getRemoteId();
    $estado = $payment->getState()->getId();

    $info = [
      'gateway' => $gateway_id,
      'estado' => $estado,
      'remote_id' => $remote_id,
      'entidade' => NULL,
      'referencia' => NULL,
      'tipo' => 'outro',
    ];

    // Detectar Multibanco — remote_id é a referência, entidade vem da config do gateway.
    $gateway_storage = $this->entityTypeManager()->getStorage('commerce_payment_gateway');
    $gateway = $gateway_storage->load($gateway_id);
    if ($gateway) {
      $plugin = $gateway->getPlugin();
      $plugin_id = $gateway->getPluginId();
      $gateway_machine_name = $gateway->id();

      if ($plugin_id === 'ifthenpay_multibanco') {
        $info['tipo'] = 'multibanco';
        // O remote_id é JSON: {"entity":"12537","reference":"456003808","expiry_date":"..."}
        $decoded = json_decode((string) $remote_id, TRUE);
        if ($decoded && isset($decoded['entity'], $decoded['reference'])) {
          $info['entidade'] = $decoded['entity'];
          $ref = preg_replace('/\D/', '', $decoded['reference']);
          $info['referencia'] = strlen($ref) === 9
            ? substr($ref, 0, 3) . ' ' . substr($ref, 3, 3) . ' ' . substr($ref, 6)
            : $decoded['reference'];
        }
        else {
          // Fallback: remote_id é a referência direta (módulo antigo)
          $config = $plugin->getConfiguration();
          $info['entidade'] = $config['multibanco_entidade'] ?? ($config['multibanco_mb_key'] ?? $config['mb_key'] ?? '');
          $ref = preg_replace('/\D/', '', (string) $remote_id);
          $info['referencia'] = strlen($ref) === 9
            ? substr($ref, 0, 3) . ' ' . substr($ref, 3, 3) . ' ' . substr($ref, 6)
            : $remote_id;
        }
      }
      elseif ($plugin_id === 'ifthenpay_mbway') {
        $info['tipo'] = 'mbway';
      }
    }

    return $info;
  }

  /**
   * Página de notificações.
   */
  public function notificacoes() {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();

    $message_storage = \Drupal::entityTypeManager()->getStorage('message');
    $ids = $message_storage->getQuery()
      ->condition('uid', $uid)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    $messages = $message_storage->loadMultiple($ids);

    // Marcar como lidas — guardar timestamp da última leitura.
    \Drupal::service('user.data')->set(
      'garagem_reservas',
      $uid,
      'notificacoes_lidas_ate',
      \Drupal::time()->getRequestTime()
    );

    $template_storage = \Drupal::entityTypeManager()->getStorage('message_template');
    $items = [];
    foreach ($messages as $message) {
      $template = $template_storage->load($message->bundle());
      $label = $template ? $template->label() : $message->bundle();

      $texts = $message->getText();
      $text_rendered = '';
      foreach ($texts as $text) {
        if (is_array($text)) {
          $text_rendered .= \Drupal::service('renderer')->renderRoot($text);
        } else {
          $text_rendered .= $text;
        }
      }

      $args = $message->get('arguments')->getValue();
      $reserva_id = $args[0]['@reserva_id'] ?? NULL;
      $url_reserva = $reserva_id ? \Drupal\Core\Url::fromRoute('garagem_reservas.reserva_view', ['reserva' => $reserva_id])->toString() : NULL;

      $items[] = [
        'label' => $label,
        'created' => $message->getCreatedTime(),
        'text' => $text_rendered,
        'url_reserva' => $url_reserva,
      ];
    }

    return [
      '#theme' => 'garagem_reservas_notificacoes',
      '#items' => $items,
      '#cache' => ['max-age' => 0],
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
    // Verificar que a garagem existe e está publicada.
    $garagem = $this->entityTypeManager()->getStorage('node')->load($node);
    if (!$garagem || !$garagem->isPublished() || $garagem->bundle() !== 'armazem') {
      return new JsonResponse(['error' => 'Not found'], 404);
    }

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

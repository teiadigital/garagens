<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\Core\Database\Connection;

/**
 * Serviço de verificação de disponibilidade de garagens.
 */
class DisponibilidadeService {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Verifica se uma garagem está disponível para um período.
   *
   * @param int $garagem_id
   *   ID da garagem.
   * @param int $inicio_ts
   *   Timestamp de início.
   * @param int|null $fim_ts
   *   Timestamp de fim. NULL se indefinido.
   *
   * @return bool
   *   TRUE se disponível, FALSE se não.
   */
  public function verificarDisponibilidade(int $garagem_id, int $inicio_ts, ?int $fim_ts): bool {
    $query = $this->database->select('garagem_reserva', 'gr')
      ->condition('garagem_id', $garagem_id)
      ->condition('estado', ['pago', 'aprovado', 'aguarda_pagamento', 'pendente'], 'IN');

    if ($fim_ts) {
      $or = $query->orConditionGroup()
        // Renovação automática que começa antes do fim pedido.
        ->condition(
          $query->andConditionGroup()
            ->condition('renovacao_automatica', 1)
            ->condition('data_inicio', $fim_ts, '<')
        )
        // Reservas normais que se sobrepõem ao período pedido.
        ->condition(
          $query->andConditionGroup()
            ->condition('data_inicio', $fim_ts, '<')
            ->condition(
              $query->orConditionGroup()
                ->isNull('data_fim')
                ->condition('data_fim', $inicio_ts, '>')
            )
        );
      $query->condition($or);
    }
    else {
      // Modo renovação automática — bloqueia se existir qualquer reserva ativa na garagem.
      // Não pode haver nenhuma outra reserva ativa, seja ela qual for.
      $query->condition(
        $query->orConditionGroup()
          ->condition('renovacao_automatica', 1)
          ->condition('data_inicio', $inicio_ts, '>=')
      );
    }

    $count = $query->countQuery()->execute()->fetchField();
    if ($count > 0) {
      return FALSE;
    }

    // Verificar também bloqueios manuais sobrepostos.
    $bloqueio_query = $this->database->select('garagem_indisponibilidade', 'gi')
      ->condition('garagem_id', $garagem_id);

    $fim_check = $fim_ts ?? PHP_INT_MAX;
    $bloqueio_query->condition('data_inicio', $fim_check, '<');
    $bloqueio_query->condition('data_fim', $inicio_ts, '>');

    $count_bloqueios = $bloqueio_query->countQuery()->execute()->fetchField();
    return $count_bloqueios == 0;
  }

  /**
   * Retorna as datas ocupadas de uma garagem.
   *
   * @param int $garagem_id
   *   ID da garagem.
   *
   * @return array
   *   Array de períodos ocupados.
   */
  public function getDatasOcupadas(int $garagem_id): array {
    $result = $this->database->select('garagem_reserva', 'gr')
      ->fields('gr', ['data_inicio', 'data_fim', 'renovacao_automatica'])
      ->condition('garagem_id', $garagem_id)
      ->condition('estado', ['pago', 'aprovado', 'pendente', 'aguarda_pagamento'], 'IN')
      ->execute()
      ->fetchAll();

    $datas = [];
    foreach ($result as $row) {
      $inicio_dia = mktime(0, 0, 0, date('n', $row->data_inicio), date('j', $row->data_inicio), date('Y', $row->data_inicio));
      // O dia de fim é exclusivo — não bloquear esse dia no calendário.
      $fim_dia = $row->data_fim
        ? mktime(0, 0, 0, date('n', $row->data_fim), date('j', $row->data_fim), date('Y', $row->data_fim))
        : NULL;

      $datas[] = [
        'inicio' => (string) $inicio_dia,
        'fim' => (string) $fim_dia,
        'renovacao_automatica' => (bool) $row->renovacao_automatica,
      ];
    }

    // Incluir bloqueios manuais do proprietário.
    $bloqueios = $this->database->select('garagem_indisponibilidade', 'gi')
      ->fields('gi', ['data_inicio', 'data_fim'])
      ->condition('garagem_id', $garagem_id)
      ->execute()
      ->fetchAll();

    foreach ($bloqueios as $bloqueio) {
      $datas[] = [
        'inicio' => (string) $bloqueio->data_inicio,
        'fim' => (string) $bloqueio->data_fim,
        'renovacao_automatica' => FALSE,
        'bloqueio_manual' => TRUE,
      ];
    }

    return $datas;
  }

  /**
   * Verifica se uma garagem tem reservas futuras ativas (a partir de hoje).
   */
  public function temReservasFuturas(int $garagem_id): bool {
    $hoje = mktime(0, 0, 0, date('n'), date('j'), date('Y'));

    $count = $this->database->select('garagem_reserva', 'gr')
      ->condition('garagem_id', $garagem_id)
      ->condition('estado', ['pago', 'aprovado', 'pendente', 'aguarda_pagamento'], 'IN')
      ->condition(
        $this->database->condition('OR')
          ->condition('renovacao_automatica', 1)
          ->condition('data_fim', $hoje, '>=')
      )
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($count > 0) {
      return TRUE;
    }

    // Verificar também bloqueios manuais futuros.
    $count_bloqueios = $this->database->select('garagem_indisponibilidade', 'gi')
      ->condition('garagem_id', $garagem_id)
      ->condition('data_fim', $hoje, '>=')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count_bloqueios > 0;
  }

}

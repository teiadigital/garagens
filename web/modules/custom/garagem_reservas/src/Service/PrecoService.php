<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\node\NodeInterface;

/**
 * Serviço de cálculo de preços de reservas.
 */
class PrecoService {

  /**
   * Calcula o preço de uma reserva.
   *
   * @param \Drupal\node\NodeInterface $garagem
   *   Node da garagem.
   * @param int $inicio_ts
   *   Timestamp de início.
   * @param int|null $fim_ts
   *   Timestamp de fim. NULL se indefinido.
   * @param bool $indefinido
   *   Se a reserva é por tempo indefinido.
   *
   * @return array
   *   Array com preco_total e tipo_preco.
   */
  public function calcularPreco(NodeInterface $garagem, int $inicio_ts, ?int $fim_ts, bool $indefinido): array {
    // Obter preços definidos pelo proprietário.

    // Preço por hora — desativado para já, pode ser reativado no futuro.
    // $preco_hora = $garagem->hasField('field_preco_hora_ativo') && $garagem->get('field_preco_hora_ativo')->value
    //   ? (float) $garagem->get('field_preco_hora')->value
    //   : NULL;

    $preco_dia = $garagem->hasField('field_preco_dia_ativo') && $garagem->get('field_preco_dia_ativo')->value
      ? (float) $garagem->get('field_preco_dia')->value
      : NULL;

    $preco_mes = $garagem->hasField('field_preco_mes_ativo') && $garagem->get('field_preco_mes_ativo')->value
      ? (float) $garagem->get('field_preco_mes')->value
      : NULL;

    // Reserva indefinida — calcular apenas o primeiro mês.
    if ($indefinido) {
      if ($preco_mes !== NULL) {
        return ['preco_total' => $preco_mes, 'tipo_preco' => 'mes'];
      }
      elseif ($preco_dia !== NULL) {
        // Converter preço/dia para 30 dias (primeiro mês).
        return ['preco_total' => $preco_dia * 30, 'tipo_preco' => 'dia'];
      }
      // Fallback hora — desativado.
      // else {
      //   return ['preco_total' => $preco_hora * 24 * 30, 'tipo_preco' => 'hora'];
      // }
    }

    // Calcular duração.
    $duracao_segundos = $fim_ts - $inicio_ts;
    $duracao_dias = $duracao_segundos / 86400;
    $duracao_meses = $duracao_dias / 30;

    // Determinar melhor tipo de preço baseado na duração.
    if ($duracao_meses >= 1 && $preco_mes !== NULL) {
      $meses = round($duracao_meses);
      return ['preco_total' => $preco_mes * $meses, 'tipo_preco' => 'mes'];
    }
    elseif ($preco_dia !== NULL) {
      $dias = ceil($duracao_dias);
      return ['preco_total' => $preco_dia * $dias, 'tipo_preco' => 'dia'];
    }

    // Preço por hora — desativado para já, pode ser reativado no futuro.
    // elseif ($preco_hora !== NULL) {
    //   $duracao_horas = $duracao_segundos / 3600;
    //   $horas = ceil($duracao_horas);
    //   return ['preco_total' => $preco_hora * $horas, 'tipo_preco' => 'hora'];
    // }

    // Fallback — converter entre unidades disponíveis.
    if ($preco_mes !== NULL) {
      $dias = ceil($duracao_dias);
      $preco_dia_calc = $preco_mes / 30;
      return ['preco_total' => $preco_dia_calc * $dias, 'tipo_preco' => 'mes'];
    }

    // Fallback hora — desativado.
    // if ($preco_dia !== NULL) {
    //   $duracao_horas = $duracao_segundos / 3600;
    //   $horas = ceil($duracao_horas);
    //   $preco_hora_calc = $preco_dia / 24;
    //   return ['preco_total' => $preco_hora_calc * $horas, 'tipo_preco' => 'dia'];
    // }

    return ['preco_total' => 0, 'tipo_preco' => 'dia'];
  }

}

<?php

namespace Drupal\garagem_reservas\Service;

use Drupal\node\NodeInterface;

/**
 * Serviço de cálculo de preços de reservas.
 */
class PrecoService {

  /**
   * Calcula o preço de uma reserva com base no tipo selecionado.
   *
   * @param \Drupal\node\NodeInterface $garagem
   * @param string $tipo_preco 'dia' | 'mes' | 'ano'
   * @param int $inicio_ts
   * @param int $fim_ts
   *
   * @return array ['preco_total' => float, 'tipo_preco' => string]
   */
  public function calcularPreco(NodeInterface $garagem, string $tipo_preco, int $inicio_ts, int $fim_ts): array {
    $duracao_dias = ($fim_ts - $inicio_ts) / 86400;

    switch ($tipo_preco) {
      case 'dia':
        $preco = (float) $garagem->get('field_preco_dia')->value;
        $dias = (int) round($duracao_dias);
        return ['preco_total' => $preco * $dias, 'tipo_preco' => 'dia'];

      case 'mes':
        $preco = (float) $garagem->get('field_preco_mes')->value;
        $meses = (int) round($duracao_dias / 30);
        return ['preco_total' => $preco * max(1, $meses), 'tipo_preco' => 'mes'];

      case 'ano':
        $preco = (float) $garagem->get('field_preco_ano')->value;
        $anos = (int) round($duracao_dias / 365);
        return ['preco_total' => $preco * max(1, $anos), 'tipo_preco' => 'ano'];
    }

    return ['preco_total' => 0, 'tipo_preco' => 'dia'];
  }

  /**
   * Retorna os tipos de preço ativos numa garagem.
   *
   * @return array e.g. ['dia' => 10.00, 'mes' => 250.00, 'ano' => 2800.00]
   */
  public function getTiposAtivos(NodeInterface $garagem): array {
    $tipos = [];

    if ($garagem->hasField('field_preco_dia_ativo') && $garagem->get('field_preco_dia_ativo')->value
      && $garagem->hasField('field_preco_dia') && !$garagem->get('field_preco_dia')->isEmpty()) {
      $tipos['dia'] = (float) $garagem->get('field_preco_dia')->value;
    }

    if ($garagem->hasField('field_preco_mes_ativo') && $garagem->get('field_preco_mes_ativo')->value
      && $garagem->hasField('field_preco_mes') && !$garagem->get('field_preco_mes')->isEmpty()) {
      $tipos['mes'] = (float) $garagem->get('field_preco_mes')->value;
    }

    if ($garagem->hasField('field_preco_ano_ativo') && $garagem->get('field_preco_ano_ativo')->value
      && $garagem->hasField('field_preco_ano') && !$garagem->get('field_preco_ano')->isEmpty()) {
      $tipos['ano'] = (float) $garagem->get('field_preco_ano')->value;
    }

    return $tipos;
  }

  /**
   * Verifica se a garagem aceita renovação automática.
   */
  public function aceitaRenovacaoAutomatica(NodeInterface $garagem): bool {
    return $garagem->hasField('field_renovacao_automatica')
      && (bool) $garagem->get('field_renovacao_automatica')->value;
  }

}

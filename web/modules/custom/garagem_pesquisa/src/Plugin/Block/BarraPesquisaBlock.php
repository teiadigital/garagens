<?php

namespace Drupal\garagem_pesquisa\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Barra de pesquisa de garagens.
 *
 * @Block(
 *   id = "garagem_barra_pesquisa",
 *   admin_label = @Translation("Barra de Pesquisa de Garagens"),
 *   category = @Translation("Garagens")
 * )
 */
class BarraPesquisaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $tipos = [];
    foreach (['dia' => 'field_preco_dia', 'mes' => 'field_preco_mes', 'ano' => 'field_preco_ano'] as $tipo => $campo) {
      $count = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'armazem')
        ->condition('status', 1)
        ->condition('field_estado', 3)
        ->condition($campo, NULL, 'IS NOT NULL')
        ->condition($campo, '', '<>')
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      if ($count > 0) $tipos[] = $tipo;
    }

    return [
      '#theme'    => 'garagem_barra_pesquisa',
      '#attached' => [
        'library'        => ['garagem_pesquisa/pesquisa'],
        'drupalSettings' => [
          'garagemPesquisa' => [
            'ajaxUrl' => Url::fromRoute('garagem_pesquisa.ajax')->toString(),
            'tipos'   => $tipos,
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}

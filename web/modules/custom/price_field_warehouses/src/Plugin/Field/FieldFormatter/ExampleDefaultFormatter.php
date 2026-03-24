<?php declare(strict_types = 1);

namespace Drupal\price_field_warehouses\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'price_field_warehouses_example_default' formatter.
 *
 * @FieldFormatter(
 *   id = "price_field_warehouses_example_default",
 *   label = @Translation("Default"),
 *   field_types = {"price_field_warehouses_example"},
 * )
 */
final class ExampleDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {

      if ($item->price_full) {
        $element[$delta]['price_full'] = [
          'preco_dia' => [
            '#type' => 'item',
            '#title' => $this->t('Preço Dia'),
            '#markup' => $item->preco_dia,
          ],
          'preco_mes' => [
            '#type' => 'item',
            '#title' => $this->t('Preço mês'),
            '#markup' => $item->preco_mes,
          ],
          'preco_ano' => [
            '#type' => 'item',
            '#title' => $this->t('Preço ano'),
            '#markup' => $item->preco_ano,
          ],
        ];
      }

      if ($item->price_share) {
        $element[$delta]['price_share'] = [
          'preco_dia' => [
            '#type' => 'item',
            '#title' => $this->t('Preço Dia'),
            '#markup' => $item->preco_dia,
          ],
          'preco_mes' => [
            '#type' => 'item',
            '#title' => $this->t('Preço mês'),
            '#markup' => $item->preco_mes,
          ],
          'preco_ano' => [
            '#type' => 'item',
            '#title' => $this->t('Preço ano'),
            '#markup' => $item->preco_ano,
          ],
        ];
      }

      if ($item->price_temp) {
        $element[$delta]['price_temp'] = [
          'preco_dia' => [
            '#type' => 'item',
            '#title' => $this->t('Preço Dia'),
            '#markup' => $item->preco_dia,
          ],
          'preco_mes' => [
            '#type' => 'item',
            '#title' => $this->t('Preço mês'),
            '#markup' => $item->preco_mes,
          ],
          'preco_ano' => [
            '#type' => 'item',
            '#title' => $this->t('Preço ano'),
            '#markup' => $item->preco_ano,
          ],
        ];
      }
    }

    return $element;
  }

}

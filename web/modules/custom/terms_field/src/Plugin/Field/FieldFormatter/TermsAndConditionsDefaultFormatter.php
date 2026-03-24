<?php declare(strict_types = 1);

namespace Drupal\terms_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'terms_field_terms_and_conditions_default' formatter.
 *
 * @FieldFormatter(
 *   id = "terms_field_terms_and_conditions_default",
 *   label = @Translation("Default"),
 *   field_types = {"terms_field_terms_and_conditions"},
 * )
 */
final class TermsAndConditionsDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {

      $element[$delta]['agree_to_terms_and_conditions'] = [
        '#type' => 'item',
        '#title' => $this->t('Agree to Terms and Conditions'),
        '#markup' => $item->agree_to_terms_and_conditions ? $this->t('Yes') : $this->t('No'),
      ];

    }

    return $element;
  }

}

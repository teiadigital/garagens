<?php declare(strict_types = 1);

namespace Drupal\price_field_warehouses\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'price_field_warehouses_example' field widget.
 *
 * @FieldWidget(
 *   id = "price_field_warehouses_example",
 *   label = @Translation("Example"),
 *   field_types = {"price_field_warehouses_example"},
 * )
 */
final class ExampleWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    // $element['preco_full'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Preço full'),
    // ];

    $element['preco_full_day'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço dia'),
      '#default_value' => $items[$delta]->preco_full_day ?? NULL,
      '#prefix' => '<div class="container-inline">',
    ];

    $element['preco_full_month'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço mês'),
      '#default_value' => $items[$delta]->preco_full_month ?? NULL,
      
    ];

    $element['preco_full_year'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço ano'),
      '#default_value' => $items[$delta]->preco_full_year ?? NULL,
      '#suffix' => '</div>',
    ];

    // $element['preco_share'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Preço share'),
    // ];
    
    $element['preco_share_day'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço dia'),
      '#default_value' => $items[$delta]->preco_share_day ?? NULL,
      '#prefix' => '<div class="container-inline">',
      
    ];

    $element['preco_share_month'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço mês'),
      '#default_value' => $items[$delta]->preco_share_month ?? NULL,
      
    ];

    $element['preco_share_year'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço ano'),
      '#default_value' => $items[$delta]->preco_share_year ?? NULL,
      '#suffix' => '</div>',
    ];

    // $element['preco_temp'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Preço temp'),
    // ];

    $element['preco_temp_day'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço dia'),
      '#default_value' => $items[$delta]->preco_temp_day ?? NULL,
      '#prefix' => '<div class="container-inline">',
      
    ];

    $element['preco_temp_month'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço mês'),
      '#default_value' => $items[$delta]->preco_temp_month ?? NULL,
      
    ];

    $element['preco_temp_year'] = [
      '#type' => 'number',
      '#step' => '.01',
      '#title' => $this->t('Preço ano'),
      '#default_value' => $items[$delta]->preco_temp_year ?? NULL,
      '#suffix' => '</div>',
    ];

    // $element['#theme_wrappers'] = ['container', 'form_element'];
    // $element['#attributes']['class'][] = 'container-inline';
    // $element['#attributes']['class'][] = 'price-field-warehouses-example-elements';
    // $element['#attached']['library'][] = 'price_field_warehouses/price_field_warehouses_example';

    return $element;
  }

  // /**
  //  * {@inheritdoc}
  //  */
  // public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|bool {
  //   $element = parent::errorElement($element, $error, $form, $form_state);
  //   if ($element === FALSE) {
  //     return FALSE;
  //   }
  //   $error_property = explode('.', $error->getPropertyPath())[1];
  //   return $element[$error_property];
  // }

  // /**
  //  * {@inheritdoc}
  //  */
  // public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
  //   foreach ($values as $delta => $value) {
  //     if ($value['value_1'] === '') {
  //       $values[$delta]['value_1'] = NULL;
  //     }
  //     if ($value['value_2'] === '') {
  //       $values[$delta]['value_2'] = NULL;
  //     }
  //     if ($value['value_3'] === '') {
  //       $values[$delta]['value_3'] = NULL;
  //     }
  //   }
  //   return $values;
  // }

}

<?php declare(strict_types = 1);

namespace Drupal\terms_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'terms_field_terms_and_conditions' field widget.
 *
 * @FieldWidget(
 *   id = "terms_field_terms_and_conditions",
 *   label = @Translation("Terms and Conditions"),
 *   field_types = {"terms_field_terms_and_conditions"},
 * )
 */
final class TermsAndConditionsWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    $settings = $items->getFieldDefinition()->getFieldStorageDefinition()->getSettings()['terms'];

    $html = '<div id="container-terms-box-'.$delta.'" class="container-terms-box">'.nl2br($settings).'</div>';

    $element['mymarkup'] = array(
      '#markup' => $html,
    );

    $element['agree_to_terms_and_conditions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree with the policy, terms and conditions of use of this application. (Read to the end and only then can you confirm.)'),
      '#default_value' => $items[$delta]->agree_to_terms_and_conditions ?? NULL,
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'][] = 'terms-field-terms-and-conditions-elements';
    $element['#attached']['library'][] = 'terms_field/terms_field_terms_and_conditions';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|bool {
    $element = parent::errorElement($element, $error, $form, $form_state);
    if ($element === FALSE) {
      return FALSE;
    }
    $error_property = explode('.', $error->getPropertyPath())[1];
    return $element[$error_property];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as $delta => $value) {
      if ($value['agree_to_terms_and_conditions'] === '') {
        $values[$delta]['agree_to_terms_and_conditions'] = NULL;
      }
    }
    return $values;
  }

}

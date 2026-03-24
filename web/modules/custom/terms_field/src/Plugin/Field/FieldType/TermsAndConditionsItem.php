<?php declare(strict_types = 1);

namespace Drupal\terms_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'terms_field_terms_and_conditions' field type.
 *
 * @FieldType(
 *   id = "terms_field_terms_and_conditions",
 *   label = @Translation("Terms and Conditions"),
 *   category = @Translation("General"),
 *   default_widget = "terms_field_terms_and_conditions",
 *   default_formatter = "terms_field_terms_and_conditions_default",
 * )
 */
final class TermsAndConditionsItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    $settings = ['terms' => 'Insira os termos neste campo'];
    return $settings + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $settings = $this->getSettings();

    $element['terms'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Terms'),
      '#default_value' => $settings['terms'],
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return $this->agree_to_terms_and_conditions != 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {

    $properties['agree_to_terms_and_conditions'] = DataDefinition::create('boolean')
      ->setLabel(t('Agree to Terms and Conditions'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();

    // NotBlank validator is not suitable for booleans because it does not
    // recognize '0' as an empty value.
    $options['agree_to_terms_and_conditions']['AllowedValues']['choices'] = [1];
    $options['agree_to_terms_and_conditions']['AllowedValues']['message'] = $this->t('This value should not be blank.');

    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', $options);
    // @todo Add more constraints here.
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {

    $columns = [
      'agree_to_terms_and_conditions' => [
        'type' => 'int',
        'size' => 'tiny',
      ],
    ];

    $schema = [
      'columns' => $columns,
      // @DCG Add indexes here if necessary.
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {

    $values['agree_to_terms_and_conditions'] = (bool) mt_rand(0, 1);

    return $values;
  }

}

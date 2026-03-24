<?php declare(strict_types = 1);

namespace Drupal\price_field_warehouses\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'price_field_warehouses_example' field type.
 *
 * @FieldType(
 *   id = "price_field_warehouses_example",
 *   label = @Translation("Example"),
 *   category = @Translation("General"),
 *   default_widget = "price_field_warehouses_example",
 *   default_formatter = "price_field_warehouses_example_default",
 * )
 */
final class ExampleItem extends FieldItemBase {


  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];

    $properties['preco_full_day'] = DataDefinition::create('float')
      ->setLabel(t('Preço dia'));

    $properties['preco_full_month'] = DataDefinition::create('float')
      ->setLabel(t('Preço mês'));

    $properties['preco_full_year'] = DataDefinition::create('float')
      ->setLabel(t('Preço ano'));

    $properties['preco_share_day'] = DataDefinition::create('float')
      ->setLabel(t('Preço dia'));

    $properties['preco_share_month'] = DataDefinition::create('float')
      ->setLabel(t('Preço mês'));

    $properties['preco_share_year'] = DataDefinition::create('float')
      ->setLabel(t('Preço ano'));

    $properties['preco_temp_day'] = DataDefinition::create('float')
      ->setLabel(t('Preço dia'));

    $properties['preco_temp_month'] = DataDefinition::create('float')
      ->setLabel(t('Preço mês'));

    $properties['preco_temp_year'] = DataDefinition::create('float')
      ->setLabel(t('Preço ano'));

      
    return $properties;
  }


  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {

    $columns['preco_full_day'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal',
    ];

    $columns['preco_full_month'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal',
    ];

    $columns['preco_full_year'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal', 
    ];

     $columns['preco_share_day'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal', 
    ];

    $columns['preco_share_month'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal',
    ];

    $columns['preco_share_year'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal',  
    ];

     $columns['preco_temp_day'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal',
    ];

    $columns['preco_temp_month'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal', 
    ];

    $columns['preco_temp_year'] = [
      'type' => 'float', 
      'unsigned' => TRUE, 
      'size' => 'normal', 
    ];

    $schema = [
      'columns' => $columns,
      // @DCG Add indexes here if necessary.
    ];

    return $schema;
  }


}

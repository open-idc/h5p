<?php

namespace Drupal\H5P\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of H5P.
 *
 * @FieldType(
 *   id = "h5p",
 *   label = @Translation("Interactive Content"),
 *   default_formatter = "h5p_default",
 *   default_widget = "h5p_upload",
 * )
 */
class H5PItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'library_id' => array(
          'description' => 'The library we instanciate using the parameters',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ),
        'parameters' => array(
          'description' => 'The raw/unsafe parameters to use',
          'type' => 'text',
          'not null' => TRUE,
          'size' => 'big',
        ),
        'filtered_parameters' => array(
          'description' => 'The filtered parameters that is safe to use',
          'type' => 'text',
          'not null' => TRUE,
          'size' => 'big',
        ),
        'disabled_features' => array(
          'description' => 'Keeps track of which features has been disabled for the content',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0
        ),
        'slug' => array(
          'description' => 'Human readable URL identifier used for the export filename',
          'type' => 'varchar',
          'length' => 127,
          'not null' => TRUE
        ),
      ),
      'indexes' => array(
        'library' => array('library_id'),
        'slug' => array('slug'),
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['library_id'] = DataDefinition::create('integer');
    $properties['parameters'] = DataDefinition::create('string');
    $properties['filtered_parameters'] = DataDefinition::create('string');
    $properties['disabled_features'] = DataDefinition::create('integer');
    $properties['slug'] = DataDefinition::create('string');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $library_id = $this->get('library_id')->getValue();
    return $library_id === NULL || $library_id === 0;
  }

}

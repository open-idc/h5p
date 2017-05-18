<?php

namespace Drupal\H5P\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\h5p\H5PDrupal\H5PDrupal;

/**
 * Provides a field type of H5P.
 *
 * @FieldType(
 *   id = "h5p",
 *   label = @Translation("Interactive Content"),
 *   description = @Translation("This field stores the ID of an H5P Content as an integer value."),
 *   category = @Translation("Reference"),
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
        'h5p_content_id' => array(
          'description' => 'Referance to the H5P Content entity ID',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ),
      ),
      'indexes' => array(
        'h5p_content_id' => array('h5p_content_id'),
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['h5p_content_id'] = DataDefinition::create('integer');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->get('h5p_content_id')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // TODO: Figure out if we need to do something here. Guess: Log content creation?
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    $this->deleteH5PContent();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    parent::deleteRevision();

    if (Drupal::state('h5p_revisioning')->get() ?: 1) {
      $this->deleteH5PContent();
    }
  }

  /**
   * Delete the H5P Content referenced by this field
   */
  private function deleteH5PContent() {
    $content_id = $this->get('h5p_content_id')->getValue();
    if (!empty($content_id)) {
      $storage = H5PDrupal::getInstance('storage');
      $storage->deletePackage([
        'id' => $content_id,
        'slug' => 'interactive-content',
      ]);
    }
  }
}

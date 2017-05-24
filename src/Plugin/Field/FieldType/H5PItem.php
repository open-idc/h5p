<?php

namespace Drupal\H5P\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;

/**
 * Provides a field type of H5P.
 *
 * @FieldType(
 *   id = "h5p",
 *   label = @Translation("Interactive Content – H5P"),
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
    $properties['h5p_content_revisioning_handled'] = DataDefinition::create('boolean');

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
  public function preSave() {
    // Handles the revisioning when there's no widget doing it
    $h5p_content_revisioning_handled = !empty($this->get('h5p_content_revisioning_handled')->getValue());
    if ($h5p_content_revisioning_handled || $this->isEmpty()) {
      return; // No need to do anything
    }

    // Determine if this is a new revision
    $entity = $this->getEntity();
    $is_new_revision = (!empty($entity->original) && $entity->getRevisionId() != $entity->original->getRevisionId());

    // Determine if we do revisioning for H5P content
    // (may be disabled to save disk space)
    $interface = H5PDrupal::getInstance();
    $do_new_revision = $interface->getOption('revisioning', TRUE) && $is_new_revision;
    if (!$do_new_revision) {
      return; // No need to do anything
    }

    // New revision, clone the existing content
    $h5p_content_id = $this->get('h5p_content_id')->getValue();
    $h5p_content = H5PContent::load($h5p_content_id);
    $h5p_content->set('id', NULL);
    $h5p_content->set('filtered_parameters', NULL);
    $h5p_content->save();

    // Clone content folder
    $core = H5PDrupal::getInstance('core');
    $core->fs->cloneContent($h5p_content_id, $h5p_content->id());

    // Update field reference id
    $this->set('h5p_content_id', $h5p_content->id());
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

    $interface = H5PDrupal::getInstance();
    if ($interface->getOption('revisioning', TRUE)) {
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

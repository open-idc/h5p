<?php

namespace Drupal\h5p\Plugin\Field\FieldType;

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
    $entity = $this->getEntity();
    $entity_type = $entity->getEntityType();
    if (!$entity_type->isRevisionable()) {
      // No revisions – only need to delete the current value
      self::deleteH5PContent($this->get('h5p_content_id')->getValue());
      return;
    }

    // We need to looks up all the revisions of this field and delete them
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $table_mapping = $storage->getTableMapping();

    $field_definition = $this->getFieldDefinition();
    $storage_definition = $field_definition->getFieldStorageDefinition();

    // Find revision table name
    $revision_table = $table_mapping->getDedicatedRevisionTableName($storage_definition);

    // Find column name
    $columns = $storage_definition->getColumns();
    $column = $table_mapping->getFieldColumnName($storage_definition, key($columns));

    // Look up all h5p content referenced by this field
    $database = \Drupal::database();
    $results = $database->select($revision_table, 'r')
        ->fields('r', [$column])
        ->condition('entity_id', $entity->id())
        ->execute();

    // … and delete them one by one
    while ($h5p_content_id = $results->fetchField()) {
      self::deleteH5PContent($h5p_content_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    $interface = H5PDrupal::getInstance();
    if ($interface->getOption('revisioning', TRUE)) {
      self::deleteH5PContent($this->get('h5p_content_id')->getValue());
    }
  }

  /**
   * Delete the H5P Content referenced by this field
   */
  public static function deleteH5PContent($content_id) {
    if (empty($content_id)) {
      return; // Nothing to delete
    }

    $h5p_content = H5PContent::load($content_id);
    if (empty($h5p_content)) {
      return; // Nothing to delete
    }

    $h5p_content->delete();
  }
}

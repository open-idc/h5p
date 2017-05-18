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
    $content_id = $this->get('h5p_content_id')->getValue();
    return $content_id === NULL || $content_id === 0;
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

    /*
    $res = db_query("SELECT content_id AS id, slug FROM {h5p_nodes} WHERE nid = :nid", array(':nid' => $entity->id()));
    while ($content = $res->fetchAssoc()) {
      h5p_delete_h5p_content($content);
    }

    if (isset($_SESSION['h5p']['node']['main_library'])) {
      // Log content delete
      new H5PDrupal\H5PEvent('content', 'delete',
        $entity->id(),
        $entity->label(),
        $_SESSION['h5p']['node']['main_library']['name'],
        $_SESSION['h5p']['node']['main_library']['majorVersion'] . '.' . $_SESSION['h5p']['node']['main_library']['minorVersion']
      );
    }
    */

    /*
    $helper = new Helper\H5PEnvironment();
    $h5p_core = $helper->getInstance('storage');

    $h5p_core->deletePackage($content);

    // Remove content points
    db_delete('h5p_points')
      ->condition('content_id', $content['id'])
      ->execute();

    // Remove content user data
    db_delete('h5p_content_user_data')
      ->condition('content_main_id', $content['id'])
      ->execute();
    */
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    parent::deleteRevision();

    /*
    $h5p_revisioning = Drupal::state('h5p_revisioning')->get() ?: 1;

    if ($h5p_revisioning) {
      h5p_delete_h5p_content(array(
        'id' => $entity->id(),
        'slug' => $_SESSION['h5p']['node']['h5p_slug'],
      ));
    }
    */
  }

}

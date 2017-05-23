<?php

namespace Drupal\h5p;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Defines the H5PContent schema handler.
 */
class H5PContentStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['h5p_content']['indexes'] += array(
      'h5p_library' => array('library_id'),
    );

    return $schema;
  }
}

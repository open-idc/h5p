<?php

/**
 * @file
 * Provide views data for h5p.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 *
 * @see views_field_default_views_data()
 */
function h5p_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  foreach ($data as $table_name => $table_data) {
    $data[$table_name][$field_storage->getName() . '_h5p_content_id']['relationship'] = [
      'id' => 'standard',
      'base' => 'h5p_content',
      'entity type' => 'h5p_content',
      'base field' => 'id', // i.e h5p_content.id
      'label' => t('H5P from @field_name', ['@field_name' => $field_storage->getName()]),
    ];
  }

  return $data;
}

<?php

/**
 * @file
 * Provide views data for file.module.
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

/**
 * Implements hook_field_views_data_views_data_alter().
 *
 * Views integration to provide reverse relationships on H5P fields.
 */
/*function h5p_field_views_data_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
  $entity_type_id = $field_storage->getTargetEntityTypeId();
  $field_name = $field_storage->getName();
  $entity_manager = \Drupal::entityManager();
  $entity_type = $entity_manager->getDefinition($entity_type_id);
  $pseudo_field_name = 'reverse_' . $field_name . '_' . $entity_type_id;
  $table_mapping = $entity_manager->getStorage($entity_type_id)->getTableMapping();

  list($label) = views_entity_field_label($entity_type_id, $field_name);

  $data['h5p_content'][$pseudo_field_name]['relationship'] = [
    'title' => t('@entity using @field', ['@entity' => $entity_type->getLabel(), '@field' => $label]),
    'label' => t('@field_name', ['@field_name' => $field_name]),
    'help' => t('Relate each @entity with a @field set to the H5P.', ['@entity' => $entity_type->getLabel(), '@field' => $label]),
    'group' => $entity_type->getLabel(),
    'id' => 'entity_reverse',
    'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
    'entity_type' => $entity_type_id,
    'base field' => $entity_type->getKey('id'),
    'field_name' => $field_name,
    'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
    'field field' => $field_name . '_h5p_content_id',
    'join_extra' => [
      0 => [
        'field' => 'deleted',
        'value' => 0,
        'numeric' => TRUE,
      ],
    ],
  ];
}*/

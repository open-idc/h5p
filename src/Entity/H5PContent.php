<?php

namespace Drupal\h5p\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the h5p content entity.
 *
 * @ContentEntityType(
 *   id = "h5p_content",
 *   label = @Translation("H5P Content"),
 *   base_table = "h5p_content",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 * )
 */
class H5PContent extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id']->setDescription(t('The ID of the H5P Content entity.'));

    $fields['library_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Library ID'))
      ->setDescription(t('The ID of the library we instanciate using our parameters.'))
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $fields['parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Parameters'))
      ->setDescription(t('The raw/unsafe parameters.'))
      ->setSetting('size', 'big')
      ->setRequired(TRUE);

    $fields['filtered_parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Filtered Parameters'))
      ->setDescription(t('The filtered parameters that are safe to use'))
      ->setSetting('size', 'big')
      ->setDefaultValue('');

    $fields['disabled_features'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Disabled Features'))
      ->setDescription(t('Keeps track of which features has been disabled for the content.'))
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(0);

    return $fields;
  }

  public function getID() {
    return $this->get('id')->value;
  }
}

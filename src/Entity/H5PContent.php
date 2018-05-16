<?php

namespace Drupal\h5p\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\H5PDrupal\H5PEvent;

/**
 * Defines the h5p content entity.
 *
 * @ContentEntityType(
 *   id = "h5p_content",
 *   label = @Translation("H5P Content"),
 *   handlers = {
 *     "storage_schema" = "Drupal\h5p\H5PContentStorageSchema",
 *     "views_data" = "Drupal\h5p\H5PContentViewsData",
 *   },
 *   base_table = "h5p_content",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 * )
 */
class H5PContent extends ContentEntityBase implements ContentEntityInterface {

  protected $library;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id']->setDescription(t('The ID of the H5P Content entity.'));

    $fields['library_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Library ID'))
      ->setDescription(t('The ID of the library we instanciate using our parameters.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'normal')
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
      ->setSetting('size', 'small')
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Load library used by content
   */
  protected function loadLibrary() {
    $this->library = db_query(
        "SELECT  title,
                 machine_name AS name,
                 major_version AS major,
                 minor_version AS minor,
                 embed_types,
                 fullscreen
            FROM {h5p_libraries}
           WHERE library_id = :id",
        [
          ':id' => $this->get('library_id')->value
        ])
        ->fetchObject();
  }

  /**
   *
   */
  public function getLibrary($assoc = FALSE) {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    if ($assoc) {
      return [
        'name' => $this->library->name,
        'machineName' => $this->library->name,
        'majorVersion' => $this->library->major,
        'minorVersion' => $this->library->minor
      ];
    }

    return $this->library;
  }

  /**
   *
   */
  public function getLibraryString() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    return "{$this->library->name} {$this->library->major}.{$this->library->minor}";
  }

  /**
   *
   */
  public function getLibraryId() {
    return $this->get('library_id')->value;
  }

  /**
   *
   */
  public function isDivEmbeddable() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    return (strpos($this->library->embed_types, 'iframe') === FALSE);
  }

  /**
   *
   */
  protected function getExportURL() {
    $interface = H5PDrupal::getInstance();
    if (empty($interface->getOption('export', TRUE))) {
      return '';
    }

    $h5p_path = $interface->getOption('default_path', 'h5p');
    return file_create_url("public://{$h5p_path}/exports/interactive-content-" . $this->id() . '.h5p');
  }

  /**
   * Only use for data comparison. Must not be used for content display.
   */
  public function getParameters() {
    return json_decode($this->get('parameters')->value);
  }

  /**
   *
   */
  public function getFilteredParameters() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    $content = [
      'title' => 'Interactive Content',
      'id' => $this->id(),
      'slug' => 'interactive-content',
      'library' => [
        'name' => $this->library->name,
        'majorVersion' => $this->library->major,
        'minorVersion' => $this->library->minor,
      ],
      'params' => $this->get('parameters')->value,
      'filtered' => $this->get('filtered_parameters')->value,
      'embedType' => 'div',
    ];

    $core = H5PDrupal::getInstance('core');
    $filteredParameters = $core->filterParameters($content);

    // alters filtered params
    $moduleHandler = \Drupal::moduleHandler();
    $filteredAsJson = json_decode($filteredParameters);
    $moduleHandler->alter('h5p_filtered_params', $filteredAsJson);
    return json_encode($filteredAsJson);
  }

  /**
   *
   */
  public function getH5PIntegrationSettings($canUpdateEntity) {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    // Load user data for content
    $results = db_query(
        "SELECT sub_content_id, data_id, data
           FROM {h5p_content_user_data}
          WHERE user_id = :user_id
            AND content_main_id = :content_id
            AND preloaded = 1",
        [
          ':user_id' => \Drupal::currentUser()->id(),
          ':content_id' => $this->id(),
        ]
    );

    $content_user_data = [
      0 => [
        'state' => '{}',
      ]
    ];
    foreach ($results as $result) {
      $content_user_data[$result->sub_content_id][$result->data_id] = $result->data;
    }

    $core = H5PDrupal::getInstance('core');
    $filtered_parameters = $this->getFilteredParameters();
    $display_options = $core->getDisplayOptionsForView($this->get('disabled_features')->value, $canUpdateEntity);

    $h5p_module_path = drupal_get_path('module', 'h5p');
    $embed_url = Url::fromUri('internal:/h5p/' . $this->id() . '/embed', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
    $resizer_url = Url::fromUri('internal:/' . $h5p_module_path . '/vendor/h5p/h5p-core/js/h5p-resizer.js', ['absolute' => TRUE, 'language' => FALSE])->toString(TRUE)->getGeneratedUrl();

    return array(
      'library' => $this->getLibraryString(),
      'jsonContent' => $filtered_parameters,
      'fullScreen' => $this->library->fullscreen,
      'exportUrl' => $this->getExportURL(),
      'embedCode' => '<iframe src="' . $embed_url . '" width=":w" height=":h" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
      'resizeCode' => '<script src="' . $resizer_url . '" charset="UTF-8"></script>',
      'url' => $embed_url,
      'title' => 'Not Available',
      'contentUserData' => $content_user_data,
      'displayOptions' => $display_options,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    // Delete entity
    parent::delete();

    // Delete all associated files and data
    $storage = H5PDrupal::getInstance('storage');
    $storage->deletePackage([
      'id' => $this->id(),
      'slug' => 'interactive-content',
    ]);

    // Log content delete
    new H5PEvent('content', 'delete',
      $this->id(), '',
      $this->library->name, $this->library->major . '.' . $this->library->minor
    );
  }
}

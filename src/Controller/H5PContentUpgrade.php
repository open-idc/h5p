<?php

namespace Drupal\h5p\Controller;

use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\H5PDrupal\H5PEvent;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The H5PContentUpgrade controller
 */
class H5PContentUpgrade extends ControllerBase {

  protected $database;

  /**
   * constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    $controller = new static(\Drupal::database());
    return $controller;
  }

  /**
   * Helper for getting library versions
   *
   * @param string $library_id
   *
   * @return array
   */
  public static function getLibraryVersions($library_id) {
    $query = \Drupal::database()->select('h5p_libraries', 'hl1');
    $query->join('h5p_libraries', 'hl2', 'hl1.machine_name = hl2.machine_name');
    $query->condition('hl1.library_id', $library_id, '=');
    $query->addField('hl2', 'library_id', 'id');
    $query->fields('hl2', ['machine_name', 'title', 'major_version', 'minor_version', 'patch_version']);
    $query->orderBy('hl2.title', 'ASC');
    $query->orderBy('hl2.major_version', 'ASC');
    $query->orderBy('hl2.minor_version', 'ASC');
    $results = $query->execute();

    $versions = [];
    foreach ($results as $result) {
      $versions[$result->id] = $result;
    }

    return $versions;
  }

  /**
   * Creates the title for the upgrade content page
   *
   * @param string $library_id
   *
   * @return string
   */
  public function pageTitle($library_id) {
    $query = $this->database->select('h5p_libraries', 'l');
    $query->condition('library_id', $library_id, '=');
    $query->fields('l', ['title', 'major_version', 'minor_version', 'patch_version']);
    $library = $query->execute()->fetch();

    return t('Upgrade @library content', ['@library' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')']);
  }

  /**
   * Handles saving of upgraded content. Returns new batch
   *
   * @param string $library_id
   *
   * @return JsonResponse|array
   */
  public function upgrade($library_id) {
    // Verify security token
    if (!\H5PCore::validToken('contentupgrade', filter_input(INPUT_POST, 'token'))) {
      return ['#markup' => t('Error: Invalid security token!')];
    }

    // Get the library we're upgrading to
    $to_library = $this->database->query('SELECT library_id AS id, machine_name AS name, major_version, minor_version FROM {h5p_libraries} WHERE library_id = :id', [':id' => filter_input(INPUT_POST, 'libraryId')])->fetch();
    if (!$to_library) {
      return ['#markup' => t('Error: Your library is missing!')];
    }

    // Prepare response
    $out = [
      'params' => [],
      'token' => \H5PCore::createToken('contentupgrade'),
    ];

    // Prepare our interface
    $interface = H5PDrupal::getInstance('interface');

    // Get updated params
    $params = filter_input(INPUT_POST, 'params');
    if ($params !== NULL) {
      // Update params.
      $params = json_decode($params);
      foreach ($params as $id => $param) {
        $this->database->update('h5p_content')
          ->fields([
            'library_id' => $to_library->id,
            'parameters' => $param,
            'filtered_parameters' => ''
          ])
          ->condition('id', $id)
          ->execute();

        // Log content upgrade successful
        new H5PEvent('content', 'upgrade',
          $id,
          '', // Should be title, but an entity does not have one
          $to_library->name,
          $to_library->major_version . '.' . $to_library->minor_version);

        // Clear content cache
        $interface->updateContentFields($id, ['filtered' => '']);
      }
    }

    // Get number of contents for this library
    $out['left'] = $interface->getNumContent($library_id);

    if ($out['left']) {
      // Find the 10 first contents using library and add to params
      $contents = $this->database->query('SELECT id, parameters AS params FROM {h5p_content} WHERE library_id = :id LIMIT 40', [':id' => $library_id]);
      foreach ($contents as $content) {
        $out['params'][$content->id] = $content->params;
      }
    }

    return new JsonResponse($out);
  }

  /**
   * AJAX loading of libraries for content upgrade script.
   *
   * @param string $name Machine name
   * @param int $major
   * @param int $minor
   *
   * @return JsonResponse
   */
  public function prepareUpgrade($name, $major, $minor) {
    $library = (object) [
      'name' => $name,
      'version' => (object) [
        'major' => $major,
        'minor' => $minor,
      ],
    ];

    $core = H5PDrupal::getInstance('core');
    $library->semantics = $core->loadLibrarySemantics($library->name, $library->version->major, $library->version->minor);
    if ($library->semantics === NULL) {
      throw new NotFoundHttpException();
    }

    $upgrades_script = H5PDrupal::getRelativeH5PPath() . "/libraries/{$library->name}-{$library->version->major}.{$library->version->minor}/upgrades.js";

    if (file_exists($upgrades_script)) {
      $library->upgradesScript = base_path() . $upgrades_script;
    }

    return new JsonResponse($library);
  }
}

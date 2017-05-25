<?php

namespace Drupal\h5p\Controller;

use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class H5PAdmin extends ControllerBase {

  protected $database;

  /**
   * constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {

    $controller = new static(
      $container->get('database')
    );
    return $controller;
  }

  /**
   * Creates the library list page
   *
   * @return string Html
   */
  function libraryList() {

    $core = H5PDrupal::getInstance('core');
    $numNotFiltered = $core->h5pF->getNumNotFiltered();
    $libraries = $core->h5pF->loadLibraries();

    // Add settings for each library
    $settings = [];
    $i = 0;
    foreach ($libraries as $versions) {
      foreach ($versions as $library) {
        // TODO: Fix interface, getLibraryUsage only take 1 arg
        $usage = $core->h5pF->getLibraryUsage($library->id, $numNotFiltered ? TRUE : FALSE);
        if ($library->runnable) {
          $upgrades = $core->getUpgrades($library, $versions);

          $url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/upgrade')->toString();
          $upgradeUrl = empty($upgrades) ? FALSE : $url;

          $restricted = ($library->restricted === '1' ? TRUE : FALSE);
          $option = [
            'query' => [
              'token' => \H5PCore::createToken('library_' . $i),
              'token_id' => $i,
              'restrict' => ($library->restricted === '1' ? 0 : 1)
            ]
          ];
          $restricted_url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/restrict', $option)->toString();
        }
        else {
          $upgradeUrl = NULL;
          $restricted = NULL;
          $restricted_url = NULL;
        }

        $option = [
          'query' => drupal_get_destination(),
        ];
        $deleteUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/delete', $option);
        //$detailsUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id);

        $settings['libraryList']['listData'][] = [
          'title' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')',
          'restricted' => $restricted,
          'restrictedUrl' => $restricted_url,
          'numContent' => $core->h5pF->getNumContent($library->id),
          'numContentDependencies' => $usage['content'] === -1 ? '' : $usage['content'],
          'numLibraryDependencies' => $usage['libraries'],
          'upgradeUrl' => $upgradeUrl,
          //'detailsUrl' => $detailsUrl->toString(),
          'deleteUrl' => $deleteUrl->toString(),
        ];

        $i++;
      }
    }

    // All translations are made server side
    $settings['libraryList']['listHeaders'] = [t('Title'), t('Restricted'), t('Instances'), t('Instance Dependencies'), t('Library dependencies'), t('Actions')];

    // Make it possible to rebuild all caches.
    if ($numNotFiltered) {
      $settings['libraryList']['notCached'] = $this->getNotCachedSettings($numNotFiltered);
    }

    $settings['containerSelector'] = '#h5p-admin-container';

    // Add the needed css and javascript
    $build['#attached'] = $this->addSettings($settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.list';

    $build['title_add'] = ['#markup' => '<h3 class="h5p-admin-header">' . t('Add libraries') . '</h3>'];
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\h5p\Form\H5PLibraryUploadForm');
    $build['title_installed'] = ['#markup' => '<h3 class="h5p-admin-header">' . t('Installed libraries') . '</h3>'];
    $build['container'] = ['#markup' => '<div id="h5p-admin-container"></div>'];

    return $build;
  }

  /**
   * Settings needed to rebuild cache from UI.
   *
   * @param int $num
   * @return array
   */
  function getNotCachedSettings($num) {
    $url = Url::fromUri('internal:/admin/content/h5p/rebuild-cache');
    return [
      'num' => $num,
      'url' => $url->toString(),
      'message' => t('Not all content has gotten their cache rebuilt. This is required to be able to delete libraries, and to display how many contents that uses the library.'),
      'progress' => \Drupal::translation()->formatPlural($num, '1 content need to get its cache rebuilt.', '@count contents needs to get their cache rebuilt.'),
      'button' => t('Rebuild cache')
    ];
  }

  /**
   * Creates the library list page
   *
   * @param {string} $library_id The id of the library to be displayed
   *
   * @return {string} Html string
   */
  /*function libraryDetails($library_id) {

    $settings = [];

    $query = $this->database->select('h5p_libraries', 'l');
    $query->fields('l', ['title', 'machine_name', 'major_version', 'minor_version', 'patch_version', 'runnable', 'fullscreen']);
    $query->condition('l.library_id', $library_id, '=');
    $library = $query->execute()->fetchObject();

    // Build library info
    $settings['libraryInfo']['info'] = [
      'Name' => $library->title,
      'Machine name' => $library->machine_name,
      'Version' => \H5PCore::libraryVersion($library),
      'Runnable' => $library->runnable ? t('Yes') : t('No'),
      'Fullscreen' => $library->fullscreen ? t('Yes') : t('No'),
    ];

    // Build the translations needed
    $settings['libraryInfo']['translations'] = [
      'contentCount' => t('Content count'),
      'noContent' => t('No content is using this library'),
      'contentHeader' => t('Content using this library'),
      'pageSizeSelectorLabel' => t('Elements per page'),
      'filterPlaceholder' => t('Filter content'),
      'pageXOfY' => t('Page $x of $y'),
    ];

    $h5p_drupal = H5PDrupal::getInstance('interface');
    $numNotFiltered = $h5p_drupal->getNumNotFiltered();
    if ($numNotFiltered) {
      $settings['libraryInfo']['notCached'] = $this->getNotCachedSettings($numNotFiltered);

    } else {

      // Build a list of the content using this library
      $query = $this->database->select('h5p_content_libraries', 'l');
      $query->distinct();
      $query->fields('n', ['nid', 'title']);
      //$query->join('h5p_nodes', 'hn', 'l.content_id = hn.content_id');

      $query->join('node_field_data', 'n', 'hn.nid = n.nid');
      $query->condition('l.library_id', $library_id, '=');
      $query->orderBy('n.title', 'ASC');
      $nodes_res = $query->execute();

      foreach($nodes_res as $node) {
        $node_url = Url::fromUri('internal:/node/' . $node->nid);
        $settings['libraryInfo']['content'][] = [
          'title' => $node->title,
          'url' => $node_url->toString(),
        ];
      }
    }

    // Add the needed css and javascript
    $settings['containerSelector'] = '#h5p-admin-container';
    $build['#attached'] = $this->addSettings($settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.details';
    $build['container'] = ['#markup' => '<div id="h5p-admin-container"></div>'];
    $build['#cache']['max-age'] = 0;

    return $build;
  }*/

  /**
   * Creates the title for the upgrade content page
   *
   * @param string $library_id
   * @return string
   */
  function libraryUpgradePageTitle($library_id) {
    $query = $this->database->select('h5p_libraries', 'l');
    $query->condition('library_id', $library_id, '=');
    $query->fields('l', ['title', 'major_version', 'minor_version', 'patch_version']);
    $library = $query->execute()->fetch();

    return t('Upgrade @library content', ['@library' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')']);
  }
  /**
   * Callback for the library content upgrade page.
   *
   * @param int $library_id
   * @return string HTML
   */
  function libraryUpgrade($library_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      return $this->contentUpgradeProgress($library_id);
    }

    $query = $this->database->select('h5p_libraries', 'hl1');
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

    $library = $versions[$library_id];

    $core = H5PDrupal::getInstance('core');
    $upgrades = $core->getUpgrades($library, $versions);

    if (count($versions) < 2) {
      return ['#markup' => t("There are no available upgrades for this library.")];
    }

    // Get num of contents that can be upgraded
    $contents = $core->h5pF->getNumContent($library_id);
    if (!$contents) {
      return ['#markup' => t("There's no content instances to upgrade.")];
    }

    $contents_plural = \Drupal::translation()->formatPlural($contents, '1 content instance', '@count content instances');
    $returnLink = Link::fromTextAndUrl(t('Return'), Url::fromUri('internal:/admin/content/h5p/'))->toString();
    $settings = [
      'libraryInfo' => [
        'message' => t('You are about to upgrade %num. Please select upgrade version.', ['%num' => $contents_plural]),
        'inProgress' => t('Upgrading to %ver...'),
        'error' => t('An error occurred while processing parameters:'),
        'errorData' => t('Could not load data for library %lib.'),
        'errorScript' => t('Could not load upgrades script for %lib.'),
        'errorContent' => t('Could not upgrade content %id:'),
        'errorParamsBroken' => t('Parameters are broken.'),
        'done' => t('You have successfully upgraded %num.', ['%num' => $contents_plural]) . $returnLink,
        'library' => [
          'name' => $library->machine_name,
          'version' => $library->major_version . '.' . $library->minor_version,
        ],
        'libraryBaseUrl' => Url::fromUri('internal:/admin/content/h5p/upgrade/library')->toString(),
        'scriptBaseUrl' => base_path() . 'vendor/h5p/h5p-core/js/',
        'buster' => '?' . \Drupal::state()->get('css_js_query_string') ?: '',
        'versions' => $upgrades,
        'contents' => $contents,
        'buttonLabel' => t('Upgrade'),
        'infoUrl' => Url::fromUri("internal:/admin/content/h5p/libraries/{$library_id}/upgrade")->toString(),
        'total' => $contents,
        'token' => \H5PCore::createToken('contentupgrade'), // Use token to avoid unauthorized updating
      ]
    ];

    // Create page - add settings and JS
    $build['#markup'] = '<div id="h5p-admin-container">' . t('Please enable JavaScript.') . '</div>';
    $build['#attached'] = $this->addSettings($settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.upgrade';

    return $build;
  }

  /**
   * Handles saving of upgraded content. Returns new batch
   *
   * @param string $library_id
   */
  private function contentUpgradeProgress($library_id) {
    // Verify security token
    if (!\H5PCore::validToken('contentupgrade', filter_input(INPUT_POST, 'token'))) {
      return ['#markup' => t('Error: Invalid security token!')];
    }

    // Get the library we're upgrading to
    $to_library = db_query('SELECT library_id AS id, machine_name AS name, major_version, minor_version FROM {h5p_libraries} WHERE library_id = :id', [':id' => filter_input(INPUT_POST, 'libraryId')])->fetch();
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
        db_update('h5p_content')
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
      $contents = db_query('SELECT id, parameters AS params FROM {h5p_content} WHERE library_id = :id LIMIT 40', [':id' => $library_id]);
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
   */
  function contentUpgradeLibrary($name, $major, $minor) {
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

  /**
   * Display library delete page with form.
   *
   * @param string $library_id
   */
  function libraryDelete($library_id) {
    // Is library deletable ?
    $h5p_drupal = H5PDrupal::getInstance('interface');
    $notCached = $h5p_drupal->getNumNotFiltered();
    $library_usage = $h5p_drupal->getLibraryUsage($library_id, $notCached ? TRUE : FALSE);
    if ($library_usage['content'] === 0 && $library_usage['libraries'] === 0) {
      // Create form:
      return \Drupal::formBuilder()->getForm('Drupal\h5p\Form\H5PLibraryDeleteForm', $library_id, $this->libraryDetailsTitle($library_id));

    } else {
      // May not delete this one
      return t('Library is in use by content, or is dependent by other librarie(s), and can therefore not be deleted');
    }
  }

  function embed() {
    // TODO
  }
  /**
   * Restrict a library
   *
   * @param string $library_id
   */
  function libraryRestrict($library_id) {
    $restricted = filter_input(INPUT_GET, 'restrict');
    $restrict = ($restricted === '1');

    $token_id = filter_input(INPUT_GET, 'token_id');
    if (!\H5PCore::validToken('library_' . $token_id, filter_input(INPUT_GET, 'token')) || (!$restrict && $restricted !== '0')) {
      return MENU_ACCESS_DENIED;
    }

    db_update('h5p_libraries')
      ->fields(['restricted' => $restricted])
      ->condition('library_id', $library_id)
      ->execute();

    $restrictUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library_id . '/restrict', [
      'query' => [
        'token' => \H5PCore::createToken('library_' . $token_id),
        'token_id' => $token_id,
        'restrict' => ($restrict ? 0 : 1),
      ]
    ]);

    return new JsonResponse(['url' => $restrictUrl->toString()]);
  }

  /**
   * Callback for rebuilding all content cache.
   */
  function rebuildCache() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      return new JsonResponse('What you want? This is not for you.');
    }

    // Do as many as we can in ten seconds.
    $start = microtime(TRUE);

    $core = H5PDrupal::getInstance('core');

    $query = $this->database->select('h5p_content', 'hc')
      ->fields('hc', ['id'])
      ->isNull('hc.filtered_parameters');
    $num_rows = $query->countQuery()->execute()->fetchField();
    $result = $query->execute();

    $done = 0;
    foreach ($result as $row) {
      $h5p_content = H5PContent::load($row->id);
      $h5p_content->getFilteredParameters();
      $done++;

      if ((microtime(TRUE) - $start) > 10) {
        break;
      }
    }

    $count = $num_rows - $done;

    return new JsonResponse((string)$count);
  }


  /**
   * Creates the title for the library details page
   *
   * @param integer $library_id
   */
  function libraryDetailsTitle($library_id) {
    $query = $this->database->select('h5p_libraries', 'l');
    $query->condition('l.library_id', $library_id, '=');
    $query->fields('l', ['title']);
    return $query->execute()->fetchField();
  }

  /**
   * Helper function - adds admin settings
   *
   * @param {array} $settings
   */
  function addSettings($settings = NULL) {
    $module_path = drupal_get_path('module', 'h5p');

    if ($settings === NULL) {
      $settings = [];
    }

    $settings['containerSelector'] = '#h5p-admin-container';
    $settings['l10n'] = [
      'NA' => t('N/A'),
      /*'viewLibrary' => t('View library details'),*/
      'deleteLibrary' => t('Delete library'),
      'upgradeLibrary' => t('Upgrade library content')
    ];

    $build['drupalSettings']['h5p']['drupal_h5p_admin_integration'] = [
      'H5PAdminIntegration' =>  $settings,
    ];
    $build['drupalSettings']['h5p']['drupal_h5p'] = [
      'H5P' => H5PDrupal::getGenericH5PIntegrationSettings(),
    ];

    return $build;
  }

}

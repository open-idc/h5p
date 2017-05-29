<?php

namespace Drupal\h5p\Controller;

use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class H5PLibraryAdmin extends ControllerBase {

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

          $url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/upgrade-confirm')->toString();
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
    $build['#attached'] = self::addSettings($settings);
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
   * Restrict a library
   *
   * @param string $library_id
   */
  function restrict($library_id) {
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
  public static function libraryDetailsTitle($library_id) {
    $query = db_select('h5p_libraries', 'l');
    $query->condition('l.library_id', $library_id, '=');
    $query->fields('l', ['title']);
    return $query->execute()->fetchField();
  }

  /**
   * Helper function - creates admin settings
   *
   * @param array $settings
   * @return array
   */
  public static function addSettings($settings = NULL) {
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

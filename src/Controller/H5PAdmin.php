<?php

namespace Drupal\h5p\Controller;

use Drupal\h5p\H5PDrupal\H5PDrupal;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;


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
   * @return {string} Html
   */
  function libraryList() {

    $core = H5PDrupal::getInstance('core');
    $numNotFiltered = $core->h5pF->getNumNotFiltered();
    $libraries = $core->h5pF->loadLibraries();

    // Add settings for each library
    $settings = array();
    $i = 0;
    foreach ($libraries as $versions) {
      foreach ($versions as $library) {
        $usage = $core->h5pF->getLibraryUsage($library->id, $numNotFiltered ? TRUE : FALSE);
        if ($library->runnable) {
          $upgrades = $core->getUpgrades($library, $versions);

          $option = array(
            'query' => drupal_get_destination(),
          );
          $url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/upgrade', $option)->toString();
          $upgradeUrl = empty($upgrades) ? FALSE : $url;

          $restricted = ($library->restricted === '1' ? TRUE : FALSE);
          $option = array(
            'query' => array(
              'token' => \H5PCore::createToken('library_' . $i),
              'token_id' => $i,
              'restrict' => ($library->restricted === '1' ? 0 : 1)
            )
          );
          $restricted_url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/restrict', $option)->toString();
        }
        else {
          $upgradeUrl = NULL;
          $restricted = NULL;
          $restricted_url = NULL;
        }

        $option = array(
          'query' => drupal_get_destination(),
        );
        $deleteUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/delete', $option);
        //$detailsUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id);

        $settings['libraryList']['listData'][] = array(
          'title' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')',
          'restricted' => $restricted,
          'restrictedUrl' => $restricted_url,
          'numContent' => $core->h5pF->getNumContent($library->id),
          'numContentDependencies' => $usage['content'] === -1 ? '' : $usage['content'],
          'numLibraryDependencies' => $usage['libraries'],
          'upgradeUrl' => $upgradeUrl,
          //'detailsUrl' => $detailsUrl->toString(),
          'deleteUrl' => $deleteUrl->toString(),
        );

        $i++;
      }
    }

    // All translations are made server side
    $settings['libraryList']['listHeaders'] = array(t('Title'), t('Restricted'), t('Instances'), t('Instance Dependencies'), t('Library dependencies'), t('Actions'));

    // Make it possible to rebuild all caches.
    if ($numNotFiltered) {
      $settings['libraryList']['notCached'] = $this->getNotCachedSettings($numNotFiltered);
    }

    $settings['containerSelector'] = '#h5p-admin-container';

    // Add the needed css and javascript
    $module_path = drupal_get_path('module', 'h5p');
    $build['#attached'] = $this->addSettings($module_path, $settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.list';

    $build['title_add'] =  array('#markup' => '<h3 class="h5p-admin-header">' . t('Add libraries') . '</h3>');
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\h5p\Form\H5PLibraryUploadForm');
    $build['title_installed'] =  array('#markup' => '<h3 class="h5p-admin-header">' . t('Installed libraries') . '</h3>');
    $build['container'] = array('#markup' => '<div id="h5p-admin-container"></div>');

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
    return array(
      'num' => $num,
      'url' => $url,
      'message' => t('Not all content has gotten their cache rebuilt. This is required to be able to delete libraries, and to display how many contents that uses the library.'),
      // todo $JM format_plural
      // 'progress' => format_plural($num, '1 content need to get its cache rebuilt.', '@count contents needs to get their cache rebuilt.'),
      'progress' => 'content need to get its cache rebuilt.',
      'button' => t('Rebuild cache')
    );
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
    $query->fields('l', array('title', 'machine_name', 'major_version', 'minor_version', 'patch_version', 'runnable', 'fullscreen'));
    $query->condition('l.library_id', $library_id, '=');
    $library = $query->execute()->fetchObject();

    // Build library info
    $settings['libraryInfo']['info'] = array(
      'Name' => $library->title,
      'Machine name' => $library->machine_name,
      'Version' => \H5PCore::libraryVersion($library),
      'Runnable' => $library->runnable ? t('Yes') : t('No'),
      'Fullscreen' => $library->fullscreen ? t('Yes') : t('No'),
    );

    // Build the translations needed
    $settings['libraryInfo']['translations'] = array(
      'contentCount' => t('Content count'),
      'noContent' => t('No content is using this library'),
      'contentHeader' => t('Content using this library'),
      'pageSizeSelectorLabel' => t('Elements per page'),
      'filterPlaceholder' => t('Filter content'),
      'pageXOfY' => t('Page $x of $y'),
    );

    $h5p_drupal = H5PDrupal::getInstance('interface');
    $numNotFiltered = $h5p_drupal->getNumNotFiltered();
    if ($numNotFiltered) {
      $settings['libraryInfo']['notCached'] = $this->getNotCachedSettings($numNotFiltered);

    } else {

      // Build a list of the content using this library
      $query = $this->database->select('h5p_content_libraries', 'l');
      $query->distinct();
      $query->fields('n', array('nid', 'title'));
      //$query->join('h5p_nodes', 'hn', 'l.content_id = hn.content_id');

      $query->join('node_field_data', 'n', 'hn.nid = n.nid');
      $query->condition('l.library_id', $library_id, '=');
      $query->orderBy('n.title', 'ASC');
      $nodes_res = $query->execute();

      foreach($nodes_res as $node) {
        $node_url = Url::fromUri('internal:/node/' . $node->nid);
        $settings['libraryInfo']['content'][] = array(
          'title' => $node->title,
          'url' => $node_url->toString(),
        );
      }
    }

    // Add the needed css and javascript
    $module_path = drupal_get_path('module', 'h5p');
    $settings['containerSelector'] = '#h5p-admin-container';
    $build['#attached'] = $this->addSettings($module_path, $settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.details';
    $build['container'] = array('#markup' => '<div id="h5p-admin-container"></div>');
    $build['#cache']['max-age'] = 0;

    return $build;
  }*/

  /**
   * Callback for the library content upgrade page.
   *
   * @param int $library_id
   * @return string HTML
   */
  function libraryUpgrade($library_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      h5p_content_upgrade_progress($library_id);
      return;
    }

    $core = H5PDrupal::getInstance('core');

    //$results = db_query('SELECT hl2.library_id as id, hl2.machine_name as name, hl2.title, hl2.major_version, hl2.minor_version, hl2.patch_version FROM {h5p_libraries} hl1
    // JOIN {h5p_libraries} hl2 ON hl1.machine_name = hl2.machine_name WHERE hl1.library_id = :id ORDER BY hl2.title ASC, hl2.major_version ASC, hl2.minor_version ASC', array(':id' => $library_id));

    $query = $this->database->select('h5p_libraries', 'hl1');
    $query->join('h5p_libraries', 'hl2', 'hl1.machine_name = hl2.machine_name');
    $query->condition('hl1.library_id', $library_id, '=');
    $query->fields('hl2', array('library_id', 'machine_name', 'title', 'major_version', 'minor_version', 'patch_version'));
    $query->orderBy('hl2.title', 'ASC');
    $query->orderBy('hl2.major_version', 'ASC');
    $query->orderBy('hl2.minor_version', 'ASC');
    $results = $query->execute();

    $versions = array();
    foreach ($results as $result) {
      $versions[$result->id] = $result;
    }
    $library = $versions[$library_id];
    $upgrades = $core->getUpgrades($library, $versions);

    drupal_set_title(t('Upgrade @library content', array('@library' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')')));
    if (count($versions) < 2) {
      return array('#markup' => t("There are no available upgrades for this library."));
    }

    // Get num of contents that can be upgraded
    $contents = $core->h5pF->getNumContent($library_id);
    if (!$contents) {
      return array('#markup' => t("There's no content instances to upgrade."));
    }

    $contents_plural = format_plural($contents, '1 content instance', '@count content instances');

    // Add JavaScript settings
    $return = filter_input(INPUT_GET, 'destination');
    $settings = array(
      'libraryInfo' => array(
        'message' => t('You are about to upgrade %num. Please select upgrade version.', array('%num' => $contents_plural)),
        'inProgress' => t('Upgrading to %ver...'),
        'error' => t('An error occurred while processing parameters:'),
        'errorData' => t('Could not load data for library %lib.'),
        'errorScript' => t('Could not load upgrades script for %lib.'),
        'errorContent' => t('Could not upgrade content %id:'),
        'errorParamsBroken' => t('Parameters are broken.'),
        'done' => t('You have successfully upgraded %num.', array('%num' => $contents_plural)) . ($return ? ' ' . l(t('Return'), $return) : ''),
        'library' => array(
          'name' => $library->name,
          'version' => $library->major_version . '.' . $library->minor_version,
        ),
        'libraryBaseUrl' => url('admin/content/h5p/upgrade/library'),
        'scriptBaseUrl' => base_path() . drupal_get_path('module', 'h5p') . '/library/js',
        'buster' => '?' . \Drupal::state()->get('css_js_query_string') ?: '',
        'versions' => $upgrades,
        'contents' => $contents,
        'buttonLabel' => t('Upgrade'),
        'infoUrl' => url('admin/content/h5p/libraries/' . $library_id . '/upgrade'),
        'total' => $contents,
        'token' => \H5PCore::createToken('contentupgrade'), // Use token to avoid unauthorized updating
      )
    );

    // Add JavaScripts
    $module_path = drupal_get_path('module', 'h5p');
    _h5p_admin_add_generic_css_and_js($module_path, $settings);
    drupal_add_js($module_path . '/library/js/h5p-version.js');
    drupal_add_js($module_path . '/library/js/h5p-content-upgrade.js');

    return '<div id="h5p-admin-container">' . t('Please enable JavaScript.') . '</div>';
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



  /**
   * Callback for rebuilding all content cache.
   */
  function rebuildCache() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      drupal_set_message(t('HTTP POST is required.'), 'error');
      drupal_set_title(t('Error'));
      return '';
    }

    // Do as many as we can in ten seconds.
    $start = microtime(TRUE);

    $core = H5PDrupal::getInstance('core');

    // $contents = db_query("SELECT content_id FROM {h5p_nodes} WHERE filtered = ''");
    $query = $this->database->select('h5p_nodes', 'n'); // TODO: Use H5PContent entity
    $query->fields('n', array('content_id'));
    $query->condition('n.filtered', '', '=');
    $num_rows = $query->countQuery()->execute()->fetchField();
    $result = $query->execute();

    $done = 0;
    foreach ($result as $row) {
      $content = $core->loadContent($row->content_id);
      $core->filterParameters($content);
      $done++;

      if ((microtime(TRUE) - $start) > 10) {
        break;
      }
    }

    $count = $num_rows - $done;
    return array('#markup' =>  $count);
  }


  /**
   * Creates the title for the library details page
   *
   * @param integer $library_id
   */
  function libraryDetailsTitle($library_id) {

    // return db_query('SELECT title FROM {h5p_libraries} where library_id = :id', array(':id' => $library_id))->fetchField();

    $query = $this->database->select('h5p_libraries', 'l');
    $query->condition('l.library_id', $library_id, '=');
    $query->fields('l', array('title'));
    return $query->execute()->fetchField();
  }

  /**
   * Helper function - adds admin css and js
   *
   * @param {string} $module_path The H5P path
   */
  function addSettings($module_path, $settings = NULL) {

    if ($settings === NULL) {
      $settings = array();
    }

    $settings['containerSelector'] = '#h5p-admin-container';
    $settings['l10n'] = array(
      'NA' => t('N/A'),
      /*'viewLibrary' => t('View library details'),*/
      'deleteLibrary' => t('Delete library'),
      'upgradeLibrary' => t('Upgrade library content')
    );

    $build['drupalSettings']['h5p']['drupal_h5p_admin_integration'] = [
      'H5PAdminIntegration' =>  $settings,
    ];
    $build['drupalSettings']['h5p']['drupal_h5p'] = [
      'H5P' => H5PDrupal::getGenericH5PIntegrationSettings(),
    ];

    return $build;
  }

}

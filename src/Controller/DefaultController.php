<?php /**
 * @file
 * Contains \Drupal\h5p\Controller\DefaultController.
 */

namespace Drupal\h5p\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the h5p module.
 */
class DefaultController extends ControllerBase {

  public function h5p_library_list() {
    _h5p_check_settings();

    $core = _h5p_get_instance('core');
    $numNotFiltered = $core->h5pF->getNumNotFiltered();
    $libraries = $core->h5pF->loadLibraries();

    // Add settings for each library
    $settings = [];
    $i = 0;
    foreach ($libraries as $versions) {
      foreach ($versions as $library) {
        $usage = $core->h5pF->getLibraryUsage($library->id, $numNotFiltered ? TRUE : FALSE);
        if ($library->runnable) {
          $upgrades = $core->getUpgrades($library, $versions);
          $upgradeUrl = empty($upgrades) ? FALSE : Drupal::url('h5p.content_upgrade', ['library_id' => $library->id], array('query' => drupal_get_destination()));

          $restricted = ($library->restricted === '1' ? TRUE : FALSE);
        $restricted_url = Drupal::url('h5p.restrict_library_callback', ['library_id' => $library->id], array(
            'query' => array(
              'token' => h5p_get_token('library_' . $i),
              'token_id' => $i,
              'restrict' => ($library->restricted === '1' ? 0 : 1)
            )
          ));
        }
        else {
          $upgradeUrl = NULL;
          $restricted = NULL;
          $restricted_url = NULL;
        }

        $settings['libraryList']['listData'][] = array(
          'title' => $library->title . ' (' . H5PCore::libraryVersion($library) . ')',
          'restricted' => $restricted,
          'restrictedUrl' => $restricted_url,
          'numContent' => $core->h5pF->getNumContent($library->id),
          'numContentDependencies' => $usage['content'] === -1 ? '' : $usage['content'],
          'numLibraryDependencies' => $usage['libraries'],
          'upgradeUrl' => $upgradeUrl,
          'detailsUrl' => Drupal::url('h5p.library_details', ['library_id' => $library->id]),
          'deleteUrl' => Drupal::url('h5p.library_delete', ['library_id' => $library->id], array('query' => drupal_get_destination()))
        );

        $i++;
      }
    }

    // All translations are made server side
    $settings['libraryList']['listHeaders'] = [
      t('Title'),
      t('Restricted'),
      t('Instances'),
      t('Instance Dependencies'),
      t('Library dependencies'),
      t('Actions'),
    ];

    // Make it possible to rebuild all caches.
    if ($numNotFiltered) {
      $settings['libraryList']['notCached'] = h5p_get_not_cached_settings($numNotFiltered);
    }

    // Add the needed css and javascript
    $module_path = drupal_get_path('module', 'h5p');
    _h5p_admin_add_generic_css_and_js($module_path, $settings);
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_js($module_path . '/library/js/h5p-library-list.js');


    $upgrade_all_libraries = variable_get('h5p_unsupported_libraries', NULL) === NULL ? '' : '<p>' . t('Click <a href="@url">here</a> to upgrade all installed libraries', array('@url' => Drupal::url('h5p.library_list'))) . '</p>';


    // Create the container which all admin content
    // will appends it's data to. This id is used by h5pintegration
    // to find where to put the admin content.
    $upload_form = \Drupal::formBuilder()->getForm('h5p_library_upload_form');
    return '<h3 class="h5p-admin-header">' . t('Add libraries') . '</h3>' . \Drupal::service("renderer")->render($upload_form) . '<h3 class="h5p-admin-header">' . t('Installed libraries') . '</h3>' . $upgrade_all_libraries . '<div id="h5p-admin-container"></div>';
  }

  public function _h5p_library_details_title($library_id) {
    return db_query('SELECT title FROM {h5p_libraries} where library_id = :id', [
      ':id' => $library_id
      ])->fetchField();
  }

  public function h5p_library_details($library_id) {
    $settings = [];

    $library = db_query('SELECT title, machine_name, major_version, minor_version, patch_version, runnable, fullscreen FROM {h5p_libraries} where library_id = :id', [
      'id' => $library_id
      ])->fetchObject();

    // Build library info
    $settings['libraryInfo']['info'] = [
      t('Name') => $library->title,
      t('Machine name') => $library->machine_name,
      t('Version') => H5PCore::libraryVersion($library),
      t('Runnable') => $library->runnable ? t('Yes') : t('No'),
      t('Fullscreen') => $library->fullscreen ? t('Yes') : t('No'),
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

    $h5p_drupal = _h5p_get_instance('interface');
    $numNotFiltered = $h5p_drupal->getNumNotFiltered();
    if ($numNotFiltered) {
      $settings['libraryInfo']['notCached'] = h5p_get_not_cached_settings($numNotFiltered);
    }
    else {
      // Build a list of the content using this library
      $nodes_res = db_query('SELECT DISTINCT n.nid, n.title FROM {h5p_nodes_libraries} l join {h5p_nodes} hn on l.content_id = hn.content_id join {node} n on hn.nid = n.nid  where library_id = :id order by n.title', [
        ':id' => $library_id
        ]);
      foreach ($nodes_res as $node) {
        $settings['libraryInfo']['content'][] = array(
          'title' => $node->title,
          'url' => Drupal::url('entity.node.canonical', ['node' => $node->nid]),
        );
      }
    }

    $module_path = drupal_get_path('module', 'h5p');
    _h5p_admin_add_generic_css_and_js($module_path, $settings);
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_js($module_path . '/library/js/h5p-library-details.js');


    // Create the container which all admin content
    // will appends it's data to. This id is used by h5pintegration
    // to find where to put the admin content.
    return '<div id="h5p-admin-container"></div>';
  }

  public function h5p_content_upgrade($library_id) {
    if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
      h5p_content_upgrade_progress($library_id);
      return;
    }
    $core = _h5p_get_instance('core');

    $results = db_query('SELECT hl2.library_id as id, hl2.machine_name as name, hl2.title, hl2.major_version, hl2.minor_version, hl2.patch_version FROM {h5p_libraries} hl1 JOIN {h5p_libraries} hl2 ON hl1.machine_name = hl2.machine_name WHERE hl1.library_id = :id ORDER BY hl2.title ASC, hl2.major_version ASC, hl2.minor_version ASC', [
      ':id' => $library_id
      ]);
    $versions = [];
    foreach ($results as $result) {
      $versions[$result->id] = $result;
    }
    $library = $versions[$library_id];
    $upgrades = $core->getUpgrades($library, $versions);

    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    // 
    // 
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Upgrade @library content', array('@library' => $library->title . ' (' . H5PCore::libraryVersion($library) . ')')));

    if (count($versions) < 2) {
      return t("There are no available upgrades for this library.");
    }

    // Get num of contents that can be upgraded
    $contents = $core->h5pF->getNumContent($library_id);
    if (!$contents) {
      return t("There's no content instances to upgrade.");
    }

    $contents_plural = \Drupal::translation()->formatPlural($contents, '1 content instance', '@count content instances');

    // Add JavaScript settings
    $settings = array(
      'libraryInfo' => array(
        'message' => t('You are about to upgrade %num. Please select upgrade version.', array('%num' => $contents_plural)),
        'inProgress' => t('Upgrading to %ver...'),
        'error' => t('An error occurred while processing parameters:'),
        'errorData' => t('Could not load data for library %lib.'),
        'errorScript' => t('Could not load upgrades script for %lib.'),
        'errorContent' => t('Could not upgrade content %id:'),
        'errorParamsBroken' => t('Parameters are broken.'),
        'done' => t('You have successfully upgraded %num.', array('%num' => $contents_plural)) . l(t('Back to library overview'), 'h5p.library_list'),
        'library' => array(
          'name' => $library->name,
          'version' => $library->major_version . '.' . $library->minor_version,
        ),
        'libraryBaseUrl' => Drupal::url('h5p.content_upgrade_library_base'),
        'scriptBaseUrl' => base_path() . drupal_get_path('module', 'h5p') . '/library/js',
        'buster' => '?' . Drupal::state()->get('system.css_js_query_string'),
        'versions' => $upgrades,
        'contents' => $contents,
        'buttonLabel' => t('Upgrade'),
        'infoUrl' => url('h5p.content_upgrade', ['library_id' => $library_id]),
        'total' => $contents,
        'token' => h5p_get_token('content_upgrade'), // Use token to avoid unauthorized updating
      )
    );


    // Add JavaScripts
    $module_path = drupal_get_path('module', 'h5p');
    _h5p_admin_add_generic_css_and_js($module_path, $settings);
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_js($module_path . '/library/js/h5p-version.js');

    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_js($module_path . '/library/js/h5p-content-upgrade.js');


    return '<div id="h5p-admin-container">' . t('Please enable JavaScript.') . '</div>';
  }

  public function h5p_library_delete($library_id) {

    // Is library deletable ?
    $h5p_drupal = _h5p_get_instance('interface');
    $notCached = $h5p_drupal->getNumNotFiltered();
    $library_usage = $h5p_drupal->getLibraryUsage($library_id, $notCached ? TRUE : FALSE);
    if ($library_usage['content'] === 0 && $library_usage['libraries'] === 0) {
      // Create form:
      return \Drupal::formBuilder()->getForm('h5p_library_delete_form', $library_id, _h5p_library_details_title($library_id));
    }
    else {
      // May not delete this one
      return t('Library is in use by content, or is dependent by other librarie(s), and can therefore not be deleted');
    }
  }

  public function h5p_restrict_library_callback($library_id) {
    $restricted = filter_input(INPUT_GET, 'restrict');
    $restrict = ($restricted === '1');

    $token_id = filter_input(INPUT_GET, 'token_id');
    if (!h5p_verify_token('library_' . $token_id, filter_input(INPUT_GET, 'token')) || (!$restrict && $restricted !== '0')) {
      return MENU_ACCESS_DENIED;
    }

    db_update('h5p_libraries')
      ->fields(['restricted' => $restricted])
      ->condition('library_id', $library_id)
      ->execute();

    print json_encode(array(
      'url' => Drupal::url('h5p.restrict_library_callback', ['library_id' => $library_id] . '/restrict', array(
        'query' => array(
          'token' => h5p_get_token('library_' . $token_id),
          'token_id' => $token_id,
          'restrict' => ($restrict ? 0 : 1),
        )
      )),
    ));
  }

  public function h5p_content_upgrade_library($name, $major, $minor) {
    $library = (object) [
      'name' => $name,
      'version' => (object) [
        'major' => $major,
        'minor' => $minor,
      ],
    ];

    $core = _h5p_get_instance('core');
    $library->semantics = $core->loadLibrarySemantics($library->name, $library->version->major, $library->version->minor);
    if ($library->semantics === NULL) {
      drupal_not_found();
    }

    if ($core->development_mode & H5PDevelopment::MODE_LIBRARY) {
      $dev_lib = $core->h5pD->getLibrary($library->name, $library->version->major, $library->version->minor);
    }

    $upgrades_script = _h5p_get_h5p_path() . (isset($dev_lib) ? '/' . $dev_lib['path'] : '/libraries/' . $library->name . '-' . $library->version->major . '.' . $library->version->minor) . '/upgrades.js';
    if (file_exists($upgrades_script)) {
      $library->upgradesScript = base_path() . $upgrades_script;
    }

    drupal_add_http_header('Cache-Control', 'no-cache');
    drupal_add_http_header('Content-type', 'application/json');
    print json_encode($library);
  }

  public function h5p_rebuild_cache() {
    if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') !== 'POST') {
      drupal_set_message(t('HTTP POST is required.'), 'error');
      // @FIXME
      // drupal_set_title() has been removed. There are now a few ways to set the title
      // dynamically, depending on the situation.
      // 
      // 
      // @see https://www.drupal.org/node/2067859
      // drupal_set_title(t('Error'));

      return '';
    }

    // Do as many as we can in ten seconds.
    $start = microtime(TRUE);

    $core = _h5p_get_instance('core');
    $contents = db_query("SELECT content_id FROM {h5p_nodes} WHERE filtered = ''");
    $done = 0;
    while ($content_id = $contents->fetchField()) {
      $content = $core->loadContent($content_id);
      $core->filterParameters($content);
      $done++;

      if ((microtime(TRUE) - $start) > 10) {
        break;
      }
    }

    print ($contents->rowCount() - $done);
  }

  public function h5p_embed($node) {
    $language = \Drupal::languageManager()->getCurrentLanguage();

    $callback = filter_input(INPUT_GET, 'callback');
    if ($callback !== NULL) {

      // Old embed code only returns resizer url
      print $callback . '(\'' . h5p_get_resize_url() . '\');';
      return;
    }

    $node = \Drupal::entityManager()->getStorage('node')->load($node);
    if (!$node || !node_access('view', $node) || !isset($node->json_content)) {
      print '<body style="margin:0"><div style="background: #fafafa url(http://h5p.org/sites/all/themes/professional_themec/images/h5p.svg) no-repeat center;background-size: 50% 50%;width: 100%;height: 100%;"></div><div style="width:100%;position:absolute;top:75%;text-align:center;color:#434343;font-family: Consolas,monaco,monospace">' . t('Content unavailable.') . '</div></body>';
      return;
    }

    $cache_buster = '?' . Drupal::state()->get('system.css_js_query_string');


    // Get core settings
    $settings = h5p_get_core_settings();

    $module_path = base_path() . drupal_get_path('module', 'h5p');

    // Get core scripts
    $scripts = [];
    foreach (H5PCore::$scripts as $script) {
      $scripts[] = $module_path . '/library/' . $script . $cache_buster;
    }

    // Get core styles
    $styles = [];
    foreach (H5PCore::$styles as $style) {
      $styles[] = $module_path . '/library/' . $style . $cache_buster;
    }

    // Get integration object
    $integration = h5p_get_core_settings();

    // Get content object
    $content = h5p_get_content($node);

    // Add content to integration
    $integration['contents']['cid-' . $content['id']] = h5p_get_content_settings($content);

    // Get content assets
    $core = _h5p_get_instance('core');
    $preloaded_dependencies = $core->loadContentDependencies($content['id'], 'preloaded');
    $files = $core->getDependenciesFiles($preloaded_dependencies, _h5p_get_h5p_path());
    $library_list = _h5p_dependencies_to_library_list($preloaded_dependencies);

    $mode = 'external';
    \Drupal::moduleHandler()->alter('h5p_scripts', $files['scripts'], $library_list, $mode);
    \Drupal::moduleHandler()->alter('h5p_styles', $files['styles'], $library_list, $mode);

    $scripts = array_merge($scripts, $core->getAssetsUrls($files['scripts']));
    $styles = array_merge($styles, $core->getAssetsUrls($files['styles']));

    $lang = $language->language;
    include('library/embed.php');
  }

  public function h5p_access_set_finished(Drupal\Core\Session\AccountInterface $account) {
    $id = filter_input(INPUT_POST, 'contentId', FILTER_VALIDATE_INT);
    return $id ? h5p_content_access($id) : FALSE;
  }

  public function h5p_set_finished() {
    $user = \Drupal::currentUser();
    $result = ['success' => FALSE];

    if ($user->uid && $_POST['contentId'] !== NULL && $_POST['score'] !== NULL && $_POST['maxScore'] !== NULL) {
      db_update('h5p_points')
        ->fields([
        'finished' => time(),
        'points' => $_POST['score'],
        'max_points' => $_POST['maxScore'],
      ])
        ->condition('content_id', $_POST['contentId'])
        ->condition('uid', $user->uid)
        ->execute();
      $result['success'] = TRUE;
    }

    echo json_encode($result);
  }

  public function h5p_content_user_data_access($content_id, Drupal\Core\Session\AccountInterface $account) {
    $user = \Drupal::currentUser();

    // Only logged in users can have user data
    return ($user->uid ? h5p_content_access($content_id) : FALSE);
  }

  public function h5p_content_user_data($content_id, $data_id, $sub_content_id) {
    $user = \Drupal::currentUser();

    $response = (object) ['success' => TRUE];

    if (\Drupal::config('h5p.settings')->get('h5p_revisioning')) {
      // We got vid, but we need nid. Let's ask DB
      $content_main_id = (int) db_query('SELECT nid FROM {node_revision} WHERE vid = :vid', [
        ':vid' => $content_id
        ])->fetchField();
    }
    else {
      $content_main_id = $content_id;
    }

    $data = filter_input(INPUT_POST, 'data');
    $preload = filter_input(INPUT_POST, 'preload');
    $invalidate = filter_input(INPUT_POST, 'invalidate');
    if ($data !== NULL && $preload !== NULL && $invalidate !== NULL) {
      if ($data === '0') {
        // Remove data
        db_delete('h5p_content_user_data')
          ->condition('content_main_id', $content_main_id)
          ->condition('data_id', $data_id)
          ->condition('user_id', $user->uid)
          ->condition('sub_content_id', $sub_content_id)
          ->execute();
      }
      else {
        // Wash values to ensure 0 or 1.
        $preload = ($preload === '0' ? 0 : 1);
        $invalidate = ($invalidate === '0' ? 0 : 1);

        // Determine if we should update or insert
        $update = db_query("SELECT content_main_id
                                   FROM {h5p_content_user_data}
                                   WHERE content_main_id = :content_main_id
                                     AND user_id = :user_id
                                     AND data_id = :data_id
                                     AND sub_content_id = :sub_content_id", [
          ':content_main_id' => $content_main_id,
          ':user_id' => $user->uid,
          ':data_id' => $data_id,
          ':sub_content_id' => $sub_content_id,
        ])->fetchField();

        if ($update === FALSE) {
          // Insert new data
          db_insert('h5p_content_user_data')
            ->fields([
            'user_id' => $user->uid,
            'content_main_id' => $content_main_id,
            'sub_content_id' => $sub_content_id,
            'data_id' => $data_id,
            'timestamp' => time(),
            'data' => $data,
            'preloaded' => $preload,
            'delete_on_content_change' => $invalidate,
          ])
            ->execute();
        }
        else {
          // Update old data
          db_update('h5p_content_user_data')
            ->fields([
            'timestamp' => time(),
            'data' => $data,
            'preloaded' => $preload,
            'delete_on_content_change' => $invalidate,
          ])
            ->condition('user_id', $user->uid)
            ->condition('content_main_id', $content_main_id)
            ->condition('data_id', $data_id)
            ->condition('sub_content_id', $sub_content_id)
            ->execute();
        }
      }
    }
    else {
      // Fetch data
      $response->data = db_query("SELECT data FROM {h5p_content_user_data}
                                WHERE user_id = :user_id
                                  AND content_main_id = :content_main_id
                                  AND data_id = :data_id
                                  AND sub_content_id = :sub_content_id", [
        ':user_id' => $user->uid,
        ':content_main_id' => $content_main_id,
        ':sub_content_id' => $sub_content_id,
        ':data_id' => $data_id,
      ])->fetchField();
    }

    drupal_add_http_header('Cache-Control', 'no-cache');
    drupal_add_http_header('Content-type', 'application/json; charset=utf-8');
    print json_encode($response);
  }

}

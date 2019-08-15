<?php

namespace Drupal\h5p\H5PDrupal;

use Drupal\h5p\Entity\H5PContent;
use Drupal\h5peditor\H5PEditor;
use Drupal\Core\Url;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Component\Utility\UrlHelper;

class H5PDrupal implements \H5PFrameworkInterface {
  private $h5pPath, $folderPath;

  /**
   *  Store these options in State API instead of config.
   */
  const STATE_OPTIONS = [
    'content_type_cache_updated_at',
    'fetched_library_metadata_on',
  ];
  /**
   * Get an instance of one of the h5p library classes
   *
   * @staticvar H5PDrupal $interface
   *  The interface between the H5P library and drupal
   * @staticvar H5PCore $core
   *  Core functions and storage in the h5p library
   * @param string $type
   *  Specifies the instance to be returned; validator, storage, interface or core
   * @return \H5PCore|\H5PValidator|\H5PStorage|\H5PContentValidator|\H5PExport|\Drupal\h5p\H5PDrupal\H5PDrupal
   *  The instance og h5p specified by type
   */
  public static function getInstance($type = 'interface', $instance = 'default') {
    static $instances;

    if (!isset($instances) || !isset($instances[$instance])) {
      // Not present in runtime cache – create new instances
      $interface = new self();

      // Determine language
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

      // Prepare file storage
      $h5p_path = $interface->getOption('default_path', 'h5p');
      $fs = new \H5PDefaultStorage(\Drupal::service('file_system')->realpath("public://{$h5p_path}"));

      // Determine if exports should be generated
      $is_export_enabled = !!$interface->getOption('export', TRUE);
      $core = new \H5PCore($interface, $fs, base_path(), $language, $is_export_enabled);

      // Add to runtime cache
      $instances[$instance] = [$interface, $core];
    }
    else {
      // Get runtime cache
      list($interface, $core) = $instances[$instance];
    }

    switch ($type) {
      case 'validator':
        return new \H5PValidator($interface, $core);
      case 'storage':
        return new \H5PStorage($interface, $core);
      case 'contentvalidator':
        return new \H5PContentValidator($interface, $core);
      case 'export':
        return new \H5PExport($interface, $core);
      default:
      case 'interface':
        return $interface;
      case 'core':
        return $core;
    }
  }

  /**
   * Grabs the relative URL to H5P files folder.
   *
   * @return string
   */
  public static function getRelativeH5PPath() {
    $interface = self::getInstance();
    return PublicStream::basePath() . '/' . $interface->getOption('default_path', 'h5p');
  }

  /**
   * Prepares the generic H5PIntegration settings
   */
  public static function getGenericH5PIntegrationSettings() {
    static $settings;

    if (!empty($settings)) {
      return $settings; // Only needs to be generated the first time
    }

    // Load current user
    $user = \Drupal::currentUser();

    // Load configuration settings
    $interface = self::getInstance();
    $h5p_save_content_state = $interface->getOption('save_content_state', FALSE);
    $h5p_save_content_frequency = $interface->getOption('save_content_frequency', 30);
    $h5p_hub_is_enabled = $interface->getOption('hub_is_enabled', TRUE);

    // Create AJAX URLs
    $set_finished_url = Url::fromUri('internal:/h5p-ajax/set-finished.json', ['query' => ['token' => \H5PCore::createToken('result')]])->toString(TRUE)->getGeneratedUrl();
    $content_user_data_url = Url::fromUri('internal:/h5p-ajax/content-user-data/:contentId/:dataType/:subContentId', ['query' => ['token' => \H5PCore::createToken('contentuserdata')]])->toString(TRUE)->getGeneratedUrl();
    $h5p_url = base_path() . self::getRelativeH5PPath();

    // Define the generic H5PIntegration settings
    $core = self::getInstance('core');
    $settings = array(
      'baseUrl' => base_path(),
      'url' => $h5p_url,
      'postUserStatistics' => $user->id() > 0,
      'ajax' => array(
        'setFinished' => $set_finished_url,
        'contentUserData' => str_replace('%3A', ':', $content_user_data_url),
      ),
      'saveFreq' => $h5p_save_content_state ? $h5p_save_content_frequency : FALSE,
      'l10n' => array(
        'H5P' => $core->getLocalization(),
      ),
      'hubIsEnabled' => $h5p_hub_is_enabled,
      'reportingIsEnabled' => ($interface->getOption('enable_lrs_content_types', FALSE) === 1) ? TRUE : FALSE,
      'libraryConfig' => $core->h5pF->getLibraryConfig(),
      'pluginCacheBuster' => '?' . \Drupal::state()->get('system.css_js_query_string', '0'),
      'libraryUrl' => base_path() . drupal_get_path('module', 'h5p') . '/vendor/h5p/h5p-core/js',
    );

    if ($user->id()) {
      $settings['user'] = [
        'name' => $user->getAccountName(),
        'mail' => $user->getEmail(),
      ];
    }
    else {
      $settings['siteUrl'] = Url::fromUri('internal:/', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
    }

    return $settings;
  }

  /**
   * Get a list with prepared asset links that is used when JS loads components.
   *
   * @param array [$keys] Optional keys, first for JS second for CSS.
   * @return array
   */
  public static function getCoreAssets($keys = NULL) {
    if (empty($keys)) {
      $keys = ['scripts', 'styles'];
    }

    // Prepare arrays
    $assets = [
      $keys[0] => [],
      $keys[1] => [],
    ];

    // Determine cache buster
    $cache_buster = \Drupal::state()->get('system.css_js_query_string', '0');
    $h5p_module_path = drupal_get_path('module', 'h5p');

    // Add all core scripts
    foreach (\H5PCore::$scripts as $script) {
      $assets[$keys[0]][] = "{$h5p_module_path}/vendor/h5p/h5p-core/{$script}?{$cache_buster}";
    }

    // and styles
    foreach (\H5PCore::$styles as $style) {
      $assets[$keys[1]][] = "{$h5p_module_path}/vendor/h5p/h5p-core/{$style}?{$cache_buster}";
    }

    return $assets;
  }

  /**
   *
   */
  public static function aggregatedAssets($scriptAssets, $styleAssets) {
    $jsOptimizer = \Drupal::service('asset.js.collection_optimizer');
    $cssOptimizer = \Drupal::service('asset.css.collection_optimizer');
    $systemPerformance = \Drupal::config('system.performance');
    $jsAssetConfig = ['preprocess' => $systemPerformance->get('js.preprocess')];
    $cssAssetConfig = ['preprocess' => $systemPerformance->get('css.preprocess'), 'media' => 'css'];
    $assets = ['scripts' => [], 'styles' => []];
    foreach ($scriptAssets as $jsFiles) {
      $assets['scripts'][] = self::createCachedPublicFiles($jsFiles, $jsOptimizer, $jsAssetConfig);
    }
    foreach ($styleAssets as $cssFiles) {
      $assets['styles'][] = self::createCachedPublicFiles($cssFiles, $cssOptimizer, $cssAssetConfig);
    }
    return $assets;
  }

  /**
   * Combines a set of files to a cached version, that is public available
   *
   * @param string[] $filePaths
   * @param AssetCollectionOptimizerInterface $optimizer
   * @param array $assetConfig
   *
   * @return string[]
   */
  private static function createCachedPublicFiles(array $filePaths, $optimizer, $assetConfig) {
    $assets = [];

    $defaultAssetConfig = [
      'type' => 'file',
      'group' => 'h5p',
      'cache' => TRUE,
      'attributes' => [],
      'version' => NULL,
      'browsers' => [],
    ];

    foreach ($filePaths as $index => $path) {
      $path = explode('?', $path)[0];

      $assets[$path] = [
        'weight' => count($filePaths) - $index,
        'data' => $path,
      ] + $assetConfig + $defaultAssetConfig;
    }
    $cachedAsset = $optimizer->optimize($assets);

    return array_map(function($publicUrl){ return file_create_url($publicUrl); }, array_column($cachedAsset, 'data'));
  }

  /**
   * Clean up outdated events.
   */
  public function removeOldLogEvents() {
    $older_than = (time() - \H5PEventBase::$log_time);

    db_delete('h5p_events')
      ->condition('created_at', $older_than, '<')
      ->execute();
  }

  /**
   * Keeps the libraries metadata cache up-to-date.
   */
  public function fetchLibrariesMetadata($fetchingDisabled = FALSE) {

    $hub_is_enabled = $this->getOption('hub_is_enabled', TRUE);
    $send_usage_statistics = $this->getOption('send_usage_statistics', TRUE);
    $last_fetched_at = intval($this->getOption('fetched_library_metadata_on', 0));

    if ($fetchingDisabled || (($hub_is_enabled) || $send_usage_statistics) &&
        ($last_fetched_at < (time() - 86400))) {
      // Fetch the library-metadata:
      $core = H5PDrupal::getInstance('core');
      $core->fetchLibrariesMetadata($fetchingDisabled);
      $this->setOption('fetched_library_metadata_on', time());
    }
  }

  /**
   * Implements getPlatformInfo
   */
  public function getPlatformInfo() {

    $h5p_info = system_get_info('module', 'h5p');

    return [
      'name' => 'drupal',
      'version' => \DRUPAL::VERSION,
      'h5pVersion' => isset($h5p_info['version']) ? $h5p_info['version'] : NULL,
    ];
  }

  /**
   * Implements fetchExternalData
   */
  public function fetchExternalData($url, $data = NULL, $blocking = TRUE, $stream = NULL) {

    $options = [];
    if (!empty($data)) {
      $options['headers'] = [
        'Content-Type' => 'application/x-www-form-urlencoded'
      ];
      $options['form_params'] = $data;
    }

    if ($stream) {
      @set_time_limit(0);
    }

    try {
      $client = \Drupal::httpClient();
      $response = $client->request(empty($data) ? 'GET' : 'POST', $url, $options);
      $response_data = (string) $response->getBody();
      if (empty($response_data)) {
        return FALSE;
      }

    }
    catch (\Exception $e) {
      $this->setErrorMessage($e->getMessage(), 'failed-fetching-external-data');
      return FALSE;
    }

    if ($stream && empty($response->error)) {
      // Create file from data
      H5PEditor\H5peditorDrupalStorage::saveFileTemporarily($response_data);
      // TODO: Cannot rely on H5PEditor module – Perhaps we could use the
      // save_to/sink option to save directly to file when streaming ?
      // http://guzzle.readthedocs.io/en/latest/request-options.html#sink-option
      return TRUE;
    }

    return $response_data;
  }

  /**
   * Implements setLibraryTutorialUrl
   *
   * Set the tutorial URL for a library. All versions of the library is set
   *
   * @param string $machineName
   * @param string $tutorialUrl
   */
  public function setLibraryTutorialUrl($machineName, $tutorialUrl) {
    db_update('h5p_libraries')
      ->fields([
        'tutorial_url' => $tutorialUrl,
      ])
      ->condition('machine_name', $machineName)
      ->execute();
  }

  /**
   * Kesps track of messages for the user.
   * @var array
   */
  private $messages = array('error' => array(), 'info' => array());

  /**
   * Implements setErrorMessage
   */
  public function setErrorMessage($message, $code = NULL) {
    $this->messages['error'][] = (object)array(
      'code' => $code,
      'message' => $message
    );
    drupal_set_message($message, 'error');
  }

  /**
   * Implements setInfoMessage
   */
  public function setInfoMessage($message) {
    $this->messages['info'][] = $message;
    drupal_set_message($message);
  }

  /**
   * Implements getMessages
   */
  public function getMessages($type) {
    if (empty($this->messages[$type])) {
      return NULL;
    }
    $messages = $this->messages[$type];
    $this->messages[$type] = array();
    drupal_get_messages($type === 'info' ? 'status' : $type, TRUE); // Prevent messages from displaying twice
    return $messages;
  }

  /**
   * Implements t
   */
  public function t($message, $replacements = []) {
    return t($message, $replacements);
  }

  /**
   * Implements getLibraryFileUrl
   */
  public function getLibraryFileUrl($libraryFolderName, $fileName) {
    // Misplaced; this is something that Core should be able to handle.
    return base_path() . self::getRelativeH5PPath() . "/libraries/{$libraryFolderName}/{$fileName}";
  }

  /**
   * Implements getUploadedH5PFolderPath
   */
  public function getUploadedH5pFolderPath($set = NULL) {
    if (!empty($set)) {
      $this->folderPath = $set;
    }

    return $this->folderPath;
  }

  /**
   * Implements getUploadedH5PPath
   */
  public function getUploadedH5pPath($set = NULL) {
    if (!empty($set)) {
      $this->h5pPath = $set;
    }

    return $this->h5pPath;
  }

  /**
   * Implements loadLibraries
   */
  public function loadLibraries() {
    $res = db_query(
        "SELECT library_id AS id,
                machine_name AS name,
                title,
                major_version, minor_version, patch_version,
                runnable, restricted
           FROM {h5p_libraries}
       ORDER BY title ASC,
                major_version ASC,
                minor_version ASC"
    );

    $libraries = [];
    foreach ($res as $library) {
      $libraries[$library->name][] = $library;
    }

    return $libraries;
  }

  /**
   * Implements getAdminUrl
   */
  public function getAdminUrl() {
    // Misplaced; not used by Core.
    $url = Url::fromUri('internal:/admin/content/h5p')->toString();
    return $url;
  }

  /**
   * Implements getLibraryId
   */
  public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL) {
    $library_id = db_query(
        "SELECT library_id
           FROM {h5p_libraries}
          WHERE machine_name = :machine_name
            AND major_version = :major_version
            AND minor_version = :minor_version",
        [
          ':machine_name' => $machineName,
          ':major_version' => $majorVersion,
          ':minor_version' => $minorVersion
        ]
    )->fetchField();

    return $library_id;
  }

  /**
   * Implements isPatchedLibrary
   */
  public function isPatchedLibrary($library) {
    if ($this->getOption('dev_mode', FALSE)) {
      return TRUE;
    }

    $result = db_query(
        "SELECT 1
           FROM {h5p_libraries}
          WHERE machine_name = :machineName
            AND major_version = :majorVersion
            AND minor_version = :minorVersion
            AND patch_version < :patchVersion",
        [
          ':machineName' => $library['machineName'],
          ':majorVersion' => $library['majorVersion'],
          ':minorVersion' => $library['minorVersion'],
          ':patchVersion' => $library['patchVersion']
        ]
    )->fetchField();
    return $result === '1';
  }

  /**
   * Implements isInDevMode
   */
  public function isInDevMode() {
    $h5p_dev_mode = $this->getOption('dev_mode', FALSE);
    return (bool) $h5p_dev_mode;
  }

  /**
   * Implements mayUpdateLibraries
   */
  public function mayUpdateLibraries() {

    // Get the current user
    $user = \Drupal::currentUser();
    // Check for permission
    return $user->hasPermission('update h5p libraries');
  }

  /**
   * Implements getLibraryUsage
   *
   * Get number of content using a library, and the number of
   * dependencies to other libraries
   *
   * @param int $libraryId
   * @return array The array contains two elements, keyed by 'content' and 'libraries'.
   *               Each element contains a number
   */
  public function getLibraryUsage($libraryId, $skipContent = FALSE) {
    $usage = [];

    if ($skipContent) {
      $usage['content'] = -1;
    }
    else {
      $usage['content'] = intval(db_query(
          "SELECT COUNT(distinct nfd.id)
             FROM {h5p_libraries} l
             JOIN {h5p_content_libraries} nl
               ON l.library_id = nl.library_id
             JOIN {h5p_content} nfd
               ON nl.content_id = nfd.id
            WHERE l.library_id = :id",
          [
            ':id' => $libraryId
          ]
      )->fetchField());
    }

    $usage['libraries'] = intval(db_query(
        "SELECT COUNT(*)
           FROM {h5p_libraries_libraries}
          WHERE required_library_id = :id",
        [':id' => $libraryId]
    )->fetchField());

    return $usage;
  }

  /**
   * Implements getLibraryContentCount
   *
   * Get a key value list of library version and count of content created
   * using that library.
   *
   * @return array
   *  Array containing library, major and minor version - content count
   *  e.g. "H5P.CoursePresentation 1.6" => "14"
   */
  public function getLibraryContentCount() {
    $contentCount = [];

    // Count content with same machine name, major and minor version
    $results = db_query(
        "SELECT l.machine_name AS name,
                l.major_version AS major,
                l.minor_version AS minor,
                count(*) AS count
           FROM {h5p_content} c,
                {h5p_libraries} l
          WHERE c.library_id = l.library_id
       GROUP BY l.machine_name,
                l.major_version,
                l.minor_version"
    );

    // Format results
    foreach($results as $library) {
      $contentCount["{$library->name} {$library->major}.{$library->minor}"] = $library->count;
    }

    return $contentCount;
  }

  /**
   * Implements getLibraryStats
   */
  public function getLibraryStats($type) {
    $count = [];

    $results = db_query(
        "SELECT library_name AS name,
                library_version AS version,
                num
           FROM {h5p_counters}
          WHERE type = :type",
        [
          ':type' => $type
        ]
    )->fetchAll();

    // Extract results
    foreach($results as $library) {
      $count["{$library->name} {$library->version}"] = $library->num;
    }

    return $count;
  }

  /**
   * Implements getNumAuthors
   */
  public function getNumAuthors() {

    $id = db_query(
        "SELECT id
           FROM {h5p_content}
          LIMIT 1")->fetchField();

    // Return 1 if there is content and 0 if there is none
    return empty($id) ? 0 : 1;
  }

  /**
   * Implements saveLibraryData
   *
   * @param array $libraryData
   * @param boolean $new
   */
  public function saveLibraryData(&$libraryData, $new = TRUE) {
    $preloadedJs = $this->pathsToCsv($libraryData, 'preloadedJs');
    $preloadedCss =  $this->pathsToCsv($libraryData, 'preloadedCss');
    $dropLibraryCss = '';

    if (isset($libraryData['dropLibraryCss'])) {
      $libs = array();
      foreach ($libraryData['dropLibraryCss'] as $lib) {
        $libs[] = $lib['machineName'];
      }
      $dropLibraryCss = implode(', ', $libs);
    }

    $embedTypes = '';
    if (isset($libraryData['embedTypes'])) {
      $embedTypes = implode(', ', $libraryData['embedTypes']);
    }
    if (!isset($libraryData['semantics'])) {
      $libraryData['semantics'] = '';
    }
    if (!isset($libraryData['fullscreen'])) {
      $libraryData['fullscreen'] = 0;
    }
    if (!isset($libraryData['hasIcon'])) {
      $libraryData['hasIcon'] = 0;
    }

    if ($new) {
      $libraryId = db_insert('h5p_libraries')
        ->fields(array(
          'machine_name' => $libraryData['machineName'],
          'title' => $libraryData['title'],
          'major_version' => $libraryData['majorVersion'],
          'minor_version' => $libraryData['minorVersion'],
          'patch_version' => $libraryData['patchVersion'],
          'runnable' => $libraryData['runnable'],
          'fullscreen' => $libraryData['fullscreen'],
          'embed_types' => $embedTypes,
          'preloaded_js' => $preloadedJs,
          'preloaded_css' => $preloadedCss,
          'drop_library_css' => $dropLibraryCss,
          'semantics' => $libraryData['semantics'],
          'has_icon' => $libraryData['hasIcon'] ? 1 : 0,
          'metadata_settings' => $libraryData['metadataSettings'],
          'add_to' => isset($libraryData['addTo']) ? json_encode($libraryData['addTo']) : NULL,
        ))
        ->execute();
      $libraryData['libraryId'] = $libraryId;
      if ($libraryData['runnable']) {
        $h5p_first_runnable_saved = $this->getOption('first_runnable_saved', FALSE);
        if (! $h5p_first_runnable_saved) {
          $this->setOption('first_runnable_saved', 1);
        }
      }
    }
    else {
      db_update('h5p_libraries')
        ->fields(array(
          'title' => $libraryData['title'],
          'patch_version' => $libraryData['patchVersion'],
          'runnable' => $libraryData['runnable'],
          'fullscreen' => $libraryData['fullscreen'],
          'embed_types' => $embedTypes,
          'preloaded_js' => $preloadedJs,
          'preloaded_css' => $preloadedCss,
          'drop_library_css' => $dropLibraryCss,
          'semantics' => $libraryData['semantics'],
          'has_icon' => $libraryData['hasIcon'] ? 1 : 0,
          'metadata_settings' => $libraryData['metadataSettings'],
          'add_to' => isset($libraryData['addTo']) ? json_encode($libraryData['addTo']) : NULL,
        ))
        ->condition('library_id', $libraryData['libraryId'])
        ->execute();
      $this->deleteLibraryDependencies($libraryData['libraryId']);
    }

    // Log library installed or updated
    new H5PEvent('library', ($new ? 'create' : 'update'),
      NULL, NULL,
      $libraryData['machineName'],
      $libraryData['majorVersion'] . '.' . $libraryData['minorVersion']
    );

    // invoke library installed
    \Drupal::moduleHandler()->invokeAll('h5p_library_installed', array($libraryData, $new));

    db_delete('h5p_libraries_languages')
      ->condition('library_id', $libraryData['libraryId'])
      ->execute();
    if (isset($libraryData['language'])) {
      foreach ($libraryData['language'] as $languageCode => $languageJson) {
        $id = db_insert('h5p_libraries_languages')
          ->fields(array(
            'library_id' => $libraryData['libraryId'],
            'language_code' => $languageCode,
            'language_json' => $languageJson,
          ))
          ->execute();
      }
    }
    \Drupal::cache()->delete('h5p_library_info_build');
  }

  /**
   * Convert list of file paths to csv
   *
   * @param array $libraryData
   *  Library data as found in library.json files
   * @param string $key
   *  Key that should be found in $libraryData
   * @return string
   *  file paths separated by ', '
   */
  private function pathsToCsv($libraryData, $key) {
    if (isset($libraryData[$key])) {
      $paths = array();
      foreach ($libraryData[$key] as $file) {
        $paths[] = $file['path'];
      }
      return implode(', ', $paths);
    }
    return '';
  }

  public function lockDependencyStorage() {
    if (db_driver() === 'mysql') {
      // Only works for mysql, other DBs will have to use transactions.

      // db_transaction often deadlocks, we do it more brutally...
      db_query('LOCK TABLES {h5p_libraries_libraries} write, {h5p_libraries} as hl read');
    }
  }

  public function unlockDependencyStorage() {
    if (db_driver() === 'mysql') {
      db_query('UNLOCK TABLES');
    }
  }

  /**
   * Implements deleteLibraryDependencies
   */
  public function deleteLibraryDependencies($libraryId) {
    db_delete('h5p_libraries_libraries')
      ->condition('library_id', $libraryId)
      ->execute();
  }

  /**
   * Implements deleteLibrary. Will delete a library's data both in the database and file system
   */
  public function deleteLibrary($libraryId) {
    $library = db_query(
        "SELECT *
           FROM {h5p_libraries}
          WHERE library_id = :id",
        [
          ':id' => $libraryId
        ]
    )->fetchObject();

    // Delete files
    \H5PCore::deleteFileTree(self::getRelativeH5PPath() . "/libraries/{$library->machine_name}-{$library->major_version}.{$library->minor_version}");

    // Delete data in database (won't delete content)
    db_delete('h5p_libraries_libraries')->condition('library_id', $libraryId)->execute();
    db_delete('h5p_libraries_languages')->condition('library_id', $libraryId)->execute();
    db_delete('h5p_libraries')->condition('library_id', $libraryId)->execute();
  }

  /**
   * Implements saveLibraryDependencies
   */
  public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type) {
    foreach ($dependencies as $dependency) {
      $query = db_select('h5p_libraries', 'hl');
      $query->addExpression($libraryId);
      $query->addField('hl', 'library_id');
      $query->addExpression("'{$dependency_type}'");
      $query->condition('machine_name', $dependency['machineName']);
      $query->condition('major_version', $dependency['majorVersion']);
      $query->condition('minor_version', $dependency['minorVersion']);

      db_insert('h5p_libraries_libraries')
        /*
         * TODO: The order of the required_library_id and library_id below is reversed,
         * to match the order of the fields in the select statement. We should rather
         * try to control the order of the fields in the select statement or something.
         */
        ->fields(array('required_library_id', 'library_id', 'dependency_type'))
        ->from($query)
        ->execute();
    }
  }

  /**
   * Implements updateContent
   */
  public function updateContent($content, $contentMainId = NULL) {
    // Load existing entity
    $h5p_content = H5PContent::load($content['id']);

    // Update properties
    $h5p_content->set('library_id', $content['library']['libraryId']);
    $h5p_content->set('parameters', $content['params']);
    $h5p_content->set('disabled_features', $content['disable']);
    $h5p_content->set('filtered_parameters', '');

    // Update metadata properties
    $metadata_fields = \H5PMetadata::toDBArray($content['metadata']);
    foreach ($metadata_fields as $key => $value) {
      $h5p_content->set($key, $value);
    }

    // Save changes
    $h5p_content->save();

    // Log update event
    self::logContentEvent('update', $content);
  }

  /**
   * Implements insertContent
   */
  public function insertContent($content, $contentMainId = NULL) {
    $fields = array_merge(\H5PMetadata::toDBArray($content['metadata']), [
      'library_id' => $content['library']['libraryId'],
      'parameters' => $content['params'],
      'disabled_features' => $content['disable']
    ]);

    // Create new entity for content
    $h5p_content = H5PContent::create($fields);

    // Save
    $h5p_content->save();

    // Grab id of new entitu
    $content['id'] = $h5p_content->id();

    // Log create event
    self::logContentEvent('create', $content);

    // Return content id of the new entity
    return $content['id'];
  }

  /**
   * Help log content events
   *
   * @param string $eventType
   * @param array $content
   */
  private static function logContentEvent($eventType, $content) {
    if (!empty($content['uploaded'])) {
      $eventType .= ' upload';
    }
    new H5PEvent('content', $eventType,
      $content['id'], '',
      $content['library']['machineName'],
      $content['library']['majorVersion'] . '.' . $content['library']['minorVersion']
    );
  }

  /**
   * Implements resetContentUserData
   */
  public function resetContentUserData($contentId) {
    // Reset user datas for this content
    db_update('h5p_content_user_data')
      ->fields(array(
        'timestamp' => time(),
        'data' => 'RESET'
      ))
      ->condition('content_main_id', $contentId)
      ->condition('delete_on_content_change', 1)
      ->execute();
  }

  /**
   * Implements getWhitelist
   */
  public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist) {
    // Misplaced; should be done by Core.
    $h5p_whitelist = $this->getOption('whitelist', $defaultContentWhitelist);
    $whitelist = $h5p_whitelist;
    if ($isLibrary) {
      $h5p_library_whitelist_extras = $this->getOption('library_whitelist_extras', $defaultLibraryWhitelist);
      $whitelist .= ' ' . $h5p_library_whitelist_extras;
    }
    return $whitelist;

  }

  /**
   * Implements copyLibraryUsage
   */
  public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL) {
    db_query(
        "INSERT INTO {h5p_content_libraries}
                     (content_id, library_id, dependency_type, drop_css, weight)
              SELECT :toId, hnl.library_id, hnl.dependency_type, hnl.drop_css, hnl.weight
                FROM {h5p_content_libraries} hnl
               WHERE hnl.content_id = :fromId",
        [
          ':toId' => $contentId,
          ':fromId' => $copyFromId
        ]
    );
  }

  /**
   * Implements deleteContentData
   */
  public function deleteContentData($contentId) {
    // Delete library usage
    $this->deleteLibraryUsage($contentId);

    // Remove content points
    db_delete('h5p_points')
      ->condition('content_id', $contentId)
      ->execute();

    // Remove content user data
    db_delete('h5p_content_user_data')
      ->condition('content_main_id', $contentId)
      ->execute();
  }

  /**
   * Implements deleteLibraryUsage
   */
  public function deleteLibraryUsage($contentId) {
    db_delete('h5p_content_libraries')
      ->condition('content_id', $contentId)
      ->execute();
  }

  /**
   * Implements saveLibraryUsage
   */
  public function saveLibraryUsage($contentId, $librariesInUse) {
    $dropLibraryCssList = array();
    foreach ($librariesInUse as $dependency) {
      if (!empty($dependency['library']['dropLibraryCss'])) {
        $dropLibraryCssList = array_merge($dropLibraryCssList, explode(', ', $dependency['library']['dropLibraryCss']));
      }
    }
    foreach ($librariesInUse as $dependency) {
      $dropCss = in_array($dependency['library']['machineName'], $dropLibraryCssList) ? 1 : 0;
      db_insert('h5p_content_libraries')
        ->fields(array(
          'content_id' => $contentId,
          'library_id' => $dependency['library']['libraryId'],
          'dependency_type' => $dependency['type'],
          'drop_css' => $dropCss,
          'weight' => $dependency['weight'],
        ))
        ->execute();
    }
  }

  /**
   * Implements loadLibrary
   */
  public function loadLibrary($machineName, $majorVersion, $minorVersion) {
    $library = db_query(
        "SELECT library_id,
                machine_name,
                title,
                major_version,
                minor_version,
                patch_version,
                embed_types,
                preloaded_js,
                preloaded_css,
                drop_library_css,
                fullscreen,
                runnable,
                semantics,
                tutorial_url,
                has_icon
          FROM {h5p_libraries}
          WHERE machine_name = :machine_name
          AND major_version = :major_version
          AND minor_version = :minor_version",
        [
          ':machine_name' => $machineName,
          ':major_version' => $majorVersion,
          ':minor_version' => $minorVersion
        ]
    )->fetchObject();

    if ($library === FALSE) {
      return FALSE;
    }
    $library = \H5PCore::snakeToCamel($library);

    // Load dependencies
    $result = db_query(
        "SELECT hl.machine_name AS name,
                hl.major_version AS major,
                hl.minor_version AS minor,
                hll.dependency_type AS type
           FROM {h5p_libraries_libraries} hll
           JOIN {h5p_libraries} hl
             ON hll.required_library_id = hl.library_id
          WHERE hll.library_id = :library_id",
        [
          ':library_id' => $library['libraryId']
        ]
    );

    foreach ($result as $dependency) {
      $library["{$dependency->type}Dependencies"][] = [
        'machineName' => $dependency->name,
        'majorVersion' => $dependency->major,
        'minorVersion' => $dependency->minor,
      ];
    }

    return $library;
  }

  /**
   * Implements loadLibrarySemantics().
   */
  public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion) {
    $semantics = db_query(
        "SELECT semantics
           FROM {h5p_libraries}
          WHERE machine_name = :machine_name
            AND major_version = :major_version
            AND minor_version = :minor_version",
        [
          ':machine_name' => $machineName,
          ':major_version' => $majorVersion,
          ':minor_version' => $minorVersion
        ]
    )->fetchField();

    return ($semantics === FALSE ? NULL : $semantics);
  }

  /**
   * Implements alterLibrarySemantics().
   */
  public function alterLibrarySemantics(&$semantics, $name, $majorVersion, $minorVersion) {
    // alter only takes 4 arguments, so versions are combined to single parameter
    $version = $majorVersion . '.'. $minorVersion;
    \Drupal::moduleHandler()->alter('h5p_semantics', $semantics, $name, $version);
  }

  /**
   * Implements loadContent().
   */
  public function loadContent($id) {

    // Not sure if we really need this since the content is loaded when the
    // content entity is loaded.
  }

  /**
   * Implements loadContentDependencies().
   */
  public function loadContentDependencies($id, $type = NULL) {
    $query = "SELECT hl.library_id,
                     hl.machine_name,
                     hl.major_version, hl.minor_version, hl.patch_version,
                     hl.preloaded_css, hl.preloaded_js, hnl.drop_css,
                     hnl.dependency_type
                FROM {h5p_content_libraries} hnl
                JOIN {h5p_libraries} hl
                  ON hnl.library_id = hl.library_id
               WHERE hnl.content_id = :id";
    $queryArgs = [':id' => $id];

    if ($type !== NULL) {
      $query .= " AND hnl.dependency_type = :dt";
      $queryArgs[':dt'] = $type;
    }
    $query .= " ORDER BY hnl.weight";
    $result = db_query($query, $queryArgs);

    $dependencies = [];
    while ($dependency = $result->fetchObject()) {
      $dependencies[] = \H5PCore::snakeToCamel($dependency);
    }

    return $dependencies;
  }

  /**
   * Get stored setting.
   *
   * @param string $name
   *   Identifier for the setting
   * @param string $default
   *   Optional default value if settings is not set
   * @return mixed
   *   Whatever has been stored as the setting
   */
  public function getOption($name, $default = NULL) {
    if ($this->stateOption($name)) {
      $value = \Drupal::state()->get('h5p.' . $name, $default);
    }
    else {
      $value = \Drupal::config('h5p.settings')->get('h5p_' . $name);
    }
    return $value !== NULL ? $value : $default;
  }

  /**
   * Stores the given setting.
   *
   * @param string $name
   *   Identifier for the setting
   * @param mixed $value Data
   *   Whatever we want to store as the setting
   */
  public function setOption($name, $value) {
    // Only update the setting if it has infact changed.
    if ($value !== $this->getOption($name)) {
      if ($this->stateOption($name)) {
        \Drupal::state()->set('h5p.' . $name, $value);
      }
      else {
        $config = \Drupal::configFactory()->getEditable('h5p.settings');
        $config->set("h5p_{$name}", $value);
        $config->save();
      }
    }
  }

  /**
   * Returns whether to store this variable in Drupal's state api, or config.
   *
   * @param string $name
   *   Key for the name
   * @return boolean
   */
  protected function stateOption($name) {
    return in_array($name, self::STATE_OPTIONS);
  }
  /**
   * Convert variables to fit our DB.
   */
  private static function camelToString($input) {
    $input = preg_replace('/[a-z0-9]([A-Z])[a-z0-9]/', '_$1', $input);
    return strtolower($input);
  }

  /**
   * Implements updateContentFields().
   */
  public function updateContentFields($id, $fields) {
    if (!isset($fields['filtered'])) {
      return;
    }

    $h5p_content = H5PContent::load($id);
    $h5p_content->set('filtered_parameters', $fields['filtered']);
    $h5p_content->save();
  }

  /**
   * Will clear filtered params for all the content that uses the specified
   * library. This means that the content dependencies will have to be rebuilt,
   * and the parameters refiltered.
   *
   * @param array $library_ids
   */
  public function clearFilteredParameters($library_ids) {

    // Grab all H5PContent entities
    $h5p_contents = \Drupal::entityTypeManager()
        ->getStorage('h5p_content')
        ->loadByProperties(['library_id' => $library_ids]);

    // Clear their filtered_parameters
    foreach ($h5p_contents as $h5p_content) {
      $h5p_content->set('filtered_parameters', '');
      $h5p_content->save();
    }

    // Clear hook_library_info_build() to use updated libraries
    \Drupal::service('library.discovery.collector')->clear();

    // Delete ALL cached JS and CSS files
    \Drupal::service('asset.js.collection_optimizer')->deleteAll();
    \Drupal::service('asset.css.collection_optimizer')->deleteAll();

    // Reset cache buster
    _drupal_flush_css_js();

    // Clear field view cache for ALL H5P content
    \Drupal\Core\Cache\Cache::invalidateTags(['h5p_content']);
  }

  /**
   * Get number of contents that has to get their content dependencies rebuilt
   * and parameters refiltered.
   *
   * @return int
   */
  public function getNumNotFiltered() {
    return intval(db_query("SELECT COUNT(id) FROM {h5p_content} WHERE filtered_parameters IS NULL AND library_id > 0")->fetchField());
  }

  /**
   * Implements getNumContent.
   */
  public function getNumContent($library_id, $skip = NULL) {
    $skip_query = empty($skip) ? '' : " AND id NOT IN ($skip)";
    return intval(db_query('SELECT COUNT(id) FROM {h5p_content} WHERE library_id = :id' . $skip_query, [':id' => $library_id])->fetchField());
  }

  /**
   * Implements isContentSlugAvailable
   */
  public function isContentSlugAvailable($slug) {
    return !db_query('SELECT slug FROM {h5p_content} WHERE slug = :slug', [':slug' => $slug])->fetchField();
  }

  /**
   * Implements saveCachedAssets
   */
  public function saveCachedAssets($key, $libraries) {
  }

  /**
   * Implements deleteCachedAssets
   */
  public function deleteCachedAssets($library_id) {
  }

  /**
   * Implements afterExportCreated
   */
  public function afterExportCreated($content, $filename) {
  }

  /**
   * Implements hasPermission
   *
   * @param H5PPermission $permission
   * @param boolean $canUpdateEntity
   * @return bool
   */
  public function hasPermission($permission, $canUpdateEntity = NULL) {

    $user = \Drupal::currentUser();
    switch ($permission) {
      case \H5PPermission::COPY_H5P:
        return $canUpdateEntity !== NULL && (
            $user->hasPermission('copy all h5ps') ||
            ($canUpdateEntity && $user->hasPermission('copy own h5ps'))
          );

      case \H5PPermission::DOWNLOAD_H5P:
        return $canUpdateEntity !== NULL && (
            $user->hasPermission('download all h5ps') ||
            ($canUpdateEntity && $user->hasPermission('download own h5ps'))
          );

      case \H5PPermission::EMBED_H5P:
        return $canUpdateEntity !== NULL && (
            $user->hasPermission('embed all h5ps') ||
            ($canUpdateEntity && $user->hasPermission('embed own h5ps'))
          );

      case \H5PPermission::CREATE_RESTRICTED:
        return $user->hasPermission('create restricted h5p content types');

      case \H5PPermission::UPDATE_LIBRARIES:
        return $user->hasPermission('update h5p libraries');

      case \H5PPermission::INSTALL_RECOMMENDED:
        return $user->hasPermission('install recommended h5p libraries');
    }
    return FALSE;
  }

  /**
   * Replaces existing content type cache with the one passed in
   *
   * @param object $contentTypeCache Json with an array called 'libraries'
   *  containing the new content type cache that should replace the old one.
   */
  public function replaceContentTypeCache($contentTypeCache) {
    // Replace existing cache
    db_delete('h5p_libraries_hub_cache')
      ->execute();
    foreach ($contentTypeCache->contentTypes as $ct) {
      $created_at = new \DateTime($ct->createdAt);
      $updated_at = new \DateTime($ct->updatedAt);
      db_insert('h5p_libraries_hub_cache')
        ->fields(array(
          'machine_name' => $ct->id,
          'major_version' => $ct->version->major,
          'minor_version' => $ct->version->minor,
          'patch_version' => $ct->version->patch,
          'h5p_major_version' => $ct->coreApiVersionNeeded->major,
          'h5p_minor_version' => $ct->coreApiVersionNeeded->minor,
          'title' => $ct->title,
          'summary' => $ct->summary,
          'description' => $ct->description,
          'icon' => $ct->icon,
          'created_at' => $created_at->getTimestamp(),
          'updated_at' => $updated_at->getTimestamp(),
          'is_recommended' => $ct->isRecommended === TRUE ? 1 : 0,
          'popularity' => $ct->popularity,
          'screenshots' => json_encode($ct->screenshots),
          'license' => json_encode(isset($ct->license) ? $ct->license : array()),
          'example' => $ct->example,
          'tutorial' => isset($ct->tutorial) ? $ct->tutorial : '',
          'keywords' => json_encode(isset($ct->keywords) ? $ct->keywords : array()),
          'categories' => json_encode(isset($ct->categories) ? $ct->categories : array()),
          'owner' => $ct->owner
        ))
        ->execute();
    }
  }

  /**
   * Implements loadAddons
   */
  public function loadAddons() {
    $result = db_query("SELECT l1.library_id, l1.machine_name, l1.major_version, l1.minor_version, l1.patch_version, l1.add_to, l1.preloaded_js, l1.preloaded_css
                          FROM {h5p_libraries} l1
                     LEFT JOIN {h5p_libraries} l2 ON l1.machine_name = l2.machine_name AND
                                                     (l1.major_version < l2.major_version OR
                                                      (l1.major_version = l2.major_version AND
                                                       l1.minor_version < l2.minor_version))
                         WHERE l1.add_to IS NOT NULL
                           AND l2.machine_name IS NULL");

    // NOTE: These are treated as library objects but are missing the following properties:
    // title, embed_types, drop_library_css, fullscreen, runnable, semantics, has_icon

    $addons = array();
    while ($addon = $result->fetchObject()) {
      $addons[] = \H5PCore::snakeToCamel($addon);
    }
    return $addons;
  }

  /**
   * Implements getLibraryConfig
   */
  public function getLibraryConfig($libraries = NULL) {
    return $this->getOption('library_config', NULL);
  }

  /**
   * Implements libraryHasUpgrade
   */
  public function libraryHasUpgrade($library) {
    return !!db_query(
      "SELECT library_id
         FROM {h5p_libraries}
        WHERE machine_name = :name
          AND (major_version > :major
           OR (major_version = :major AND minor_version > :minor))
        LIMIT 1",
      array(
        ':name' => $library['machineName'],
        ':major' => $library['majorVersion'],
        ':minor' => $library['minorVersion']
      )
    )->fetchField();
  }
}

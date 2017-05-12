<?php

namespace Drupal\h5p\H5PDrupal;

use Drupal\h5peditor\H5PEditor;
use Drupal\core\Url;
use Drupal\Component\Utility\UrlHelper;

class H5PDrupal implements \H5PFrameworkInterface {

  /**
   * Get an instance of one of the h5p library classes
   *
   * This function stores the h5p core in a static variable so that the variables there will
   * be kept between validating and saving the node for instance
   *
   * @staticvar H5PDrupal $interface
   *  The interface between the H5P library and drupal
   * @staticvar H5PCore $core
   *  Core functions and storage in the h5p library
   * @param string $type
   *  Specifies the instance to be returned; validator, storage, interface or core
   * @return object
   *  The instance og h5p specified by type
   */
  public static function getInstance($type) {
    static $interface, $core;

    if (!isset($interface)) {
      // Create new instance of self
      $interface = new H5PDrupal();

      // Determine language
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

      // Prepare file storage
      $h5p_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p'; // TODO: Use \Drupal::config()->get() ?
      $fs = new \H5PDefaultStorage(\Drupal::service('file_system')->realpath("public://{$h5p_path}"));

      // Determine if exports should be generated
      $export_enabled = !!\Drupal::state()->get('h5p_export'); // TODO: Use \Drupal::config()->get() ?
      $core = new \H5PCore($interface, $fs, base_path(), $language, $is_export_enabled);
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
   * Implements getPlatformInfo
   */
  public function getPlatformInfo() {

    $h5p_info = system_get_info('module', 'h5p');

    return array(
      'name' => 'drupal',
      'version' => $h5p_info['core'],
      'h5pVersion' => isset($h5p_info['version']) ? $h5p_info['version'] : NULL,
    );
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
      //$data = 'uuid=3bcfef3f-3035-4ae8-bd2a-5f2da97b1754&platform_name=drupal&platform_version=7.53&h5p_version=7.x-1.28&disabled=0&local_id=dc0317b9&type=local&num_authors=0&libraries=%5B%5D&current_cache=0';
      $options['form_params'] = $data;
    }

    if ($stream) {
      @set_time_limit(0);
    }

    try {
      $client = \Drupal::httpClient();
      $response = $client->request('POST', $url, $options);
      $response_data = (string) $response->getBody();
      if (empty($response_data)) {
        return FALSE;
      }

    }
    catch (RequestException $e) {
      return FALSE;
    }

    if ($stream && empty($response->error)) {
      // Create file from data
      H5PEditor\H5peditorDrupalStorage::saveFileTemporarily($response_data);
      return TRUE;
    }

    return isset($response->error) ? NULL : $response_data;
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
      ->fields(array(
        'tutorial_url' => $tutorialUrl,
      ))
      ->condition('machine_name', $machineName)
      ->execute();
  }

  /**
   * Implements setErrorMessage
   */
  public function setErrorMessage($message) {

    $user = \Drupal::currentUser();
    if ($user->hasPermission('create h5p_content content')) {
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Implements setInfoMessage
   */
  public function setInfoMessage($message) {

    $user = \Drupal::currentUser();
    if ($user->hasPermission('create h5p_content content')) {
      drupal_set_message($message);
    }
  }


  /**
   * Implements t
   */
  public function t($message, $replacements = array()) {
    return t($message, $replacements);
  }

  /**
   * Implements getH5PPath
   */
  private function getH5pPath($external = FALSE) {
    return ($external ? base_path() : '') . _h5p_get_h5p_path();
  }

  /**
   * Implements getLibraryFileUrl
   */
  public function getLibraryFileUrl($libraryFolderName, $fileName) {
    return $this->getH5pPath(TRUE) . '/libraries/' . $libraryFolderName . '/' . $fileName;
  }

  /**
   * Implements getUploadedH5PFolderPath
   */
  public function getUploadedH5pFolderPath($set = NULL) {
    static $path;

    if (!empty($set)) {
      $path = $set;
    }

    return $path;
  }

  /**
   * Implements getUploadedH5PPath
   */
  public function getUploadedH5pPath($set = NULL) {
    static $path;

    if (!empty($set)) {
      $path = $set;
    }

    return $path;
  }

  /**
   * Implements loadLibraries
   */
  public function loadLibraries() {
    $res = db_query('SELECT library_id as id, machine_name as name, title, major_version, minor_version, patch_version, runnable, restricted FROM {h5p_libraries} ORDER BY title ASC, major_version ASC, minor_version ASC');

    $libraries = array();
    foreach ($res as $library) {
      $libraries[$library->name][] = $library;
    }

    return $libraries;
  }

  /**
   * Implements getAdminUrl
   */
  public function getAdminUrl() {
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
      array(':machine_name' => $machineName, ':major_version' => $majorVersion, ':minor_version' => $minorVersion))
      ->fetchField();
    return $library_id;
  }

  /**
   * Implements isPatchedLibrary
   */
  public function isPatchedLibrary($library) {
    $operator = $this->isInDevMode() ? '<=' : '<';
    $result = db_query(
      "SELECT 1
      FROM {h5p_libraries}
      WHERE machine_name = :machineName
      AND major_version = :majorVersion
      AND minor_version = :minorVersion
      AND patch_version $operator :patchVersion",
      array(
        ':machineName' => $library['machineName'],
        ':majorVersion' => $library['majorVersion'],
        ':minorVersion' => $library['minorVersion'],
        ':patchVersion' => $library['patchVersion']
      )
    )->fetchField();
    return $result === '1';
  }

  /**
   * Implements isInDevMode
   */
  public function isInDevMode() {
    $h5p_dev_mode = \Drupal::state()->get('h5p_dev_mode') ?: 0;
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
   * Get number of content/nodes using a library, and the number of
   * dependencies to other libraries
   *
   * @param int $libraryId
   * @return array The array contains two elements, keyed by 'content' and 'libraries'.
   *               Each element contains a number
   */
  public function getLibraryUsage($libraryId, $skipContent = FALSE) {
    $usage = array();

    $usage['content'] = $skipContent ? -1 : intval(db_query(
      'SELECT COUNT(distinct nfd.nid)
      FROM {h5p_libraries} l JOIN {h5p_nodes_libraries} nl ON l.library_id = nl.library_id JOIN {h5p_nodes} nfd ON nl.content_id = nfd.nid
      WHERE l.library_id = :id', array(':id' => $libraryId))->fetchField());

    $usage['libraries'] = intval(db_query("SELECT COUNT(*) FROM {h5p_libraries_libraries} WHERE required_library_id = :id", array(':id' => $libraryId))->fetchField());

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
    $contentCount = array();

    // Count content with same machine name, major and minor version
    $res = db_query(
      'SELECT machine_name, major_version, minor_version, count(*) as count
        FROM {h5p_nodes}, {h5p_libraries}
        WHERE main_library_id = library_id
        GROUP BY machine_name, major_version, minor_version'
    );

    // Extract results
    forEach($res as $lib) {
      $contentCount[$lib->machine_name.' '.$lib->major_version.'.'.$lib->minor_version] = $lib->count;
    }

    return $contentCount;
  }

  /**
   * Implements getLibraryStats
   */
  public function getLibraryStats($type) {
    $count = array();

    $results = db_query("
        SELECT library_name AS name,
               library_version AS version,
               num
          FROM {h5p_counters}
         WHERE type = :type
        ", array(
      ':type' => $type
    ))->fetchAll();

    // Extract results
    foreach($results as $library) {
      $count[$library->name . ' ' . $library->version] = $library->num;
    }

    return $count;
  }

  /**
   * Implements getNumAuthors
   */
  public function getNumAuthors() {
    $numAuthors = db_query("
      SELECT COUNT(DISTINCT uid)
      FROM {node_field_data}
      WHERE type = :type
    ", array(
      ':type' => 'h5p_content'
    ))->fetchField();

    return $numAuthors;
  }

  /**
   * Implements saveLibraryData
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
        ))
        ->execute();
      $libraryData['libraryId'] = $libraryId;
      if ($libraryData['runnable']) {
        $h5p_first_runnable_saved = \Drupal::state()->get('h5p_first_runnable_saved') ?: 0;
        if (! $h5p_first_runnable_saved) {
          h5p_variable_set('h5p_first_runnable_saved', 1);
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

    // Invoke h5p_library_installed hook for each library that has
    // been installed
    // todo $JM migrate to Drupal 8
    /*
    if (sizeof(module_implements('h5p_library_installed')) > 0) {
      module_invoke_all('h5p_library_installed', $libraryData, $new);
    }
    */

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
    $library = db_query("SELECT * FROM {h5p_libraries} WHERE library_id = :id", array(':id' => $libraryId))->fetchObject();

    // Delete files
    H5PCore::deleteFileTree(_h5p_get_h5p_path() . '/libraries/' . $library->machine_name . '-' . $library->major_version . '.' . $library->minor_version);

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
      $query->addExpression("'" . $dependency_type . "'");
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
    $content_id = db_query("SELECT content_id FROM {h5p_nodes} WHERE content_id = :content_id", array(':content_id' => $content['id']))->fetchField();
    if ($content_id === FALSE) {
      // This can happen in Drupal when module is reinstalled. (since the ID is predetermined)
      $this->insertContent($content, $contentMainId);
      return;
    }

    // Update content
    db_update('h5p_nodes')
      ->fields(array(
        'json_content' => $content['params'],
        'embed_type' => 'div', // TODO: Determine from library?
        'main_library_id' => $content['library']['libraryId'],
        'filtered' => '',
        'disable' => $content['disable']
      ))
      ->condition('content_id', $content_id)
      ->execute();

    // Derive library data from string
    if (isset($content['h5p_library'])) {
      $libraryData = explode(' ', $content['h5p_library']);
      $content['library']['machineName'] = $libraryData[0];
      $content['machineName'] = $libraryData[0];
      $libraryVersions = explode('.', $libraryData[1]);
      $content['library']['majorVersion'] = $libraryVersions[0];
      $content['library']['minorVersion'] = $libraryVersions[1];
    }

    // Determine event type
    $event_type = 'update';
    if (isset($_SESSION['h5p_upload'])) {
      $event_type .= ' upload';
    }

    // Log update event
    new H5PEvent('content', $event_type,
      $content['id'],
      $content['title'],
      $content['library']['machineName'],
      $content['library']['majorVersion'] . '.' . $content['library']['minorVersion']
    );
  }

  /**
   * Implements insertContent
   */
  public function insertContent($content, $contentMainId = NULL) {

    // Insert
    db_insert('h5p_nodes')
      ->fields(array(
        'content_id' => $content['id'],
        'nid' => $contentMainId,
        'json_content' => $content['params'],
        'embed_type' => 'div', // TODO: Determine from library?
        'main_library_id' => $content['library']['libraryId'],
        'disable' => $content['disable'],
        'filtered' => '',
        'slug' => ''
      ))
      ->execute();

    // Log update event
    $event_type = 'create';
    if (isset($_SESSION['h5p_upload'])) {
      $event_type .= ' upload';
    }
    new H5PEvent('content', $event_type,
      $content['id'],
      (isset($content['title']) ? $content['title'] : ''),
      $content['library']['machineName'],
      $content['library']['majorVersion'] . '.' . $content['library']['minorVersion']
    );

    return $content['id'];
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

    $h5p_whitelist = \Drupal::state()->get('h5p_whitelist') ?: $defaultContentWhitelist;
    $whitelist = $h5p_whitelist;
    if ($isLibrary) {
      $h5p_library_whitelist_extras = \Drupal::state()->get('h5p_library_whitelist_extras') ?: $defaultLibraryWhitelist;
      $whitelist .= ' ' . $h5p_library_whitelist_extras;
    }
    return $whitelist;

  }

  /**
   * Implements copyLibraryUsage
   */
  public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL) {
    db_query(
      "INSERT INTO {h5p_nodes_libraries} (content_id, library_id, dependency_type, drop_css, weight)
      SELECT :toId, hnl.library_id, hnl.dependency_type, hnl.drop_css, hnl.weight
      FROM {h5p_nodes_libraries} hnl
      WHERE hnl.content_id = :fromId", array(':toId' => $contentId, ':fromId' => $copyFromId)
    );
  }

  /**
   * Implements deleteContentData
   */
  public function deleteContentData($contentId) {
    db_delete('h5p_nodes')
      ->condition('content_id', $contentId)
      ->execute();
    $this->deleteLibraryUsage($contentId);
  }

  /**
   * Implements deleteLibraryUsage
   */
  public function deleteLibraryUsage($contentId) {
    db_delete('h5p_nodes_libraries')
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
      db_insert('h5p_nodes_libraries')
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
      array(
        ':machine_name' => $machineName,
        ':major_version' => $majorVersion,
        ':minor_version' => $minorVersion
      ))
      ->fetchObject();

    if ($library === FALSE) {
      return FALSE;
    }
    $library = \H5PCore::snakeToCamel($library);

    $result = db_query(
      "SELECT hl.machine_name AS name,
              hl.major_version AS major,
              hl.minor_version AS minor,
              hll.dependency_type AS type
        FROM {h5p_libraries_libraries} hll
        JOIN {h5p_libraries} hl ON hll.required_library_id = hl.library_id
        WHERE hll.library_id = :library_id",
      array(':library_id' => $library['libraryId']));

    foreach ($result as $dependency) {
      $library[$dependency->type . 'Dependencies'][] = array(
        'machineName' => $dependency->name,
        'majorVersion' => $dependency->major,
        'minorVersion' => $dependency->minor,
      );
    }
    if ($this->isInDevMode()) {
      $semantics = $this->getSemanticsFromFile($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      if ($semantics) {
        $library['semantics'] = $semantics;
      }
    }
    return $library;
  }

  private function getSemanticsFromFile($machineName, $majorVersion, $minorVersion) {
    $h5p_default_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p';
    $semanticsPath = \Drupal::service('file_system')->realpath('public://' . $h5p_default_path . '/libraries/' . $machineName . '-' . $majorVersion . '.' . $minorVersion . '/semantics.json');
    if (file_exists($semanticsPath)) {
      $semantics = file_get_contents($semanticsPath);
      if (!json_decode($semantics, TRUE)) {
        drupal_set_message(t('Invalid json in semantics for %library', array('%library' => $machineName)), 'warning');
      }
      return $semantics;
    }
    return FALSE;
  }

  /**
   * Implements loadLibrarySemantics().
   */
  public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion) {
    if ($this->isInDevMode()) {
      $semantics = $this->getSemanticsFromFile($machineName, $majorVersion, $minorVersion);
    }
    else {
      $semantics = db_query(
        "SELECT semantics
            FROM {h5p_libraries}
            WHERE machine_name = :machine_name
            AND major_version = :major_version
            AND minor_version = :minor_version",
        array(
          ':machine_name' => $machineName,
          ':major_version' => $majorVersion,
          ':minor_version' => $minorVersion
        ))->fetchField();
    }
    return ($semantics === FALSE ? NULL : $semantics);
  }

  /**
   * Implements alterLibrarySemantics().
   */
  public function alterLibrarySemantics(&$semantics, $name, $majorVersion, $minorVersion) {
    \Drupal::moduleHandler()->alter('h5p_semantics', $semantics, $name, $majorVersion, $minorVersion);
  }

  /**
   * Implements loadContent().
   */
  /**
   * Implements loadContent().
   */
  public function loadContent($id) {
    $content = db_query(
      "SELECT hn.content_id AS id,
                hn.json_content AS params,
                hn.embed_type,
                n.title,
                n.langcode,
                hl.library_id,
                hl.machine_name AS library_name,
                hl.major_version AS library_major_version,
                hl.minor_version AS library_minor_version,
                hl.embed_types AS library_embed_types,
                hl.fullscreen AS library_fullscreen,
                hn.filtered,
                hn.disable,
                hn.slug
          FROM {h5p_nodes} hn
          JOIN {node_field_data} n ON n.nid = hn.nid
          JOIN {h5p_libraries} hl ON hl.library_id = hn.main_library_id
          WHERE content_id = :id",
      array(
        ':id' => $id
      ))
      ->fetchObject();
    return ($content === FALSE ? NULL : \H5PCore::snakeToCamel($content));
  }


  /**
   * Implements loadContentDependencies().
   */
  public function loadContentDependencies($id, $type = NULL) {
    $query =
      "SELECT hl.library_id,
                hl.machine_name,
                hl.major_version,
                hl.minor_version,
                hl.patch_version,
                hl.preloaded_css,
                hl.preloaded_js,
                hnl.drop_css,
                hnl.dependency_type
          FROM {h5p_nodes_libraries} hnl
          JOIN {h5p_libraries} hl ON hnl.library_id = hl.library_id
          WHERE hnl.content_id = :id";
    $queryArgs = array(':id' => $id);

    if ($type !== NULL) {
      $query .= " AND hnl.dependency_type = :dt";
      $queryArgs[':dt'] = $type;
    }
    $query .= " ORDER BY hnl.weight";
    $result = db_query($query, $queryArgs);

    $dependencies = array();
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
    $h5p = \Drupal::state()->get('h5p_' . $name) ?: $default;
    return $h5p;
  }

  /**
   * Stores the given setting.
   * For example when did we last check h5p.org for updates to our libraries.
   *
   * @param string $name
   *   Identifier for the setting
   * @param mixed $value Data
   *   Whatever we want to store as the setting
   */
  public function setOption($name, $value) {

    h5p_variable_set('h5p_' . $name, $value);
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
    $processedFields = array();
    foreach ($fields as $name => $value) {
      $processedFields[self::camelToString($name)] = $value;
    }

    db_update('h5p_nodes')
      ->fields($processedFields)
      ->condition('content_id', $id)
      ->execute();
  }

  /**
   * Will clear filtered params for all the content that uses the specified
   * library. This means that the content dependencies will have to be rebuilt,
   * and the parameters refiltered.
   *
   * @param int $library_id
   */
  public function clearFilteredParameters($library_id) {
    db_update('h5p_nodes')
      ->fields(array(
        'filtered' => '',
      ))
      ->condition('main_library_id', $library_id)
      ->execute();

    _drupal_flush_css_js();
    drupal_clear_js_cache();
    drupal_clear_css_cache();
  }

  /**
   * Get number of contents that has to get their content dependencies rebuilt
   * and parameters refiltered.
   *
   * @return int
   */
  public function getNumNotFiltered() {
    return intval(db_query("SELECT COUNT(content_id) FROM {h5p_nodes} WHERE filtered = '' AND main_library_id > 0")->fetchField());
  }

  /**
   * Implements getNumContent.
   */
  public function getNumContent($library_id) {
    return intval(db_query('SELECT COUNT(content_id) FROM {h5p_nodes} WHERE main_library_id = :id', array(':id' => $library_id))->fetchField());
  }

  /**
   * Implements isContentSlugAvailable
   */
  public function isContentSlugAvailable($slug) {
    return !db_query('SELECT slug FROM {h5p_nodes} WHERE slug = :slug', array(':slug' => $slug))->fetchField();
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
   * Helper function to determine access
   *
   * @param int $nid
   * @return boolean
   */
  private static function mayCurrentUserUpdateNode($nid) {
    return node_access('update', node_load($nid));
  }

  /**
   * Implements hasPermission
   *
   * @param H5PPermission $permission
   * @param int $content_id
   * @return bool
   */
  public function hasPermission($permission, $content_id = NULL) {

    $user = \Drupal::currentUser();
    switch ($permission) {
      case \H5PPermission::DOWNLOAD_H5P:
        return $content_id !== NULL && (
            $user->hasPermission('download all h5ps') ||
            (self::mayCurrentUserUpdateNode($content_id) && $user->hasPermission('download own h5ps'))
          );

      case \H5PPermission::EMBED_H5P:
        return $content_id !== NULL && (
            $user->hasPermission('embed all h5ps') ||
            (self::mayCurrentUserUpdateNode($content_id) && $user->hasPermission('embed own h5ps'))
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
}

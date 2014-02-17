<?php

// TODO: Validate file endings against a setting for security
class H5PDrupal implements H5PFrameworkInterface {
  /**
   * Implements setErrorMessage
   */
  public function setErrorMessage($message) {
    if (user_access('create h5p')) {
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Implements setInfoMessage
   */
  public function setInfoMessage($message) {
    if (user_access('create h5p')) {
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
  public function getH5pPath() {
    return _h5p_get_h5p_path();
  }

  /**
   * Implements getUploadedH5PFolderPath
   */
  public function getUploadedH5pFolderPath() {
    return $_SESSION['h5p_upload_folder'];
  }

  /**
   * Implements getUploadedH5PPath
   */
  public function getUploadedH5pPath() {
    return $_SESSION['h5p_upload'];
  }
  
  /**
   * Implements getUploadedH5PSkipContent
   */
  public function getUploadedH5pSkipContent() {
    return ( isset($_SESSION['h5p_upload_skip_content']) === TRUE
             && $_SESSION['h5p_upload_skip_content'] === TRUE
             ? TRUE : FALSE );
  }

  /**
   * Implements getLibraryId
   */
  public function getLibraryId($machineName, $majorVersion, $minorVersion) {
    $library_id = db_result(db_query(
      "SELECT library_id
      FROM {h5p_libraries}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d",
      $machineName, $majorVersion, $minorVersion));
    return $library_id;
  }

  /**
   * Implements isPatchedLibrary
   */
  public function isPatchedLibrary($library) {
    $operator = $this->isInDevMode() ? '<=' : '<';
    $result = db_result(db_query(
      "SELECT 1
      FROM {h5p_libraries}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d
      AND patch_version $operator %d",
      $library['machineName'], $library['majorVersion'], $library['minorVersion'], $library['patchVersion']));
    return $result === '1';
  }

  /**
   * Implements isInDevMode
   */
  public function isInDevMode() {
    return (bool) variable_get('h5p_dev_mode', '0');
  }

  /**
   * Implements mayUpdateLibraries
   */
  public function mayUpdateLibraries() {
    return user_access('update h5p libraries');
  }

  /**
   * Implements getLibraryUsage
   *  
   * Get number of content/nodes using a library, and the number of 
   * dependencies to other libraries
   * 
   * @param int $library_id
   * @return array The array contains two elements, keyed by 'content' and 'libraries'. 
   *               Each element contains a number
   */
  public function getLibraryUsage($libraryId) {
    $usage = array();
    
    $usage['content'] = intval(db_result(db_query('SELECT COUNT(distinct n.nid)
                   FROM {h5p_libraries} l JOIN {h5p_nodes_libraries} nl ON l.library_id = nl.library_id JOIN {h5p_nodes} n ON nl.content_id = n.content_id
                   WHERE l.library_id = %d', $libraryId)));
    
    $usage['libraries'] = intval(db_result(db_query('SELECT COUNT(*) FROM {h5p_libraries_libraries} WHERE required_library_id = %d', $libraryId)));
    
    return $usage;
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
    if ($new) {
      db_query("INSERT INTO {h5p_libraries}
        (machine_name, title, major_version, minor_version, patch_version, runnable, fullscreen, embed_types, preloaded_js, preloaded_css, drop_library_css, semantics)
        VALUES ('%s', '%s', %d, %d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s')",
        $libraryData['machineName'], $libraryData['title'], $libraryData['majorVersion'], $libraryData['minorVersion'],
        $libraryData['patchVersion'], $libraryData['runnable'], $libraryData['fullscreen'], $embedTypes, $preloadedJs,
        $preloadedCss, $dropLibraryCss, $libraryData['semantics']
      );
      $libraryId = db_last_insert_id('h5p_libraries', 'library_id');
      $libraryData['libraryId'] = $libraryId;
    }
    else {
      db_query("UPDATE {h5p_libraries}
        SET title = '%s', patch_version = %d, runnable = %d, fullscreen = %d,
        embed_types = '%s', preloaded_js = '%s', preloaded_css = '%s', drop_library_css = '%s', semantics = '%s'
        WHERE library_id = %d",
        $libraryData['title'], $libraryData['patchVersion'], $libraryData['runnable'], $libraryData['fullscreen'], $embedTypes,
        $preloadedJs, $preloadedCss, $dropLibraryCss, $libraryData['semantics'], $libraryData['libraryId']
      );
      $this->deleteLibraryDependencies($libraryData['libraryId']);
    }
    // Update languages
    db_query("DELETE FROM {h5p_libraries_languages}
      WHERE library_id = %d", $libraryData['libraryId']);
    if (isset($libraryData['language'])) {
      foreach ($libraryData['language'] as $languageCode => $languageJson) {
        db_query("INSERT INTO {h5p_libraries_languages}
          (library_id, language_code, language_json)
          VALUES (%d, '%s', '%s')", $libraryData['libraryId'], $languageCode, $languageJson);
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

  /**
   * Implements deleteLibraryDependencies
   */
  public function deleteLibraryDependencies($libraryId) {
    db_query("DELETE FROM {h5p_libraries_libraries} WHERE library_id = %d", $libraryId);
  }
  
  /**
   * Implements deleteLibrary. Will delete a library's data both in the database and file system
   */
  public function deleteLibrary($libraryId) {
    
    $library = db_fetch_object(db_query("select * from {h5p_libraries} where library_id = %d", $libraryId));
    
    // Delete files:
    H5PCore::recursiveUnlink(file_directory_path() . '/' . variable_get('h5p_default_path', 'h5p') . '/libraries/' . $library->machine_name . '-' . $library->major_version . '.' . $library->minor_version);
    
    // Delete data in database (won't delete content):
    db_query("DELETE FROM {h5p_libraries_libraries} WHERE library_id = %d", $libraryId);
    db_query("DELETE FROM {h5p_libraries_languages} WHERE library_id = %d", $libraryId);
    db_query("DELETE FROM {h5p_libraries} WHERE library_id = %d", $libraryId);
  }
  
  /**
   * Implements saveLibraryDependencies
   */
  public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type) {
//    drupal_set_message('Adding deps for ' . $libraryId);
    foreach ($dependencies as $dependency) {
      db_query(
        "INSERT INTO {h5p_libraries_libraries} (library_id, required_library_id, dependency_type)
        SELECT %d, hl.library_id, '%s'
        FROM {h5p_libraries} hl
        WHERE machine_name = '%s'
        AND major_version = %d
        AND minor_version = %d",
        $libraryId, $dependency_type, $dependency['machineName'], $dependency['majorVersion'], $dependency['minorVersion']
      );
    }
  }

  /**
   * Implements saveContentData
   */
  public function saveContentData($contentId, $contentJson, $mainJsonData, $mainLibraryId, $contentMainId = NULL) {
    $embedTypes = '';
    if (isset($mainJsonData['embedTypes'])) {
      $embedTypes = implode(', ', $mainJsonData['embedTypes']);
    }
    db_query(
      "INSERT INTO {h5p_nodes}
      (content_id, nid, json_content, embed_type, main_library_id)
      VALUES (%d, %d, '%s', '%s', %d)",
      $contentId, $contentMainId, $contentJson, $embedTypes, $mainLibraryId
    );
    // TODO: Add support for allowing the user to select embed type
  }

  /**
   * Implement getWhitelist
   */
  public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist) {
    $whitelist = variable_get('h5p_whitelist', $defaultContentWhitelist);
    if ($isLibrary) {
      $whitelist .= ' ' . variable_get('h5p_library_whitelist_extras', $defaultLibraryWhitelist);;
    }
    return $whitelist;
  }

  /**
   * Implements copyLibraryUsage
   */
  public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL) {
    db_query(    
      "INSERT INTO {h5p_nodes_libraries} (content_id, library_id, dependency_type, drop_css)
      SELECT %d, hnl.library_id, hnl.dependency_type, hnl.drop_css
      FROM {h5p_nodes_libraries} hnl
      WHERE hnl.content_id = %d", $contentId, $copyFromId
    );
  }

  /**
   * Implements deleteContentData
   */
  public function deleteContentData($contentId) {
    db_query("DELETE FROM {h5p_nodes} WHERE content_id = %d", $contentId);
    $this->deleteLibraryUsage($contentId);
  }

  /**
   * Implements deleteLibraryUsage
   */
  public function deleteLibraryUsage($contentId) {
    db_query("DELETE FROM {h5p_nodes_libraries} WHERE content_id = %d", $contentId);
  }

  /**
   * Implements saveLibraryUsage
   *
   * TODO: Does the name saveContentDependencies() better describe what this function does?
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
      db_query(
        "INSERT INTO {h5p_nodes_libraries} (content_id, library_id, dependency_type, drop_css)
        VALUES (%d, %d, %d, %d)
        ON DUPLICATE KEY UPDATE dependency_type = %d, drop_css = %d",
        $contentId, $dependency['library']['libraryId'], $dependency['type'], $dropCss, $dependency['type'], $dropCss
      );
    }
  }

  /**
   * Implements loadLibrary
   */
  public function loadLibrary($machineName, $majorVersion, $minorVersion) {
    $library = db_fetch_array(db_query(
      "SELECT library_id as libraryId, machine_name as machineName, title, major_version as majorVersion, minor_version as minorVersion,
        patch_version as patchVersion, embed_types as embedTypes, preloaded_js as preloadedJs, preloaded_css as preloadedCss, drop_library_css as dropLibraryCss,
        fullscreen, runnable, semantics
      FROM {h5p_libraries}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d",
      $machineName, $majorVersion, $minorVersion));
    $result = db_query(
      "SELECT hl.machine_name as machineName, hl.major_version as majorVersion, hl.minor_version as minorVersion, hll.dependency_type as dependencyType
      FROM {h5p_libraries_libraries} hll
      JOIN {h5p_libraries} hl ON hll.required_library_id = hl.library_id
      WHERE hll.library_id = %d", $library['libraryId']
    );
    while ($dependency = db_fetch_array($result)) {
      $library[$dependency['dependencyType'] . 'Dependencies'][] = array(
        'machineName' => $dependency['machineName'],
        'majorVersion' => $dependency['majorVersion'],
        'minorVersion' => $dependency['minorVersion'],
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

  /**
   * Implements loadLibrarySemantics().
   */
  public function loadLibrarySemantics($name, $majorVersion, $minorVersion) {
    $semantics = db_result(db_query(
      "SELECT semantics
       FROM {h5p_libraries}
       WHERE machine_name = '%s'
       AND major_version = %d
       AND minor_version = %d",
      $name, $majorVersion, $minorVersion));

    return ($semantics === FALSE ? NULL : $semantics);
  }
  
  /**
   * Implements alterLibrarySemantics().
   */
  public function alterLibrarySemantics(&$semantics, $name, $majorVersion, $minorVersion) {
    drupal_alter('h5p_semantics', $semantics);
  }
  

  /**
   * Implements getExportData
   *
   * @param int $contentId
   * ContentID of the node we are going to export
   **/
  public function getExportData($contentId) {
    $content = db_fetch_array(db_query(
      "SELECT main_library_id, embed_type, json_content
      FROM {h5p_nodes}
      WHERE content_id = %d",
      $contentId));

    $mainLibraryId = $content['main_library_id'];

    // Use library ids to get library info.
    $res = db_query(
      "SELECT hl.machine_name as machine_name, hl.major_version as major_version, hl.minor_version as minor_version, 
      hnl.dependency_type as preloaded, hl.library_id as library_id
      FROM {h5p_libraries} hl
      JOIN {h5p_nodes_libraries} hnl ON hl.library_id =  hnl.library_id
      WHERE hnl.content_id = %d", $contentId);

    while($libs = db_fetch_array($res)) {
      $librariesInfo[$libs['machine_name']] = array(
        'machineName' => $libs['machine_name'],
        'majorVersion' => $libs['major_version'],
        'minorVersion' => $libs['minor_version'],
        'preloaded' => H5PCore::dependencyConstantToString($libs['preloaded']),
      );

      // Save the main libary machine name.
      if ($libs['library_id'] == $mainLibraryId) {
        $mainLibraryMachineName = $libs['machine_name'];
        $mainLibrary = $librariesInfo[$mainLibraryMachineName];
      }
    }

    if (module_exists('h5peditor')) {
      $editor = h5peditor_get_instance();
      // TODO: Move this function to core?
      $editorLibraries = $editor->findEditorLibraries($mainLibraryMachineName, $mainLibrary['majorVersion'], $mainLibrary['minorVersion']);
    }
    else {
      $editorLibraries = array();
    }

    // Set language to drupals node language, or 'und' if not set.
    $exportLanguage = $language == TRUE ? $language : 'und';

    $export = array(
      'contentId' => $contentId,
      'mainLibrary' => $mainLibraryMachineName,
      'embedType' => $content['embed_type'],
      'libraries' => $librariesInfo,
      'editorLibraries' => $editorLibraries,
      'language' => $exportLanguage,
      'jsonContent' => $content['json_content'],
    );

    return $export;
  }
  /**
   * Check if h5p export is enabled.
   *
   * @return bool
   */
  public function isExportEnabled() {
    return (bool) variable_get('h5p_export', 1);
  }
  
  /**
   * Implements loadContent().
   */
  public function loadContent($id) {
    $content = db_fetch_array(db_query(
      "SELECT hn.json_content AS params
            , hn.embed_type AS embedType 
            , hl.library_id AS libraryId 
            , hl.machine_name AS libraryName
            , hl.major_version AS libraryMajorVersion
            , hl.minor_version AS libraryMinorVersion
            , hl.embed_types AS libraryEmbedTypes
            , hl.fullscreen AS libraryFullscreen
       FROM {h5p_nodes} hn
       JOIN {h5p_libraries} hl ON hl.library_id = hn.main_library_id
       WHERE content_id = %d", $id));
       
    return ($content === FALSE ? NULL : $content);
  }

  /**
   * Implements loadContentDependencies().
   */
  public function loadContentDependencies($id, $type = NULL) {
    $query = 
      "SELECT hl.library_id, 
              hl.machine_name AS machineName, 
              hl.major_version AS majorVersion, 
              hl.minor_version AS minorVersion, 
              hl.preloaded_css AS preloadedCss, 
              hl.preloaded_js AS preloadedJs, 
              hnl.drop_css AS dropCss,
              hnl.dependency_type AS dependencyType
        FROM {h5p_nodes_libraries} hnl
        JOIN {h5p_libraries} hl ON hnl.library_id = hl.library_id
        WHERE hnl.content_id = %d";
    $queryArgs = array($id);
    
    if ($type !== NULL) {
      $query .= " AND hnl.dependency_type = %d";
      $queryArgs[] = $type;
    }
    
    $result = db_query($query, $queryArgs);
    
    $dependencies = array();
    while ($dependency = db_fetch_array($result)) {
      $dependencies[] = $dependency;
    }
    
    return $dependencies;
  }  
  
}

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
   * Implements saveLibraryDependencies
   */
  public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type) {
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
    // @todo: Add support for allowing the user to select embed type
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
      "INSERT INTO {h5p_nodes_libraries} (content_id, library_id, preloaded, drop_css)
      SELECT %d, hnl.library_id, hnl.preloaded, hnl.drop_css
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
    // @todo: Add page that lists unused libraries, allowing us to delete them
  }

  /**
   * Implements deleteLibraryUsage
   */
  public function deleteLibraryUsage($contentId) {
    db_query("DELETE FROM {h5p_nodes_libraries} WHERE content_id = %d", $contentId);
    // @todo: Add page that lists unused libraries, allowing us to delete them
  }

  /**
   * Implements saveLibraryUsage
   */
  public function saveLibraryUsage($contentId, $librariesInUse) {
    $dropLibraryCssList = array();
    foreach ($librariesInUse as $machineName => $library) {
      if (!empty($library['library']['dropLibraryCss'])) {
        $dropLibraryCssList = array_merge($dropLibraryCssList, explode(', ', $library['library']['dropLibraryCss']));
      }
    }
    foreach ($librariesInUse as $machineName => $library) {
      $dropCss = in_array($machineName, $dropLibraryCssList) ? 1 : 0;
      db_query(
        "INSERT INTO {h5p_nodes_libraries} (content_id, library_id, preloaded, drop_css)
        VALUES (%d, %d, %d, %d)
        ON DUPLICATE KEY UPDATE preloaded = %d, drop_css = %d",
        $contentId, $library['library']['libraryId'], $library['preloaded'], $dropCss, $library['preloaded'], $dropCss
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
   * Implements getLibrarySemantics
   *
   * Calls modules implementing hook_alter_h5p_semantics().
   */
  public function getLibrarySemantics($machineName, $majorVersion, $minorVersion) {
    if ($this->isInDevMode()) {
      $semantics = $this->getSemanticsFromFile($machineName, $majorVersion, $minorVersion);
    }
    else {
      $semantics = db_result(db_query(
        "SELECT semantics
        FROM {h5p_libraries}
        WHERE machine_name = '%s'
        AND major_version = %d
        AND minor_version = %d",
        $machineName, $majorVersion, $minorVersion));
    }
    $semantics = json_decode($semantics);
    drupal_alter('h5p_semantics', $semantics);

    return $semantics;
  }

  private function getSemanticsFromFile($machineName, $majorVersion, $minorVersion) {
    $semanticsPath = file_create_path(file_directory_path() . '/' . variable_get('h5p_default_path', 'h5p') . '/libraries/' . $machineName . '-' . $majorVersion . '.' . $minorVersion . '/semantics.json');
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
   * Implements getExportData
   *
   * @param int $contentId
   * ContentID of the node we are going to export
   * @param string $title
   * Title of the node to export
   * @param string $language
   * Language of the node to export
   * @return array
   * An array with all the data needed to export the h5p.
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
      hnl.preloaded as preloaded, hl.library_id as library_id
      FROM {h5p_libraries} hl
      JOIN {h5p_nodes_libraries} hnl ON hl.library_id =  hnl.library_id
      WHERE hnl.content_id = %d", $contentId);

    while($libs = db_fetch_array($res)) {
      $librariesInfo[$libs['machine_name']] = array(
        'machineName' => $libs['machine_name'],
        'majorVersion' => $libs['major_version'],
        'minorVersion' => $libs['minor_version'],
        'preloaded' => $libs['preloaded'],
      );

      // Save the main libary machine name.
      if ($libs['library_id'] == $mainLibraryId) {
        $mainLibraryMachineName = $libs['machine_name'];
        $mainLibrary = $librariesInfo[$mainLibraryMachineName];
      }
    }

    $editorStorage = new H5peditorDrupalStorage();
    $editorLibraries = $editorStorage->getEditorLibraries($mainLibraryMachineName, $mainLibrary['majorVersion'], $mainLibrary['minorVersion'], TRUE);

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
}

?>

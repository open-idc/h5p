<?php

// TODO: Validate file endings against a setting for security
class h5pDrupal implements h5pFramework {
  public function setErrorMessage($message){
    drupal_set_message($message, 'error');
  }
  public function setInfoMessage($message){
    drupal_set_message($message);
  }
  public function t($message, $replacements = array()) {
    return t($message, $replacements);
  }
  public function getH5pPath() {
    return _h5p_get_h5p_path();
  }
  public function getUploadedH5pFolderPath() {
    return $_SESSION['h5p_upload_folder'];
  }
  public function getUploadedH5pPath() {
    return $_SESSION['h5p_upload'];
  }
  public function getLibraryId($machineName, $majorVersion, $minorVersion) {
    $library_id = db_result(db_query(
      "SELECT library_id
      FROM {h5p_library}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d",
      $machineName, $majorVersion, $minorVersion));
    return $library_id;
  }
  public function isPatchedLibrary($library) {
    $result = db_result(db_query(
      "SELECT 1
      FROM {h5p_library}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d
      AND patch_version < %d",
      $library['machineName'], $library['majorVersion'], $library['minorVersion'], $library['patchVersion']));
    return $result === '1';
  }

  public function storeLibraryData(&$libraryData, $new = TRUE) {
    $preloadedJs = $this->pathsToCsv($libraryData, 'preloadedJs');
    $preloadedCss =  $this->pathsToCsv($libraryData, 'preloadedCss');
    $embedTypes = '';
    if (isset($libraryData['embedTypes'])) {
      $embedTypes = implode(', ', $libraryData['embedTypes']);
    }
    if ($new) {
      db_query("INSERT INTO {h5p_library}
        (machine_name, title, major_version, minor_version, patch_version, runnable, init, embed_types, preloaded_js, preloaded_css)
        VALUES ('%s', '%s', %d, %d, %d, %d, '%s', '%s', '%s', '%s')",
        $libraryData['machineName'], $libraryData['title'], $libraryData['majorVersion'], $libraryData['minorVersion'],
        $libraryData['patchVersion'], $libraryData['runnable'], $libraryData['init'], $embedTypes, $preloadedJs, $preloadedCss
      );
      $libraryId = db_last_insert_id('h5p_library', 'library_id');
      $libraryData['libraryId'] = $libraryId;
    }
    else {
      db_query("UPDATE {h5p_library}
        SET title = '%s', patch_version = %d, runnable = %d, init = '%s',
        embed_types = '%s', preloaded_js = '%s', preloaded_css = '%s'
        WHERE library_id = %d",
        $libraryData['title'], $libraryData['patchVersion'], $libraryData['runnable'], $libraryData['init'], $embedTypes,
        $preloadedJs, $preloadedCss, $libraryData['libraryId']
      );
      $this->deleteLibraryDependencies($libraryData['libraryId']);
    }
    if (isset($libraryData['preloadedDependencies'])) {
      $this->storeLibraryDependencies($libraryId, $libraryData['preloadedDependencies'], 1);
    }
    if (isset($libraryData['dynamicDependencies'])) {
      $this->storeLibraryDependencies($libraryId, $libraryData['dynamicDependencies'], 0);
    }
  }

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
  
  private function deleteLibraryDependencies($libraryId) {
    db_query("DELETE FROM {h5p_library_dependency_index} WHERE library_id = %d", $libraryId);
  }

  private function storeLibraryDependencies($libraryId, $dependencies, $preloaded) {
    $query = 
      "INSERT INTO {h5p_library_dependency_index}
      (library_id, machine_name, major_version, minor_version, preloaded) VALUES";
    $sql_args = array();
    $first = TRUE;
    foreach ($dependencies as $dependency) {
      if (!$first) {
        $query .= ",";
      }
      $query .= " (%d, '%s', %d, %d, %d)";
      $sql_args[] = $libraryId;
      $sql_args[] = $dependency['machineName'];
      $sql_args[] = $dependency['majorVersion'];
      $sql_args[] = $dependency['minorVersion'];
      $sql_args[] = $preloaded;
      $first = FALSE;
    }
    db_query($query, $sql_args);
  }

  public function storeContentData($contentId, $contentJson, $mainJsonData, $contentMainId = NULL) {
    $embedTypes = '';
    if (isset($mainJsonData['embedTypes'])) {
      $embedTypes = implode(', ', $mainJsonData['embedTypes']);
    }
    db_query(
      "INSERT INTO {h5p_node}
      (vid, nid, json_content, embed_type, init)
      VALUES (%d, %d, '%s', '%s', '%s')",
      $contentId, $contentMainId, $contentJson, $embedTypes, $mainJsonData['init']
    );
    // @todo: Add support for allowing the user to select embed type
  }

  public function copyContentData($contentId, $copyFromId, $contentMainId = NULL) {
    db_query(
      "INSERT INTO {h5p_node} (vid, nid, json_content, embed_type, init)
      SELECT %d, %d, hns.json_content, hns.embed_type, hns.init
      FROM {h5p_node} hns
      WHERE hns.vid = %d
      ", $contentId, $contentMainId, $copyFromId
    );
    db_query(
      "INSERT INTO {h5p_library_index} (vid, library_id, preloaded)
      SELECT %d, hlis.library_id, hlis.preloaded
      FROM {h5p_library_index} hlis
      WHERE hlis.vid = %d", $contentId, $copyFromId
    );
  }

  public function deleteContentData($contentId) {
    db_query("DELETE FROM {h5p_node} WHERE vid = %d", $contentId);
    db_query("DELETE FROM {h5p_library_index} WHERE vid = %d", $contentId);
    // @todo: Add page that lists unused libraries, allowing us to delete them
  }

  public function saveLibraryUsage($contentId, $librariesInUse) {
    foreach ($librariesInUse as $library) {
      db_query(
        "INSERT INTO {h5p_library_index}
        (vid, library_id, preloaded)
        VALUES (%d, %d, %d)",
        $contentId, $library['library']['libraryId'], $library['preloaded']
      );
    }
  }
  public function loadLibrary($machineName, $majorVersion, $minorVersion) {
    $library = db_fetch_array(db_query(
      "SELECT library_id as libraryId, machine_name as machineName, title, major_version as majorVersion, minor_version as minorVersion,
        patch_version as patchVersion, init, embed_types as embedTypes, preloaded_js as preloadedJs, preloaded_css as preloadedCss
      FROM {h5p_library}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d",
      $machineName, $majorVersion, $minorVersion));
    $result = db_query(
      "SELECT machine_name as machineName, major_version as majorVersion, minor_version as minorVersion, preloaded
      FROM {h5p_library_dependency_index}
      WHERE library_id = %d", $library['libraryId']
    );
    while ($dependency = db_fetch_array($result)) {
      $library[$dependency['preloaded'] ? 'preloadedDependencies' : 'dynamicDependencies'][] = array(
        'machineName' => $dependency['machineName'],
        'majorVersion' => $dependency['majorVersion'],
        'minorVersion' => $dependency['minorVersion'],
      );
    }
    return $library;
  }
}

?>

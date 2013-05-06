<?php

class H5peditorDrupalStorage implements H5peditorStorage {
  
  function __construct() { }
  
  public function getSemantics($machineName, $majorVersion, $minorVersion) {
    return db_result(db_query(
      "SELECT semantics
      FROM {h5p_libraries}
      WHERE machine_name = '%s'
      AND major_version = %d
      AND minor_version = %d",
      $machineName, $majorVersion, $minorVersion));
  }

  public function getFilePaths($libraryId) {
    $result = db_query(
      "SELECT hl.preloaded_css, hl.preloaded_js, hl.library_id, hl.machine_name as machineName, hl.major_version as majorVersion, hl.minor_version as minorVersion
      FROM {h5p_libraries} hl
      WHERE hl.library_id = %d", $libraryId
    );
    $file_paths = array(
      'js' => array(),
      'css' => array(),
    );
    $h5p_path = _h5p_get_h5p_path();
    $h5p_core = _h5p_get_instance('core');
    while ($paths = db_fetch_array($result)) {
      if (!empty($paths['preloaded_js'])) {
        foreach (explode(',', $paths['preloaded_js']) as $js_path) {
          $file_paths['js'][] = $h5p_path . '/libraries/' . $h5p_core->libraryToString($paths, TRUE) . '/' . trim($js_path);
        }
      }
      if (!empty($paths['preloaded_css'])) {
        foreach (explode(',', $paths['preloaded_css']) as $css_path) {
          $file_paths['css'][] = $h5p_path . '/libraries/' . $h5p_core->libraryToString($paths, TRUE) . '/' . trim($css_path);
        }
      }
    }
    return $file_paths;
  }
  
  public function addFile($file) {
    global $user;
    
    db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $user->uid, $file->name, $file->path, $file->mime, $file->size, FILE_STATUS_TEMPORARY, time());
  }
  
  public function removeFile($path) {
    db_query("DELETE FROM {files} WHERE filepath = '%s'", $path);
  }
  
  public function getLibraries() {
    $libraries = array();

    if (isset($_POST['libraries'])) {
      // Get details for the specified libraries.
      foreach ($_POST['libraries'] as $libraryName) {
        $matches = array();
        preg_match_all('/(.+)\s(\d)+\.(\d)$/', $libraryName, $matches);
        if ($matches) {
          $library = db_fetch_object(db_query("SELECT machine_name AS name, title, major_version as majorVersion, minor_version as minorVersion FROM {h5p_libraries} WHERE machine_name = '%s' AND major_version = %d AND minor_version = %d AND semantics IS NOT NULL", $matches[1][0], $matches[2][0], $matches[3][0]));
          if ($library) {
            $library->uberName = $libraryName;
            $libraries[] = $library;
          }
        }
      }
    }
    else {
      // Get some books from the library.
      $libraries_result = db_query("SELECT machine_name AS machineName, title, major_version as majorVersion, minor_version as minorVersion FROM {h5p_libraries} WHERE runnable = 1 AND semantics IS NOT NULL");
      while ($library = db_fetch_object($libraries_result)) {
        $libraries[] = $library;
      }
    }

    return json_encode($libraries);
  }
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion) {
    $editorLibraries = array();

    // TODO: Add support for fetching additional libraries this library depends on
    $result = db_query(
      "SELECT hll.required_library_id AS id, hl2.machine_name AS machineName, hl2.major_version AS majorVersion, hl2.minor_version AS minorVersion
      FROM {h5p_libraries} hl
      JOIN {h5p_libraries_libraries} hll ON hll.library_id = hl.library_id
      JOIN {h5p_libraries} hl2 ON hl2.library_id = hll.required_library_id
      WHERE hl.machine_name = '%s'
      AND hl.major_version = %d
      AND hl.minor_version = %d
      AND hll.dependency_type = 'editor'
    ", $machineName, $majorVersion, $minorVersion);
    
    $jsonData = array('preloadedDependencies' => array());
    while ($editorLibrary = db_fetch_object($result)) {
      $editorLibraries[] = $editorLibrary->id;
      
      $jsonData['preloadedDependencies'][$editorLibrary->id] = array(
        'machineName' => $editorLibrary->machineName,
        'majorVersion' => $editorLibrary->majorVersion,
        'minorVersion' => $editorLibrary->minorVersion
      );
    }
    
    $libraries = array();
    // TODO: Fix, using private function since no API is available!
    $storage = _h5p_get_instance('storage');
    $storage->getLibraryUsage($libraries, $jsonData);
    
    foreach ($libraries as $library) {
      if ($library['preloaded'] && !in_array($library['library']['libraryId'], $editorLibraries)) {
        $editorLibraries[] = $library['library']['libraryId'];
      }
    }
    
    return $editorLibraries;
  }
}
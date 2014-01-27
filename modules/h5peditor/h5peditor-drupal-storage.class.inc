<?php

/**
 * Handles all communication with the database.
 */
class H5peditorDrupalStorage implements H5peditorStorage {

  /**
   * Empty contructor.
   */
  function __construct() { }

  /**
   * TODO
   */
  public function getSemantics($machineName, $majorVersion, $minorVersion) {
    // TODO: Stop using private functions.
    $coreInterface = _h5p_get_instance('interface');
    return $coreInterface->getLibrarySemantics($machineName, $majorVersion, $minorVersion);
  }

  /**
   * TODO
   */
  public function getLanguage($machineName, $majorVersion, $minorVersion) {
    global $language;
    $lang = db_result(db_query(
      "SELECT language_json
      FROM {h5p_libraries_languages} hlt
      JOIN {h5p_libraries} hl ON hl.library_id = hlt.library_id
      WHERE hl.machine_name = '%s'
      AND hl.major_version = %d
      AND hl.minor_version = %d
      AND hlt.language_code = '%s'",
      $machineName, $majorVersion, $minorVersion, $language->language));
    return $lang;
  }

  /**
   * TODO
   */
  public function getLibraryFiles($machineName, $majorVersion, $minorVersion) {
    $file_paths = array(
      'scripts' => array(),
      'styles' => array()
    );
  
    $result = db_query(
      "SELECT hl.preloaded_css, hl.preloaded_js, hl.library_id, hl.machine_name as machineName, hl.major_version as majorVersion, hl.minor_version as minorVersion
      FROM {h5p_libraries} hl WHERE hl.machine_name = '%s' AND hl.major_version = %d AND hl.minor_version = %d", 
      $machineName, $majorVersion, $minorVersion
    );

    // TODO: Stop using private functions.
    $h5p_path = _h5p_get_h5p_path();
    $h5p_core = _h5p_get_instance('core');
    
    while ($paths = db_fetch_array($result)) {
      if (!empty($paths['preloaded_js'])) {
        foreach (explode(',', $paths['preloaded_js']) as $js_path) {
          $file_paths['scripts'][] = $h5p_path . '/libraries/' . $h5p_core->libraryToString($paths, TRUE) . '/' . trim($js_path);
        }
      }
      if (!empty($paths['preloaded_css'])) {
        foreach (explode(',', $paths['preloaded_css']) as $css_path) {
          $file_paths['styles'][] = $h5p_path . '/libraries/' . $h5p_core->libraryToString($paths, TRUE) . '/' . trim($css_path);
        }
      }
    }
    
    return $file_paths;
  }

  /**
   * TODO
   */
  public function addTmpFile($file) {
    global $user;

    db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $user->uid, $file->name, $file->path, $file->mime, $file->size, FILE_STATUS_TEMPORARY, time());
  }

  /**
   * TODO
   */
  public function keepFile($oldPath, $newPath) {
    db_query("UPDATE {files} SET filepath = '%s', status = %d WHERE filepath = '%s'", $newPath, FILE_STATUS_PERMANENT, $oldPath);
  }

  /**
   * TODO
   */
  public function removeFile($path) {
    db_query("DELETE FROM {files} WHERE filepath = '%s'", $path);
  }

  /**
   * TODO
   */
  public function getLibraries($libraries = NULL) {
    if ($libraries !== NULL) {
      // Get details for the specified libraries only.
      foreach ($libraries as $library) {
        $details = db_fetch_object(db_query("SELECT title, runnable FROM {h5p_libraries} WHERE machine_name = '%s' AND major_version = %d AND minor_version = %d AND semantics IS NOT NULL", $library->name, $library->majorVersion, $library->minorVersion));
        if ($details !== FALSE) {
          $library->title = $details->title;
          $library->runnable = $details->runnable;
        }
      }
      
      return $libraries;
    }
    
    $libraries = array();

    // Get some books from the library.
    $libraries_result = db_query("SELECT machine_name AS machineName, title, major_version as majorVersion, minor_version as minorVersion FROM {h5p_libraries} WHERE runnable = 1 AND semantics IS NOT NULL");
    while ($library = db_fetch_object($libraries_result)) {
      $libraries[] = $library;
    }
    
    return $libraries;
  }
  
  /**
   * TODO
   */
  public function getLibraryEditors($machineName, $majorVersion, $minorVersion) {
    $editorLibraries = array();

    // TODO: Add support for fetching additional libraries this library depends on
    $result = db_query(
      "SELECT hll.required_library_id AS libraryId, hl2.machine_name AS machineName, hl2.major_version AS majorVersion, hl2.minor_version AS minorVersion
      FROM {h5p_libraries} hl
      JOIN {h5p_libraries_libraries} hll ON hll.library_id = hl.library_id
      JOIN {h5p_libraries} hl2 ON hl2.library_id = hll.required_library_id
      WHERE hl.machine_name = '%s'
      AND hl.major_version = %d
      AND hl.minor_version = %d
      AND hll.dependency_type = 'editor'
    ", $machineName, $majorVersion, $minorVersion);

    while ($editorLibrary = db_fetch_object($result)) {
      $editorLibraries[$editorLibrary->libraryId] = $editorLibrary;
    }
    
    return $editorLibraries;
  }
  
  /**
   * TODO
   */
  public function findLibraryDependencies(&$libraries) {    
    $preloadedDependencies = array();
    foreach ($libraries as $library) {
      $preloadedDependencies[] = array(
        'machineName' => $library->machineName,
        'majorVersion' => $library->majorVersion,
        'minorVersion' => $library->minorVersion
      );
    }

    // TODO: Fix, using private function since no API is available!
    $storage = _h5p_get_instance('storage');
    $deps = array();
    $storage->getLibraryUsage($deps, array('preloadedDependencies' => $preloadedDependencies));
    foreach ($deps as $dep) {
      if ($dep['preloaded']) {
        $libraries[$dep['library']['libraryId']] = $dep['library'];
      }
    }
  }
}

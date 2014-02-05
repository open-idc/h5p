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
   * TODO: Use core in h5peditor, remove this.
   */
  public function getSemantics($name, $majorVersion = NULL, $minorVersion = NULL) {
    if (is_array($name) === TRUE) {
      $majorVersion = $name['majorVersion'];
      $minorVersion = $name['minorVersion'];
      $name = $name['machineName'];
    }
    
    $h5p = h5p_get_instance();
    return $h5p->loadLibrarySemantics($name, $majorVersion, $minorVersion);
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
      
    return ($lang === FALSE ? NULL : $lang);
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

    // TODO: Prevent usage of private functions.
    $h5p_path = _h5p_get_h5p_path();
    $h5p_core = _h5p_get_instance('core');
    
    while ($paths = db_fetch_array($result)) {
      if (!empty($paths['preloaded_js'])) {
        foreach (explode(',', $paths['preloaded_js']) as $js_path) {
          $file_paths['scripts'][] = $h5p_path . '/libraries/' . H5PCore::libraryToString($paths, TRUE) . '/' . trim($js_path);
        }
      }
      if (!empty($paths['preloaded_css'])) {
        foreach (explode(',', $paths['preloaded_css']) as $css_path) {
          $file_paths['styles'][] = $h5p_path . '/libraries/' . H5PCore::libraryToString($paths, TRUE) . '/' . trim($css_path);
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
   * Return all libraries used by the given editor library.
   *
   * @param string $machineName Library identfier part 1
   * @param int $majorVersion Library identfier part 2
   * @param int $minorVersion Library identfier part 3
   */
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion) {
    // TODO: Prevent usage of private functions.
    $i = _h5p_get_instance('interface');
    $s = _h5p_get_instance('storage');

    $library = $i->loadLibrary($machineName, $majorVersion, $minorVersion);
    $dependencies = array();
    $s->findLibraryDependencies($dependencies, $library);
    
    $editorLibraries = array($library['libraryId'] => $library);
    foreach ($dependencies as $dependency) {
      if ($dependency['type'] === H5PCore::DEPENDENCY_TYPE_DYNAMIC) {
        continue; // Skip dynamic dependencies.
      }
      $editorLibraries[$dependency['library']['libraryId']] = $dependency['library'];
    }
    
    return $editorLibraries;
  }
}

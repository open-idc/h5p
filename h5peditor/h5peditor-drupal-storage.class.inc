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
      "SELECT preloaded_css, preloaded_js, library_id
      FROM {h5p_libraries} hl
      WHERE hl.library_id = %d", $libraryId
    );
    $file_paths = array(
      'js' => array(),
      'css' => array(),
    );
    $h5p_path = _h5p_get_h5p_path();
    while ($paths = db_fetch_array($result)) {
      if (!empty($paths['preloaded_js'])) {
        foreach (explode(',', $paths['preloaded_js']) as $js_path) {
          $file_paths['js'][] = $h5p_path . '/libraries/' . $paths['library_id'] . '/' . trim($js_path);
        }
      }
      if (!empty($paths['preloaded_css'])) {
        foreach (explode(',', $paths['preloaded_css']) as $css_path) {
          $file_paths['css'][] = $h5p_path . '/libraries/' . $paths['library_id'] . '/' . trim($css_path);
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
    
    // Get some books from the library.
    $libraries_result = db_query("SELECT machine_name AS machineName, title, major_version as majorVersion, minor_version as minorVersion FROM {h5p_libraries} WHERE runnable = 1 AND semantics IS NOT NULL");
    while ($library = db_fetch_object($libraries_result)) {
      $libraries[] = $library;
    }

    return json_encode($libraries);
  }
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion) {
    $editorLibraries = array();

    // TODO: Add support for fetching additional libraries this library depends on
    $result = db_query(
      "SELECT hll.required_library_id
      FROM {h5p_libraries} hl
      JOIN {h5p_libraries_libraries} hll ON hll.library_id = hl.library_id
      WHERE hl.machine_name = '%s'
      AND hl.major_version = %d
      AND hl.minor_version = %d
      AND hll.dependency_type = 'editor'
    ", $machineName, $majorVersion, $minorVersion);
    while ($editorLibraryId = db_result($result)) {
      $editorLibraries[] = $editorLibraryId;
    }
    return $editorLibraries;
  }
}
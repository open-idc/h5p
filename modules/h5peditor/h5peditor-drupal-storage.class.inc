<?php

class H5peditorDrupalStorage implements H5peditorStorage {

  function __construct() { }

  public function getSemantics($library) {
    $semantics = json_decode($library['semantics']);
    if (empty($semantics) === FALSE) {
      drupal_alter('h5p_semantics', $semantics);
    }
    return $semantics;
  }

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

  public function addTempFile($file) {
    global $user;

    db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $user->uid, $file->name, $file->path, $file->mime, $file->size, FILE_STATUS_TEMPORARY, time());
  }

  public function keepFile($oldPath, $newPath) {
    db_query("UPDATE {files} SET filepath = '%s', status = %d WHERE filepath = '%s'", $newPath, FILE_STATUS_PERMANENT, $oldPath);
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
          $library = db_fetch_object(db_query("SELECT machine_name AS name, title, major_version as majorVersion, minor_version as minorVersion, runnable FROM {h5p_libraries} WHERE machine_name = '%s' AND major_version = %d AND minor_version = %d AND semantics IS NOT NULL", $matches[1][0], $matches[2][0], $matches[3][0]));
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
  
  /**
   * Return all libraries used by the given editor library.
   *
   * @param string $machineName Library identfier part 1
   * @param int $majorVersion Library identfier part 2
   * @param int $minorVersion Library identfier part 3
   */
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion) {
    $i = _h5p_get_instance('interface');
    $s = _h5p_get_instance('storage');
    // TODO: Make this feel :)

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

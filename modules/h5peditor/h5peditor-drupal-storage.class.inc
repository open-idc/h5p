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

    $libraries_result = db_query("SELECT machine_name AS machineName, title, major_version as majorVersion, minor_version as minorVersion FROM {h5p_libraries} WHERE runnable = 1 AND semantics IS NOT NULL ORDER BY title");
    while ($library = db_fetch_object($libraries_result)) {
      $found = FALSE;
      
      // Make sure we only display the newest version of a library.
      foreach ($libraries as $key => $existingLibrary) {
        if ($library->machineName === $existingLibrary->machineName) {
          $found = TRUE;
          
          // Choose newest
          if ($library->majorVersion === $existingLibrary->majorVersion) {
            if ($library->minorVersion > $existingLibrary->minorVersion) {
              // Add
              $libraries[$key] = $library;
            }
          }
          else if ($library->majorVersion > $existingLibrary->majorVersion) {
            // Add
            $libraries[$key] = $library;
          }
          // else: Just skip
        }
      }
      
      if (!$found) {
        $libraries[] = $library;
      }
    }
    
    return $libraries;
  }
  
}

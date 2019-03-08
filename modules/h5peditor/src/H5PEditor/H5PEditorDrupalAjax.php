<?php

namespace Drupal\h5peditor\H5PEditor;

class H5PEditorDrupalAjax implements \H5PEditorAjaxInterface {

  /**
   * Gets latest library versions that exists locally
   *
   * @return array Latest version of all local libraries
   */
  public function getLatestLibraryVersions() {
    $connection = \Drupal::database();

    // Retrieve latest major version
    $max_major_version = $connection->select('h5p_libraries', 'h1');
    $max_major_version->fields('h1', ['machine_name']);
    $max_major_version->addExpression('MAX(h1.major_version)', 'major_version');
    $max_major_version->condition('h1.runnable', 1);
    $max_major_version->groupBy('h1.machine_name');

    // Find latest minor version among the latest major versions
    $max_minor_version = $connection->select('h5p_libraries', 'h2');
    $max_minor_version->fields('h2', [
      'machine_name',
      'major_version',
    ]);
    $max_minor_version->addExpression('MAX(h2.minor_version)', 'minor_version');

    // Join max major version and minor versions
    $max_minor_version->join($max_major_version, 'h1', "
      h1.machine_name = h2.machine_name AND
      h1.major_version = h2.major_version
    ");

    // Group together on major versions to get latest minor version
    $max_minor_version->groupBy('h2.machine_name');
    $max_minor_version->groupBy('h2.major_version');

    // Find latest patch version from latest major and minor version
    $latest = $connection->select('h5p_libraries', 'h3');
    $latest->addField('h3', 'library_id', 'id');
    $latest->fields('h3', [
      'machine_name',
      'title',
      'major_version',
      'minor_version',
      'patch_version',
      'has_icon',
      'restricted',
    ]);

    // Join max minor versions with the latest patch version
    $latest->join($max_minor_version, 'h4', "
      h4.machine_name = h3.machine_name AND
      h4.major_version = h3.major_version AND
      h4.minor_version = h3.minor_version
    ");

    // Grab the results
    $results = $latest->execute()->fetchAll();
    return $results;
  }

  /**
   * Get locally stored Content Type Cache. If machine name is provided
   * it will only get the given content type from the cache
   *
   * @param $machineName
   *
   * @return array|object|null Returns results from querying the database
   */
  public function getContentTypeCache($machineName = NULL) {

    // Get only the specified content type from cache
    if ($machineName !== NULL) {
      return db_query(
        "SELECT id, is_recommended
         FROM {h5p_libraries_hub_cache}
        WHERE machine_name = :name",
        array(':name' => $machineName)
      )->fetchObject();
    }

    // Get all cached content types
    return db_query("SELECT * FROM {h5p_libraries_hub_cache}")->fetchAll();
  }

  /**
   * Create a list of the recently used libraries
   *
   * @return array machine names. The first element in the array is the most
   * recently used.
   */
  public function getAuthorsRecentlyUsedLibraries() {

    $uid = \Drupal::currentUser()->id();

    $recently_used = array();

    // Get recently used:
    $result = db_query("
      SELECT library_name, max(created_at) AS max_created_at
      FROM {h5p_events}
      WHERE type='content' AND sub_type = 'create' AND user_id = :uid
      GROUP BY library_name
      ORDER BY max_created_at DESC
    ", array(':uid' => $uid));

    foreach ($result as $row) {
      $recently_used[] = $row->library_name;
    }

    return $recently_used;
  }

  /**
   * Checks if the provided token is valid for this endpoint
   *
   * @param string $token The token that will be validated for.
   *
   * @return bool True if successful validation
   */
  public function validateEditorToken($token) {
    return \H5PCore::validToken('editorajax', $token);
  }

  /**
   * Get translations for a language for a list of libraries
   *
   * @param array $libraries An array of libraries, in the form "<machineName> <majorVersion>.<minorVersion>
   * @param string $language_code
   * @return array
   */
  public function getTranslations($libraries, $language_code) {
    $translations = array();

    foreach ($libraries as $library) {
      $parsedLib = \H5PCore::libraryFromString($library);

      $translation = db_query("
        SELECT language_json
        FROM {h5p_libraries} lib
        LEFT JOIN {h5p_libraries_languages} lang ON lib.library_id = lang.library_id
        WHERE lib.machine_name = :machine_name AND
              lib.major_version = :major_version AND
              lib.minor_version = :minor_version AND
              lang.language_code = :language_code
      ",
      array(
        ':machine_name' => $parsedLib['machineName'],
        ':major_version' => $parsedLib['majorVersion'],
        ':minor_version' => $parsedLib['minorVersion'],
        ':language_code' => $language_code)
      )->fetchField();

      if ($translation !== FALSE) {
        $translations[$library] = $translation;
      }
    }

    return $translations;
  }
}

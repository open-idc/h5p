<?php

namespace Drupal\h5peditor\H5PEditor;

// TODO: Remove once added to autoload
require_once('vendor/h5p/h5p-editor/h5peditor-ajax.interface.php');

class H5PEditorDrupalAjax implements \H5PEditorAjaxInterface {

  /**
   * Gets latest library versions that exists locally
   *
   * @return array Latest version of all local libraries
   */
  public function getLatestLibraryVersions() {

    $connection = \Drupal::database();

    $query = $connection->select('h5p_libraries', 'hl');
    $query->condition('hl.runnable', 1, '=');
    $query->fields('hl', ['library_id']);
    $query->addExpression('MAX(hl.major_version)');
    $query->groupBy('hl.library_id');
    $result = $query->execute()->fetchAll();
    $major_ids = [];
    foreach ($result as $row) {
      $major_ids[] = $row->library_id;
    }


    $query = $connection->select('h5p_libraries', 'hl');
    $query->condition('hl.library_id', $major_ids, 'IN');
    $query->fields('hl', ['library_id']);
    $query->addExpression('MAX(hl.minor_version)');
    $query->groupBy('hl.library_id');
    $result_max_minor = $query->execute()->fetchAll();
    foreach ($result as $row) {
      $minor_ids[] = $row->library_id;
    }

    $query = $connection->select('h5p_libraries', 'hl');
    $query->condition('hl.library_id', $minor_ids, 'IN');
    $query->fields('hl', ['machine_name','title','major_version','minor_version','patch_version', 'has_icon', 'restricted']);
    $query->addField('hl', 'library_id', 'id');
    $local_libraries = $query->execute()->fetchAll();

    return $local_libraries;
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
}

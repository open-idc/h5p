<?php

namespace Drupal\h5peditor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\H5PDrupal\H5PEvent;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;

class H5PEditorAJAXController extends ControllerBase {

  /**
   * Callback that lists all h5p libraries.
   */
  function librariesCallback() {
    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::LIBRARIES);
    exit();
  }


  /**
   * Callback that returns the content type cache
   */
  function contentTypeCacheCallback() {
    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::CONTENT_TYPE_CACHE);
    exit();
  }

  /**
   * Callback Install library from external file
   *
   * @param string $token Security token
   * @param int $content_id Id of content
   * @param string $machine_name Machine name of library
   */
  function libraryInstallCallback($token, $content_id, $machine_name) {
    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::LIBRARY_INSTALL, $token, $machine_name);
    exit();
  }

  /**
   * Callback for uploading a library
   *
   * @param string $token Editor security token
   * @param int $content_id Id of content that is being edited
   */
  function libraryUploadCallback($token, $content_id) {
    $editor = H5PEditorUtilities::getInstance();
    $filePath = $_FILES['h5p']['tmp_name'];
    $editor->ajax->action(\H5PEditorEndpoints::LIBRARY_UPLOAD, $token, $filePath, $content_id);
    exit();
  }

  /**
   * Callback that returns data for a given library
   *
   * @param string $machine_name Machine name of library
   * @param int $major_version Major version of library
   * @param int $minor_version Minor version of library
   */
  function libraryCallback($machine_name, $major_version, $minor_version) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::SINGLE_LIBRARY, $machine_name,
      $major_version, $minor_version, $language, H5PDrupal::getRelativeH5PPath(), '', filter_input(INPUT_GET, 'default-language')
    );

    // Log library loaded
    new H5PEvent('library', NULL, NULL, NULL,
      $machine_name,
      $major_version . '.' . $minor_version
    );
    exit();
  }

  /**
   * Callback for file uploads.
   *
   * @param string $token Security token
   * @param int $content_id Content id
   */
  function filesCallback($token, $content_id) {
    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::FILES, $token, $content_id);
    exit();
  }

  /**
   * Callback for file uploads.
   *
   * @param string $token Security token
   * @param int $content_id Content id
   */
  function translationsCallback($token, $content_id, $language) {
    $editor = H5PEditorUtilities::getInstance();
    $editor->ajax->action(\H5PEditorEndpoints::TRANSLATIONS, $language);
    exit();
  }

}

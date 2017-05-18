<?php

namespace Drupal\h5peditor\Controller;

use Drupal\h5p\H5PDrupal;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class H5PEditorAJAXController extends ControllerBase {

  /**
   * Callback that lists all h5p libraries.
   */
  function librariesCallback($token, $content_id) {

    $editor = h5peditor_get_instance();
    $editor->ajax->action(\H5PEditorEndpoints::LIBRARIES);

    // ajax response is alread send h5peditor
    exit();
  }


  /**
   * Callback that returns the content type cache
   */
  function contentTypeCacheCallback() {

    $editor = h5peditor_get_instance();
    $editor->ajax->action(\H5PEditorEndpoints::CONTENT_TYPE_CACHE);

    // ajax response is alread send h5peditor
    exit();
  }

  /**
   * Callback Install library from external file
   */
  function libraryInstallCallback($token, $content_id, $machine_name) {

    $editor = h5peditor_get_instance();
    $editor->ajax->action(\H5PEditorEndpoints::LIBRARY_INSTALL, $token, $machine_name);

    // ajax response is alread send h5peditor
    exit();
  }

  /**
   * Callback that returns all library data
   *
   */
  function libraryCallback($machine_name, $major_version, $minor_version) {

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $editor = h5peditor_get_instance();
    $module_path = drupal_get_path('module', 'h5p');
    $editor->ajax->action(\H5PEditorEndpoints::SINGLE_LIBRARY, $machine_name,
      $major_version, $minor_version, $language, $module_path
    );

    // Log library loaded
    new H5PDrupal\H5PEvent('library', NULL, NULL, NULL,
      $machine_name,
      $major_version . '.' . $minor_version
    );

    // ajax response is alread send h5peditor
    exit();
  }

  /**
   * Callback for file uploads.
   */
  function filesCallback($token, $content_id) {

    $editor = h5peditor_get_instance();
    $editor->ajax->action(\H5PEditorEndpoints::FILES, $token, $content_id);

    // ajax response is alread send h5peditor
    exit();
  }

}

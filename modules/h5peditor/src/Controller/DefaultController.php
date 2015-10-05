<?php /**
 * @file
 * Contains \Drupal\h5peditor\Controller\DefaultController.
 */

namespace Drupal\h5peditor\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the h5peditor module.
 */
class DefaultController extends ControllerBase {

  public function h5peditor_access($node_id, Drupal\Core\Session\AccountInterface $account) {
    if ($node_id === '0') {
      return node_access('create', 'h5p_content');
    }

    $node = \Drupal::entityManager()->getStorage('node')->load($node_id);
    return node_access('update', $node);
  }

  public function h5peditor_libraries_callback() {
    $editor = h5peditor_get_instance();

    drupal_add_http_header('Cache-Control', 'no-cache');
    drupal_add_http_header('Content-type', 'application/json');
    print $editor->getLibraries();
  }

  public function h5peditor_library_callback($machine_name, $major_version, $minor_version) {
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $editor = h5peditor_get_instance();

    // TODO: Make Drupal cache result?
    // TODO: Consider if we should leverage browser caching by just using .js files instead of eval, or a combination.

    drupal_add_http_header('Cache-Control', 'no-cache');
    drupal_add_http_header('Content-type', 'application/json');
    print $editor->getLibraryData($machine_name, $major_version, $minor_version, $language->language, '', _h5p_get_h5p_path());
  }

  public function h5peditor_files_callback() {
    $files_directory = \Drupal::service("stream_wrapper_manager")->getViaUri('public://')->getDirectoryPath();
    if (isset($_POST['contentId']) && $_POST['contentId']) {
      // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// $files_directory .= '/' . variable_get('h5p_default_path', 'h5p') . '/content/' . $_POST['contentId'];

    }
    else {
      $files_directory .= '/h5peditor';
    }

    $file = new H5peditorFile(_h5p_get_instance('interface'), $files_directory);

    if (!$file->isLoaded()) {
      drupal_not_found();
      return;
    }

    if ($file->validate() && $file->copy()) {
      $editor = h5peditor_get_instance();

      // Keep track of temporary files so they can be cleaned up later.
      $editor->addTmpFile($file);
    }

    header('Content-type: text/html; charset=utf-8');
    print $file->getResult();
  }

}

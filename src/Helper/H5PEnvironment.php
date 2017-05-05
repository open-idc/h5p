<?php
/**
 * @file
 * H5PEnvironment
 *
 * @author
 * Jörg Matheisen, www.drupalme.de
 */
namespace Drupal\h5p\Helper;

use \Drupal\h5p\H5PDrupal;
use \Drupal\Core\Url;


class H5PEnvironment {

  /**
   * Verify that the libraries H5P needs exists
   *
   * @return boolean
   *  TRUE if the settings validate, FALSE otherwise
   */
  function checkSettings() {

    $path = $this->getH5PPath();
    // Creating directories - the first empty string is for creating the parent H5P directory
    foreach (array('', 'temp', 'libraries', 'content', 'exports', 'development') as $directory) {
      $directory = $path . '/' . $directory;
      if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get the path to the h5p files folder.
   *
   * @return string
   *  Path to the h5p files folder
   */
  function getH5PPath() {
    $uri = 'public://';
    $file_path = file_create_url($uri);
    $h5p_default_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p';
    $path = $file_path . $h5p_default_path;
    return $path;
  }

  /**
   * Get the path to the h5p files folder.
   *
   * @return string
   *  Path to the h5p files folder
   */
  function getH5PRealPath() {
    $uri = 'public://h5p';
    $real_path = \Drupal::service('file_system')->realpath($uri);
    return $real_path;
  }

  /**
   * Get an instance of one of the h5p library classes
   *
   * This function stores the h5p core in a static variable so that the variables there will
   * be kept between validating and saving the node for instance
   *
   * @staticvar H5PDrupal $interface
   *  The interface between the H5P library and drupal
   * @staticvar H5PCore $core
   *  Core functions and storage in the h5p library
   * @param string $type
   *  Specifies the instance to be returned; validator, storage, interface or core
   * @return object
   *  The instance og h5p specified by type
   */
  function getInstance($type) {

    static $interface, $core;

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    if (!isset($interface)) {

      $interface = new H5PDrupal\H5PDrupal();
      $real_path = \Drupal::service('file_system')->realpath($this->getH5PRealPath());

      $fs = new \H5PDefaultStorage($real_path, file_create_url('public://') . DIRECTORY_SEPARATOR . 'h5peditor');
      $h5p_export = \Drupal::state()->get('h5p_export') ?: 1;
      $core = new \H5PCore($interface, $fs, base_path(), $language, $h5p_export);

      // Override regex for converting copied files paths
      $core->relativePathRegExp = '/^((\.\.\/){1,3})(.*content\/)?(\d+|h5peditor)\/(.+)$/';

      $h5p_library_development = \Drupal::state()->get('h5p_library_development') ?: 0;
      if ($h5p_library_development === 1) {
        $core->development_mode |= \H5PDevelopment::MODE_LIBRARY;
        $core->h5pD = new \H5PDevelopment($interface, _h5p_get_h5p_path() . '/', $language->language);

        $message = t('H5P library development directory is enabled. Change <a href="@settings-page">settings</a>.', array('@settings-page' => url('admin/config/system/h5p')));

        $preprocess_css = \Drupal::state('preprocess_css')->get() ?: 0;
        $preprocess_js =  \Drupal::state()->get('preprocess_js') ?: 0;
        $preprocess_css_or_js = $preprocess_css === '1' || $preprocess_js === '1';
        if ($preprocess_css_or_js) {
          $message .= '<br/>' . t('Preprocessing of css and/or js files is enabled. This is not supported when using the development directory option. Please disable preprocessing, and clear the cache');
        }

        drupal_set_message($message, 'warning', FALSE);
      }
    }

    switch ($type) {
      case 'validator':
        return new \H5PValidator($interface, $core);
      case 'storage':
        return new \H5PStorage($interface, $core);
      case 'contentvalidator':
        return new \H5PContentValidator($interface, $core);
      case 'export':
        return new \H5PExport($interface, $core);
      case 'interface':
        return $interface;
      case 'core':
        return $core;
    }
  }

  function getCoreSettings() {
    global $base_url;

    $user = \Drupal::currentUser();

    $option = array(
      'query' => array(
        'token' => \H5PCore::createToken('result')
      ),
    );
    $url = Url::fromUri('internal:/h5p-ajax/set-finished.json', $option);


    $option = array(
      'query' => array(
        'token' => \H5PCore::createToken('contentuserdata')
      ),
    );
    $content_user_data_url = Url::fromUri('internal:/h5p-ajax/content-user-data/:contentId/:dataType/:subContentId', $option);

    $h5p_save_content_state = \Drupal::state()->get('h5p_save_content_state') ?: 0;
    $h5p_save_content_frequency = \Drupal::state()->get('h5p_save_content_frequency') ?: 30;
    $settings = array(
      'baseUrl' => $base_url,
      'url' => $this->getH5PPath(),
      'postUserStatistics' =>  $user->id() > 0,
      'ajax' => array(
        'setFinished' => $url->toString(),
        'contentUserData' => str_replace('%3A', ':', $content_user_data_url->toString()),
      ),
      'saveFreq' => $h5p_save_content_state ? $h5p_save_content_frequency : FALSE,
      'l10n' => array(
        'H5P' => array( // Could core provide this?
          'fullscreen' => t('Fullscreen'),
          'disableFullscreen' => t('Disable fullscreen'),
          'download' => t('Download'),
          'copyrights' => t('Rights of use'),
          'embed' => t('Embed'),
          'size' => t('Size'),
          'showAdvanced' => t('Show advanced'),
          'hideAdvanced' => t('Hide advanced'),
          'advancedHelp' => t('Include this script on your website if you want dynamic sizing of the embedded content:'),
          'copyrightInformation' => t('Rights of use'),
          'close' => t('Close'),
          'title' => t('Title'),
          'author' => t('Author'),
          'year' => t('Year'),
          'source' => t('Source'),
          'license' => t('License'),
          'thumbnail' => t('Thumbnail'),
          'noCopyrights' => t('No copyright information available for this content.'),
          'downloadDescription' => t('Download this content as a H5P file.'),
          'copyrightsDescription' => t('View copyright information for this content.'),
          'embedDescription' => t('View the embed code for this content.'),
          'h5pDescription' => t('Visit H5P.org to check out more cool content.'),
          'contentChanged' => t('This content has changed since you last used it.'),
          'startingOver' => t("You'll be starting over."),
          'by' => t('by'),
          'showMore' => t('Show more'),
          'showLess' => t('Show less'),
          'subLevel' => t('Sublevel'),
          'confirmDialogHeader' => t('Confirm action'),
          'confirmDialogBody' => t('Please confirm that you wish to proceed. This action is not reversible.'),
          'cancelLabel' => t('Cancel'),
          'confirmLabel' => t('Confirm')
        )
      )
    );

    if ($user->id()) {
      $settings['user'] = array(
        'name' => $user->getDisplayName(),
        'mail' => $user->getEmail(),
      );
    }
    else {
      $option = array(
        'absolute' => TRUE,
      );
      $url = Url::fromUri('<front>', $option);
      $settings['siteUrl'] = $url;
    }

    return $settings;
  }

}
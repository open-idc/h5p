<?php

/**
 * @file
 * Contains \Drupal\h5p\Form\H5pAdminSettings.
 */

namespace Drupal\h5p\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class H5pAdminSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('h5p.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['h5p.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $form['h5p_display_options'] = [
      '#type' => 'fieldset',
      '#title' => t('Display Options'),
      '#attributes' => [
        'class' => [
          'h5p-action-bar-settings'
          ]
        ],
      '#attached' => [
        'js' => [
          drupal_get_path('module', 'h5p') . '/library/js/disable.js'
          ]
        ],
    ];

    $labels = _h5p_get_disable_labels();
    foreach (H5PCore::$disable as $bit => $name) {
      $name = ($bit & H5PCore::DISABLE_DOWNLOAD ? 'export' : $name);
      // @FIXME
      // // @FIXME
      // // The correct configuration object could not be determined. You'll need to
      // // rewrite this call manually.
      // $form['h5p_display_options']['h5p_' . $name] = array(
      //       '#type' => 'checkbox',
      //       '#title' => $labels[$bit],
      //       '#default_value' => variable_get('h5p_' . $name, TRUE)
      //     );

    }
    // TODO: Should we remove existing H5P files when export gets disabled?

    // Disable/enable the H5P icon below each H5P
    $form['h5p_display_options']['h5p_icon_in_action_bar'] = [
      '#type' => 'checkbox',
      '#title' => t('About H5P button'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_icon_in_action_bar'),
    ];

    $form['h5p_default_path'] = [
      '#type' => 'textfield',
      '#title' => t('Default h5p package path'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_default_path'),
      '#description' => t('Subdirectory in the directory %dir where files will be stored. Do not include trailing slash.', [
        '%dir' => \Drupal::service("stream_wrapper_manager")->getViaUri('public://')->realpath()
        ]),
    ];

    $h5p_nodes_exists = db_query("SELECT 1 FROM {node} WHERE type = :type", [
      ':type' => 'h5p_content'
      ])->fetchField();

    $form['h5p_revisioning'] = [
      '#type' => 'checkbox',
      '#title' => t('Save content files for each revision'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_revisioning'),
      '#description' => t("Disable this feature to save disk space. This value can't be changed if there are existing h5p nodes."),
      '#disabled' => $h5p_nodes_exists,
    ];

    // make sure core is loaded
    _h5p_get_instance('core');
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/h5p.settings.yml and config/schema/h5p.schema.yml.
    $form['h5p_whitelist'] = [
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => t('White list of accepted files.'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_whitelist'),
      '#description' => t("List accepted content file extensions for uploaded H5Ps. List extensions separated by space, eg. 'png jpg jpeg gif webm mp4 ogg mp3'. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/h5p.settings.yml and config/schema/h5p.schema.yml.
    $form['h5p_library_whitelist_extras'] = [
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => t('White list of extra accepted files in libraries.'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_library_whitelist_extras'),
      '#description' => t("Libraries might need to accept more files that should be allowed in normal contents. Add extra files here. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
    ];

    // TODO: Create a development section with multiple options?
    $form['h5p_dev_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable H5P development mode'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_dev_mode'),
      '#description' => t('Always update uploaded H5P libraries regardless of patch version. Read library data from file (semantics.json).'),
    ];

    $form['h5p_allow_communication_with_h5p_org'] = [
      '#type' => 'checkbox',
      '#title' => t('Get updates from h5p.org'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_allow_communication_with_h5p_org'),
      '#description' => t('Currently only tutorials are being updated, but in the future notification about code updates and more will be fetched from H5P.org'),
    ];

    //  $form['h5p_content_dev_mode'] = array(
    //    '#type' => 'checkbox',
    //    '#title' => t('Enable H5P content development mode'),
    //    '#default_value' => variable_get('h5p_content_dev_mode', '0'),
    //    '#description' => t("With this feature enabled content.json will be read from file. Changes to the content made using the editor won't be visible when this mode is actice."),
    //    '#disabled' => TRUE, // Disabled for now, since core is using a Drupal 6-only function
    //  );

    $form['h5p_library_development'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable library development directory (For programmers only)'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_library_development'),
      '#description' => t('Check to enable development of libraries in the %dev folder. ONLY ENABLE THIS OPTION IF YOU KNOW WHAT YOU ARE DOING! YOUR SITES H5P DATA WILL BE RUINED BY ENABLING THIS OPTION', [
        '%dev' => _h5p_get_h5p_path() . '/development'
        ]),
    ];

    $form['h5p_save_content_state'] = [
      '#type' => 'checkbox',
      '#title' => t('Save content state'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_save_content_state'),
      '#description' => t('Automatically save the current state of interactive content for each user. This means that the user may pick up where he left off.'),
    ];

    $form['h5p_save_content_frequency'] = [
      '#type' => 'textfield',
      '#title' => t('Save content state frequency'),
      '#default_value' => \Drupal::config('h5p.settings')->get('h5p_save_content_frequency'),
      '#description' => t("In seconds, how often do you wish the user to auto save their progress. Increasee this number if you're having issues with many ajax request."),
    ];

    // Make changes to the settings before passing them off to
    // system_settings_form_submit().
    $form['#submit'][] = 'h5p_admin_settings_submit';

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Try to create directories and warn the user of errors.
    $h5p_default_path = $form_state->getValue([
      'h5p_default_path'
      ]);
    $path = \Drupal::service("stream_wrapper_manager")->getViaUri('public://')->realpath() . '/' . $h5p_default_path;
    $temp_path = $path . '/' . 'temp';

    if (!file_prepare_directory($path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', t('You have specified an invalid directory.'));
    }
    if (!file_prepare_directory($temp_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', t('You have specified an invalid directory.'));
    }

    if (!is_numeric($form_state->getValue(['h5p_save_content_frequency'])) || $form_state->getValue([
      'h5p_save_content_frequency'
      ]) < 0) {
      $form_state->setErrorByName('h5p_save_content_frequency', t('You must specify a positive number.'));
    }
  }

  public function _submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Ensure that 'h5p_default_path' variable contains no trailing slash.
    $form_state->setValue(['h5p_default_path'], rtrim($form_state->getValue(['h5p_default_path']), '/\\'));
    // Ensure that the h5p white list is always stored in lower case.
    $form_state->setValue(['h5p_whitelist'], mb_strtolower($form_state->getValue(['h5p_whitelist'])));

    if ($form_state->getValue(['h5p_allow_communication_with_h5p_org']) == 0) {
      h5p_fetch_libraries_metadata(TRUE);
    }
  }

}

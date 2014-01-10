<?php
/**
 * Menu callback - Form builder function for settings.
 */
function h5p_admin_settings() {
  $form['h5p_default_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Default h5p package path'),
    '#default_value' => variable_get('h5p_default_path', 'h5p'),
    '#description' => t('Subdirectory in the directory %dir where files will be stored. Do not include trailing slash.', array('%dir' => file_directory_path())),
  );

  $h5p_nodes_exists = db_result(db_query(
    "SELECT 1 FROM {node} WHERE type = 'h5p_content'"
  ));

  $form['h5p_revisioning'] = array(
    '#type' => 'checkbox',
    '#title' => t('Save content files for each revision'),
    '#default_value' => variable_get('h5p_revisioning', '1'),
    '#description' => t("Disable this feature to save disk space. This value can't be changed if there are existing h5p nodes."),
    '#disabled' => $h5p_nodes_exists,
  );
  
  $form['h5p_export'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable export of all H5P content'),
    '#default_value' => variable_get('h5p_export', '1'),
    '#description' => t("With this feature enabled all H5P content will be downloadable as H5P files."),
  );
  
  // make sure core is loaded
  _h5p_get_instance('core');
  $form['h5p_whitelist'] = array(
    '#type' => 'textfield',
    '#maxlength' => 8192,
    '#title' => t('White list of accepted files.'),
    '#default_value' => variable_get('h5p_whitelist', H5PCore::$defaultContentWhitelist),
    '#description' => t("List accepted content file extensions for uploaded H5Ps. List extensions separated by space, eg. 'png jpg jpeg gif webm mp4 ogg mp3'. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
  );

  $form['h5p_library_whitelist_extras'] = array(
    '#type' => 'textfield',
    '#maxlength' => 8192,
    '#title' => t('White list of extra accepted files in libraries.'),
    '#default_value' => variable_get('h5p_library_whitelist_extras', H5PCore::$defaultLibraryWhitelistExtras),
    '#description' => t("Libraries might need to accept more files that should be allowed in normal contents. Add extra files here. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
  );
  
  $form['h5p_dev_mode'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable H5P development mode'),
    '#default_value' => variable_get('h5p_dev_mode', '0'),
    '#description' => t("With this feature enabled libraries with the same patch version will overwrite existing libraries in the database, and semantics.json will be read from file."),
  );
  
  $form['h5p_content_dev_mode'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable H5P content development mode'),
    '#default_value' => variable_get('h5p_content_dev_mode', '0'),
    '#description' => t("With this feature enabled content.json will be read from file. Changes to the content made using the editor won't be visible when this mode is actice."),
  );

  // Make changes to the settings before passing them off to
  // system_settings_form_submit().
  $form['#submit'][] = 'h5p_admin_settings_submit';

  return system_settings_form($form);
}

/**
 * Form validation handler for admin settings form.
 */
function h5p_admin_settings_validate($form, &$form_state) {
  // Try to create directories and warn the user of errors.
  $h5p_default_path = $form_state['values']['h5p_default_path'];
  $path = file_create_path(file_directory_path() . DIRECTORY_SEPARATOR . $h5p_default_path);
  $temp_path = $path . DIRECTORY_SEPARATOR . 'temp';

  if (!file_check_directory($path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS, 'h5p_default_path')) {
    form_set_error('h5p_default_path', t('You have specified an invalid directory.'));
  }
  if (!file_check_directory($temp_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS, 'h5p_default_path')) {
    form_set_error('h5p_default_path', t('You have specified an invalid directory.'));
  }
}

/**
 * Form submit handler for h5p admin settings form.
 */
function h5p_admin_settings_submit($form, &$form_state) {
  // Ensure that 'h5p_default_path' variable contains no trailing slash.
  $form_state['values']['h5p_default_path'] = rtrim($form_state['values']['h5p_default_path'], '/\\');
  // Ensure that the h5p white list is always stored in lower case.
  $form_state['values']['h5p_whitelist'] = mb_strtolower($form_state['values']['h5p_whitelist']);
}
/**
 * TODO
 * @return string
 */
function h5p_library_list() {
  $settings = array();
  
  // Get the libraries and usage from the database:
  $res = db_query('SELECT COUNT(n.library_id) as content_count, l.* 
                   FROM {h5p_libraries} l left JOIN {h5p_nodes_libraries} n ON l.library_id = n.library_id
                   GROUP BY l.library_id');
  
  while ($library = db_fetch_object($res)) {
    $settings['h5p']['libraries'][$library->machine_name] = array (
      'libraryId' => $library->library_id,
      'contentCount' => $library->content_count,
      'title' => $library->title,
      'majorVersion' => $library->major_version,
      'minorVersion' => $library->minor_version,
      'patchVersion' => $library->patch_version,
    );
  }
    
  drupal_add_js($settings, 'setting');
  
  // include JS
  return 'H5P Library list ';
}

/**
 * 
 */
function h5p_library_details($library_id) {
  return 'H5P details view: ' . $library_id;
}
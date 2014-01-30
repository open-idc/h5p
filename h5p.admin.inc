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
 * Creates the library list page
 * 
 * @return {string} Html string
 */
function h5p_library_list() {
  $settings = array();
  
  $libraries = array();
  
  // Get the libraries
  $res = db_query('SELECT * FROM {h5p_libraries} ORDER BY title ASC');
  while ($library = db_fetch_object($res)) {
    $library->content_count = 0;
    $libraries[$library->library_id] = $library;
  }
  
  // Get usage of each library:
  $res = db_query('SELECT COUNT(distinct n.nid) as content_count, l.library_id
                   FROM {h5p_libraries} l JOIN {h5p_nodes_libraries} nl ON l.library_id = nl.library_id JOIN {h5p_nodes} n ON nl.content_id = n.content_id  
                   GROUP BY l.library_id');
  
  while ($counter = db_fetch_object($res)) {
    $libraries[$counter->library_id]->content_count = $counter->content_count;
  }
  
  // Build result
  foreach ($libraries as $library) {
    $title = $library->title . ' (' . $library->major_version . '.' . $library->minor_version . '.' . $library->patch_version . ')';
    $settings['h5p']['libraries']['listData'][] = array(
      'name' => $title,
      'machineName' => $library->machine_name,
      'contentCount' => $library->content_count,
      'detailsUrl' => url('admin/content/h5p/libraries/' . $library->library_id),
      'deleteUrl' => url('admin/content/h5p/libraries/' . $library->library_id . '/delete', array('query' => drupal_get_destination()))
    );
  }
  
  // All translations are made server side
  $settings['h5p']['libraries']['listHeaders'] = array(t('Name'), t('Machine name'), t('Usage counter'), ''); 

  // Include libraries and more to Drupal setting
  drupal_add_js($settings, 'setting');
  
  // Add the needed css and javascript
  $module_path = drupal_get_path('module', 'h5p');
  _h5p_admin_add_generic_css_and_js($module_path);
  drupal_add_js($module_path . '/library/js/h5p-library-list.js');
  
  // Create the container which all admin content 
  // will appends it's data to. This id is used by h5pintegration
  // to find where to put the admin content. 
  return '<div class="h5p-admin-add-library"><div class="add-button">' . t('Add H5P libraries') . '</div>' . drupal_get_form('h5p_library_upload_form') . '</div><div id="h5p-admin-container"></div>';
}

/**
 * Creates the library list page
 * 
 * @param {string} $library_id The id of the library to be displayed
 * 
 * @return {string} Html string
 */
function h5p_library_details($library_id) {
  $settings = array();
  
  $library = db_fetch_object(db_query('SELECT * FROM {h5p_libraries} where library_id = %d', $library_id));
  
  // Build library info
  $settings['h5p']['library']['info'] = array(
    t('Name') => $library->title,
    t('Machine name') => $library->machine_name,
    t('Version') => $library->major_version . '.' . $library->minor_version . '.' . $library->patch_version,
    t('Runnable') => $library->runnable ? t('Yes') : t('No'),
    t('Fullscreen') => $library->fullscreen ? t('Yes') : t('No'),
  );
  
  // Build the translations needed
  $settings['h5p']['library']['translations'] = array(
    'contentCount' => t('Content count'),
    'noContent' => t('No content is using this library'),
    'contentHeader' => t('Content using this library'),
    'pageSizeSelectorLabel' => t('Elements per page'),
    'filterPlaceholder' => t('Filter content'),
    'pageXOfY' => t('Page $x of $y'),
  ); 

  // Build a list of the content using this library
  $nodes_res = db_query('SELECT DISTINCT n.nid, n.title FROM {h5p_nodes_libraries} l join {h5p_nodes} hn on l.content_id = hn.content_id join {node} n on hn.nid = n.nid  where library_id = %d order by n.title', $library_id);
  while ($node = db_fetch_object($nodes_res)) {
    $settings['h5p']['library']['content'][] = array(
      'title' => $node->title, 
      'url' => url('node/' . $node->nid),
    );
  }
  
  // Include all above in Drupal setting
  drupal_add_js($settings, 'setting');
  
  $module_path = drupal_get_path('module', 'h5p');
  _h5p_admin_add_generic_css_and_js($module_path);
  drupal_add_js($module_path . '/library/js/h5p-library-details.js');
  
  // Create the container which all admin content
  // will appends it's data to. This id is used by h5pintegration
  // to find where to put the admin content.
  return '<div id="h5p-admin-container"></div>';
}

/**
 * 
 * @param string $library_id
 */
function h5p_library_delete($library_id) {
  
  // Is library deletable ?
  $h5p_drupal = _h5p_get_instance('interface');
  $library_usage_counter = $h5p_drupal->getLibraryUsage($library_id);
  if ($library_usage_counter == 0) {
    // Create form:
    return drupal_get_form('h5p_library_delete_form', $library_id, _h5p_library_details_title($library_id));
  }
  else {
    // May not delete this one
    return t('Library is in use by content, and can therefore not be deleted');
  }
}

/**
 * Helper function - adds h5p admin css and js
 * 
 * @param {string} $module_path The H5P path 
 */
function _h5p_admin_add_generic_css_and_js($module_path) {
  drupal_add_js($module_path . '/js/h5pintegration.js');
  foreach (H5PCore::$adminScripts as $script) {
    drupal_add_js($module_path . '/library/' . $script);
  }
  drupal_add_css($module_path . '/library/styles/h5p-admin.css');
}

/**
 * Library upload form.
 */
function h5p_library_upload_form(&$form_state) {
  $form['#attributes'] = array(
    'enctype' => 'multipart/form-data'
  );

  $form['h5p'] = array(
    '#title' => t('H5P'),
    '#type' => 'file',
    '#description' => t('All the libraries from the selected package will be added.')
  );
  
  $form['submit'] = array(
    '#value' => t('Upload'),
    '#type' => 'submit',
  );

  return $form;
}

/**
 * Form validation.
 */
function h5p_library_upload_form_validate($form, &$form_state) {
  $validators = array(
    'file_validate_extensions' => array('h5p'),
  );
  $temporary_file_path = file_create_path(file_directory_path() . '/' . variable_get('h5p_default_path', 'h5p') . '/temp/' . uniqid('h5p-'));
  $file = file_save_upload('h5p', $validators, $temporary_file_path);
  if ($file === 0) {
    form_set_error('h5p', t('You must select a H5P file to upload.'));
  }
  else {
    // These has to be set instead of sending parameteres to the validation function.
    $_SESSION['h5p_upload'] = $file->filepath;
    $_SESSION['h5p_upload_folder'] = $temporary_file_path;
    $_SESSION['h5p_upload_skip_content'] = TRUE;
    
    $validator = _h5p_get_instance('validator');
    if ($validator->isValidPackage() === FALSE) {
      form_set_error('h5p', t('The uploaded file was not a valid h5p package'));
      // Maintain session variables.
      unset($_SESSION['h5p_upload'], $_SESSION['h5p_upload_folder'], $_SESSION['h5p_upload_skip_content']);
    }
  }
}

/**
 * Form submit handler.
 */
function h5p_library_upload_form_submit($form, &$form_state) {
  // Save package
  $h5p_core = _h5p_get_instance('storage');
  if ($h5p_core->savePackage(-31337) === TRUE) {
    _h5p_clear_js_and_css_cache();
  }
  
  // Maintain session variables.
  unset($_SESSION['h5p_upload'], $_SESSION['h5p_upload_folder'], $_SESSION['h5p_upload_skip_content']);
}


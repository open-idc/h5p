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
  $path = file_create_path(file_directory_path() . '/' . $h5p_default_path);
  $temp_path = $path . '/temp';

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
  $form_state['values']['h5p_default_path'] = rtrim($form_state['values']['h5p_default_path'], '/');
}
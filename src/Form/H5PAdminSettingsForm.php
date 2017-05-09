<?php

namespace Drupal\h5p\Form;

use Drupal\h5p\Helper;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Implements the H%P Admin Settings Form.
 */
class H5PAdminSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_admin_settings_form';
  }

  function buildForm(array $form, FormStateInterface $form_state) {

    // make sure core is loaded
    $helper = new Helper\H5PEnvironment();
    $core = $helper->getInstance('core');

    $path = drupal_get_path('module', 'h5p');

    // Get server setup error messages
    $server_setup_errors = $core->checkSetupErrorMessage()->errors;

    // todo $JM
    /*
    $disable_hub_data = array(
      'errors' => $server_setup_errors,
      'header' => $core->h5pF->t('Confirmation action'),
      'confirmationDialogMsg' => $core->h5pF->t('Do you still want to enable the hub ?'),
      'cancelLabel' => $core->h5pF->t('Cancel'),
      'confirmLabel' => $core->h5pF->t('Confirm')
    );
    $form['h5p_display_options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Display Options'),
      '#attached' => array(
        'js' => array(
          array(
            'data' => 'H5PDisableHubData = ' . json_encode($disable_hub_data) . ';',
            'type' => 'inline'
          ),
          $path . '/vendor/h5p/h5p-core/js/jquery.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-event-dispatcher.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-confirmation-dialog.js',
          $path . '/vendor/h5p/h5p-core/js/settings/h5p-disable-hub.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-display-options.js',
        ),
        'css' => array(
          $path . '/vendor/h5p/h5p-core/styles/h5p-confirmation-dialog.css',
          $path . '/vendor/h5p/h5p-core/styles/h5p.css',
          $path . '/vendor/h5p/h5p-core/styles/h5p-core-button.css'
        )
      )
    );
    */

    $button_behaviours = array(
      \H5PDisplayOptionBehaviour::NEVER_SHOW => t('Never show'),
      \H5PDisplayOptionBehaviour::ALWAYS_SHOW => t('Always show'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS => t('Show only if permitted through permissions'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON => t('Controlled by author, default is on'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF => t('Controlled by author, default is off'),
    );

    $h5p_frame = \Drupal::state()->get('h5p_frame');
    $h5p_export = \Drupal::state()->get('h5p_export') ?: \H5PDisplayOptionBehaviour::ALWAYS_SHOW;
    _h5p_add_display_option($form['h5p_display_options'], 'h5p_frame', t('Display buttons (download, embed and copyright)'), $h5p_frame, '.form-item-h5p-export, .form-item-h5p-embed, .form-item-h5p-copyright, .form-item-h5p-icon');
    $form['h5p_display_options']['h5p_export'] = array(
      '#title' => t('Download button'),
      '#options' => $button_behaviours,
      '#default_value' => $h5p_export,
      '#type' => 'select',
    );

    $h5p_embed = \Drupal::state()->get('h5p_embed') ?: \H5PDisplayOptionBehaviour::ALWAYS_SHOW;
    $form['h5p_display_options']['h5p_embed'] = array(
      '#title' => t('Embed button'),
      '#options' => $button_behaviours,
      '#default_value' => $h5p_embed,
      '#type' => 'select',
    );

    $h5p_copyright = \Drupal::state()->get('h5p_copyright');
    $h5p_icon = \Drupal::state()->get('h5p_icon') ?: TRUE;
    _h5p_add_display_option($form['h5p_display_options'], 'h5p_copyright', t('Copyright button'),$h5p_copyright);
    _h5p_add_display_option($form['h5p_display_options'], 'h5p_icon', t('About H5P button'), $h5p_icon);
    // TODO: Should we remove existing H5P files when export gets disabled?


    $h5p_default_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p';
    $dir = \Drupal::service('file_system')->realpath('public://');
    $form['h5p_default_path'] = array(
      '#type' => 'textfield',
      '#title' => t('Default h5p package path'),
      '#default_value' => $h5p_default_path,
      '#description' => t('Subdirectory in the directory %dir where files will be stored. Do not include trailing slash.', array('%dir' => $dir)),
    );

    $h5p_nodes_exists = db_query("SELECT 1 FROM {node} WHERE type = :type", array(':type' => 'h5p_content'))->fetchField();

    $h5p_revisioning = \Drupal::state()->get('h5p_revisioning') ?: 1;
    $form['h5p_revisioning'] = array(
      '#type' => 'checkbox',
      '#title' => t('Save content files for each revision'),
      '#default_value' => $h5p_revisioning,
      '#description' => t("Disable this feature to save disk space. This value can't be changed if there are existing h5p nodes."),
      '#disabled' => $h5p_nodes_exists,
    );


    $h5p_whitelist = \Drupal::state()->get('h5p_whitelist') ?: \H5PCore::$defaultContentWhitelist;
    $form['h5p_whitelist'] = array(
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => t('White list of accepted files.'),
      '#default_value' => $h5p_whitelist,
      '#description' => t("List accepted content file extensions for uploaded H5Ps. List extensions separated by space, eg. 'png jpg jpeg gif webm mp4 ogg mp3'. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
    );

    $h5p_library_whitelist_extras = \Drupal::state()->get('h5p_library_whitelist_extras') ?: \H5PCore::$defaultLibraryWhitelistExtras;
    $form['h5p_library_whitelist_extras'] = array(
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => t('White list of extra accepted files in libraries.'),
      '#default_value' =>$h5p_library_whitelist_extras,
      '#description' => t("Libraries might need to accept more files that should be allowed in normal contents. Add extra files here. Changing this list has security implications. Do not change it if you don't know what you're doing. Adding php to the list is for instance a security risk."),
    );

    // TODO: Create a development section with multiple options?
    $h5p_dev_mode = \Drupal::state()->get('h5p_dev_mode') ?: 0;
    $form['h5p_dev_mode'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable H5P development mode'),
      '#default_value' => $h5p_dev_mode,
      '#description' => t('Always update uploaded H5P libraries regardless of patch version. Read library data from file (semantics.json).')
    );

    $h5p_library_development = \Drupal::state()->get('h5p_library_development') ?: 0;
    $form['h5p_library_development'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable library development directory (For programmers only)'),
      '#default_value' => $h5p_library_development,
      '#description' => t('Check to enable development of libraries in the %dev folder. ONLY ENABLE THIS OPTION IF YOU KNOW WHAT YOU ARE DOING! YOUR SITES H5P DATA WILL BE RUINED BY ENABLING THIS OPTION', array('%dev' => _h5p_get_h5p_path() . '/development')),
    );

    $h5p_save_content_state = \Drupal::state()->get('h5p_save_content_state') ?: 0;
    $form['h5p_save_content_state'] = array(
      '#type' => 'checkbox',
      '#title' => t('Save content state'),
      '#default_value' => $h5p_save_content_state,
      '#description' => t('Automatically save the current state of interactive content for each user. This means that the user may pick up where he left off.'),
    );

    $h5p_save_content_frequency = \Drupal::state()->get('h5p_save_content_frequency') ?: 30;
    $form['h5p_save_content_frequency'] = array(
      '#type' => 'textfield',
      '#title' => t('Save content state frequency'),
      '#default_value' => $h5p_save_content_frequency,
      '#description' => t("In seconds, how often do you wish the user to auto save their progress. Increasee this number if you're having issues with many ajax request."),
    );

    $h5p_enable_lrs_content_types = \Drupal::state()->get('h5p_enable_lrs_content_types') ?: 0;
    $form['h5p_enable_lrs_content_types'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable LRS dependent content types'),
      '#default_value' => $h5p_enable_lrs_content_types,
      '#description' => t('Makes it possible to use content types that rely upon a Learning Record Store to function properly, like the Questionnaire content type.'),
    );

    $h5p_hub_is_enabled = \Drupal::state()->get('h5p_hub_is_enabled');
    $form['h5p_hub_is_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use H5P Hub'),
      '#default_value' => $h5p_hub_is_enabled,
      '#attributes' => array('class' => array('h5p-settings-disable-hub-checkbox')),
      '#description' => t("It's strongly encouraged to keep this option <strong>enabled</strong>. The H5P Hub provides an easy interface for getting new content types and keeping existing content types up to date. In the future, it will also make it easier to share and reuse content. If this option is disabled you'll have to install and update content types through file upload forms."),
    );

    // TODO: Be able to change site key
//  $site_key = variable_get('h5p_site_key', variable_get('h5p_site_uuid', ''));
//  $form['h5p_site_key'] = array(
//    '#type' => 'textfield',
//    '#title' => t('Site Key'),
//    '#default_value' => '',
//    '#attributes' => array(
//      'data-value' => $site_key,
//      'placeholder' => ($site_key ? '********-****-****-****-************' : t('Empty'))
//    ),
//    '#field_suffix' => '<button type="button" class="h5p-reveal-value" data-control="edit-h5p-site-key" data-hide="' . t('Hide') . '">' . t('Reveal') . '</button>',
//    '#description' => t("The site key is a secret that uniquely identifies this site with the Hub."),
//  );

    $h5p_send_usage_statistics = \Drupal::state()->get('h5p_send_usage_statistics');
    $form['h5p_send_usage_statistics'] = array(
      '#type' => 'checkbox',
      '#title' => t('Automatically contribute usage statistics'),
      '#default_value' => $h5p_send_usage_statistics,
      '#description' => t('Usage statistics numbers will automatically be reported to help the developers better understand how H5P is used and to determine potential areas of improvement.'),
    );

    // Set h5p settings class on form container
    $form['#attributes'] = array('class' => array('h5p-settings-container'));

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * Form validation handler for admin settings form.
   */
  function validateForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    // Try to create directories and warn the user of errors.
    $h5p_default_path = $values['h5p_default_path'];
    $path = \Drupal::service('file_system')->realpath('public://') . '/' . $h5p_default_path;
    $temp_path = $path . '/' . 'temp';

    if (! file_prepare_directory($path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', t('You have specified an invalid directory.'));
    }
    if (! file_prepare_directory($temp_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', t('You have specified an invalid directory.'));
    }

    if (! is_numeric($values['h5p_save_content_frequency']) || $values['h5p_save_content_frequency'] < 0) {
      $form_state->setErrorByName('h5p_save_content_frequency', t('You must specify a positive number.'));
    }
  }

  /**
   * Form submit handler for h5p admin settings form.
   */
  function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    // todo $JM use system config instead of drupal::state()
    \Drupal::state()->set('h5p_frame', $values['h5p_frame']);
    \Drupal::state()->set('h5p_export', $values['h5p_export']);
    \Drupal::state()->set('h5p_embed', $values['h5p_embed']);
    \Drupal::state()->set('h5p_copyright', $values['h5p_copyright']);
    \Drupal::state()->set('h5p_icon', $values['h5p_icon']);
    \Drupal::state()->set('h5p_default_path', $values['h5p_default_path']);
    \Drupal::state()->set('h5p_revisioning', $values['h5p_revisioning']);
    \Drupal::state()->set('h5p_whitelist', $values['h5p_whitelist']);
    \Drupal::state()->set('h5p_library_whitelist_extras', $values['h5p_library_whitelist_extras']);
    \Drupal::state()->set('h5p_dev_mode', $values['h5p_dev_mode']);
    \Drupal::state()->set('h5p_library_development', $values['h5p_library_development']);
    \Drupal::state()->set('h5p_save_content_state', $values['h5p_save_content_state']);
    \Drupal::state()->set('h5p_save_content_frequency', $values['h5p_save_content_frequency']);
    \Drupal::state()->set('h5p_enable_lrs_content_types', $values['h5p_enable_lrs_content_types']);
    \Drupal::state()->set('h5p_hub_is_enabled', $values['h5p_hub_is_enabled']);
    \Drupal::state()->set('h5p_send_usage_statistics', $values['h5p_send_usage_statistics']);

    // Ensure that 'h5p_default_path' variable contains no trailing slash.
    $values['h5p_default_path'] = rtrim($values['h5p_default_path'], '/\\');
    \Drupal::state()->set('h5p_default_path', $values['h5p_default_path']);
    // Ensure that the h5p white list is always stored in lower case.
    $values['h5p_whitelist'] = mb_strtolower($values['h5p_whitelist']);
    \Drupal::state()->set('h5p_whitelist', $values['h5p_whitelist']);

    // TODO: Be able to change site key
//  if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $form_state['values']['h5p_site_key'])) {
//    // Invalid key, use the old one
//    $form_state['values']['h5p_site_key'] = variable_get('h5p_site_key', variable_get('h5p_site_uuid', ''));
//  }
  }


}

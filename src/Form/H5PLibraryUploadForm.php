<?php
/**
 * @file H5PLibraryUploadForm
 *
 */

namespace Drupal\h5p\Form;

use Drupal\h5p\Helper;
use Drupal\h5p\H5PApi\H5PClasses;
use Drupal\h5p\H5PApi\H5PFileStorageInterface;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Implements teh UserRegisterPrivat form.
 */
class H5PLibraryUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_library_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes'] = array(
      'enctype' => 'multipart/form-data',
      'class' => 'h5p-admin-upload-libraries-form'
    );

    $form['h5p'] = array(
      '#title' => t('H5P'),
      '#type' => 'file',
      '#description' => t('Here you can upload new libraries or upload updates to existing libraries. Files uploaded here must be in the .h5p file format.')
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Upload'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateH5PFileUpload($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Save package
    $helper = new Helper\H5PEnvironment();

    $core = $helper->getInstance('storage');
    $core->savePackage(NULL, NULL, TRUE);

    // Maintain session variables.
    unset($_SESSION['h5p_upload'], $_SESSION['h5p_upload_folder']);

  }

  function validateH5PFileUpload(array &$form, FormStateInterface $form_state, $upgradeOnly = FALSE) {

    $values = $form_state->getValues();

    $validators = array(
      'file_validate_extensions' => array('h5p'),
    );
    // New uploads need to be saved in temp in order to be viewable
    // during node preview.

    $h5p_default_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p';
    $temporary_file_path = 'public://' . $h5p_default_path . '/temp/' . uniqid('h5p-');
    file_prepare_directory($temporary_file_path, FILE_CREATE_DIRECTORY);

    if ($file = file_save_upload('h5p', $validators, $temporary_file_path)) {
      // These has to be set instead of sending parameteres to the validation function.

      $uri = $file[0]->getFileUri();
      $_SESSION['h5p_upload'] = \Drupal::service('file_system')->realpath($uri);
      $_SESSION['h5p_upload_folder'] = \Drupal::service('file_system')->realpath($temporary_file_path);

      $helper = new Helper\H5PEnvironment();

      $validator = $helper->getInstance('validator');
      if ($validator->isValidPackage(TRUE, $upgradeOnly) === FALSE) {
        $form_state->setErrorByName('h5p', t('The uploaded file was not a valid h5p package'));
        // Maintain session variables.
        unset($_SESSION['h5p_upload'], $_SESSION['h5p_upload_folder']);
      }
    } elseif (! isset($SESSION['h5p']['node']['nid']) && empty($values['h5p']) && empty($_SESSION['h5p_upload'])) {
      $form_state->setErrorByName('h5p', t('You must upload a h5p file.'));
    }
  }
}
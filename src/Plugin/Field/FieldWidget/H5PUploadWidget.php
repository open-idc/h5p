<?php

namespace Drupal\H5P\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormState;
use Drupal\h5p\H5PDrupal\H5PDrupal;

/**
 * Plugin implementation of the 'h5p_upload' widget.
 *
 * @FieldWidget(
 *   id = "h5p_upload",
 *   label = @Translation("H5P Upload"),
 *   field_types = {
 *     "h5p"
 *   }
 * )
 */
class H5PUploadWidget extends WidgetBase {

  protected $massagedValues;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element += [
      '#type' => 'fieldset',
    ];

    $element['h5p_file'] = [
      '#type' => 'file',
      '#title' => t('H5P Upload'),
      '#description' => t('Select a .h5p file to upload and create interactive content from. You can find <a href="http://h5p.org/content-types-and-applications" target="_blank">example files</a> on H5P.org'),
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];

    $element['h5p_content_id'] = [
      '#type' => 'value',
      '#value' => $items[$delta]->h5p_content_id
    ];

    return array('h5p_upload' => $element);
  }

  /**
   * Validate the h5p file upload
   */
  public function validate($element, FormStateInterface $form_state) {

    $file_field = $element['#parents'][0];

    if (empty($_FILES['files']['name'][$file_field])) {
      return; // Only need to validate if the field actually has a file
    }

    // Prepare file validators
    $validators = array(
      'file_validate_extensions' => array('h5p'),
    );

    // Prepare temp folder
    $h5p_path = \Drupal::state()->get('h5p_default_path') ?: 'h5p'; // TODO: Use \Drupal::config()->get() ?
    $temporary_file_path = "public://{$h5p_path}/temp/" . uniqid('h5p-');
    file_prepare_directory($temporary_file_path, FILE_CREATE_DIRECTORY);

    // Validate file
    $files = file_save_upload($file_field, $validators, $temporary_file_path);
    if (empty($files[0])) {
      // Validation failed
      $form_state->setError($element, t("The uploaded file doesn't have the required '.h5p' extension"));
      return;
    }

    // Tell H5P Core where to look for the files
    $interface = H5PDrupal::getInstance();
    $interface->getUploadedH5pPath(\Drupal::service('file_system')->realpath($files[0]->getFileUri()));
    $interface->getUploadedH5pFolderPath(\Drupal::service('file_system')->realpath($temporary_file_path));

    // Call upon H5P Core to validate the contents of the package
    $validator = H5PDrupal::getInstance('validator');
    if (!$validator->isValidPackage()) {
      $form_state->setError($element, t("The contents of the uploaded '.h5p' file was not valid."));
      return;
    }

    // Indicate that we have a valid file upload
    $form_state->setValue($element['#parents'], 1);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // Only one message per widget
    if (empty($this->massagedValues)) {

      // Get value from form
      $h5p_content_id = $values[0]['h5p_upload']['h5p_content_id'];

      if (!FormState::hasAnyErrors() && $values[0]['h5p_upload']['h5p_file'] === 1) {
        $storage = H5PDrupal::getInstance('storage');
        $storage->savePackage($h5p_content_id);
        $h5p_content_id = $storage->contentId;
      }

      $this->massagedValues = [
        'h5p_content_id' => (int) $h5p_content_id,
      ];
    }

    return $this->massagedValues;
  }

}

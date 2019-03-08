<?php

namespace Drupal\h5p\Plugin\Field\FieldWidget;

use Drupal\h5p\Plugin\Field\H5PWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\Plugin\Field\FieldType\H5PItem;

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
class H5PUploadWidget extends H5PWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parentElement = parent::formElement($items, $delta, $element, $form, $form_state);
    $element = &$parentElement['h5p_content'];
    if (empty($element['id'])) {
      return $parentElement; // No content id, use parent element
    }

    $field_name = $this->fieldDefinition->getName();
    $element['file'] = [
      '#name' => "files[{$field_name}_{$delta}]",
      '#type' => 'file',
      '#title' => t('H5P Upload'),
      '#description' => t('Select a .h5p file to upload and create interactive content from. You can find <a href="http://h5p.org/content-types-and-applications" target="_blank">example files</a> on H5P.org'),
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];

    return $parentElement;
  }

  /**
   * Validate the h5p file upload
   */
  public function validate($element, FormStateInterface $form_state) {

    list($field_name, $delta) = $element['#parents'];
    $file_field = "{$field_name}_{$delta}";
    if (empty($_FILES['files']['name'][$file_field])) {
      return; // Only need to validate if the field actually has a file
    }

    // Prepare file validators
    $validators = [
      'file_validate_extensions' => ['h5p'],
    ];

    // Prepare temp folder
    $interface = H5PDrupal::getInstance('interface', $file_field);
    $h5p_path = $interface->getOption('default_path', 'h5p');
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
    $interface->getUploadedH5pPath(\Drupal::service('file_system')->realpath($files[0]->getFileUri()));
    $interface->getUploadedH5pFolderPath(\Drupal::service('file_system')->realpath($temporary_file_path));

    // Call upon H5P Core to validate the contents of the package
    $validator = H5PDrupal::getInstance('validator', $file_field);
    if (!$validator->isValidPackage()) {
      $form_state->setError($element, t("The contents of the uploaded '.h5p' file was not valid."));
      $files[0]->delete();
      return;
    }
    $files[0]->delete();

    foreach ($validator->h5pC->mainJsonData['preloadedDependencies'] as $dep) {
      if ($dep['machineName'] === $validator->h5pC->mainJsonData['mainLibrary']) {
        if ($validator->h5pF->libraryHasUpgrade($dep)) {
          // We do not allow storing old content due to security concerns
          $form_state->setError($element, t("You're trying to upload content of an older version of H5P. Please upgrade the content on the server it originated from and try to upload again or turn on the H5P Hub to have this server upgrade it for your automaticall."));
          return;
        }
      }
    }

    // Indicate that we have a valid file upload
    $form_state->setValue($element['#parents'], 1);
  }

  /**
   * {@inheritdoc}
   */
  protected function massageFormValue(array $value, $delta, $do_new_revision) {
    // Prepare default messaged return values
    $return_value = [
      'h5p_content_id' => $value['id'],
      'h5p_content_new_translation' => $value['new_translation'],
    ];

    // Determine if we're clearing the content
    if ($value['clear_content']) {
      $return_value['h5p_content_id'] = NULL;
      $return_value['h5p_content_revisioning_handled'] = TRUE;

      if ($value['id'] && !$do_new_revision && !$value['new_translation']) {
        // Not a new revision, delete existing content
        H5PItem::deleteH5PContent($value['id']);
      }

      return $return_value;
    }

    // Determine if a H5P file has been uploaded
    $file_is_uploaded = ($value['file'] === 1);
    if (!$file_is_uploaded) {
      return $return_value; // No new file, keep existing value
    }

    // Store the uploaded file
    $field_name = $this->fieldDefinition->getName();
    $storage = H5PDrupal::getInstance('storage', "{$field_name}_{$delta}");

    if ($value['id']) {
      // Load existing content
      $h5p_content = H5PContent::load($value['id']);
    }
    if (empty($h5p_content)) {
      // Invalid content, probably deleted
      $return_value['h5p_content_id'] = NULL;
    }

    $core = H5PDrupal::getInstance('core');
    $content = [
      'uploaded' => TRUE, // Used when logging event in insertContent or updateContent
      'disable' => $core->getStorableDisplayOptions($value, !empty($h5p_content) ? $h5p_content->get('disabled_features')->value : 0),
    ];

    $has_content = !empty($return_value['h5p_content_id']);
    if ($has_content && !$do_new_revision && !$value['new_translation']) {
      // Use existing id = update existing content
      $content['id'] = $return_value['h5p_content_id'];
    }

    // Save and update content id
    $storage->savePackage($content);
    $return_value['h5p_content_id'] = $storage->contentId;
    $return_value['h5p_content_revisioning_handled'] = TRUE;

    return $return_value;
  }

}

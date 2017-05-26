<?php

namespace Drupal\h5p\Plugin\Field\FieldWidget;

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
    // Prevent setting default value
    if ($this->isDefaultValueWidget($form_state)) {
      $element += [
        '#type' => 'markup',
        '#markup' => '<p>' . t('Currently, not supported.'). '</p>',
      ];
      return array('h5p_upload' => $element);
    }

    $element += [
      '#type' => 'fieldset',
    ];

    $field_name = $this->fieldDefinition->getName();
    $element['h5p_file'] = [
      '#name' => "files[{$field_name}_{$delta}]",
      '#type' => 'file',
      '#title' => t('H5P Upload'),
      '#description' => t('Select a .h5p file to upload and create interactive content from. You can find <a href="http://h5p.org/content-types-and-applications" target="_blank">example files</a> on H5P.org'),
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];

    $h5p_export = \Drupal::state()->get('h5p_export') ?: \H5PDisplayOptionBehaviour::ALWAYS_SHOW;
    $element['h5p_file_options'] = [
      '#type' => 'checkbox',
      '#title' => t('Display buttons (download, embed and copyright)'),
      '#default_value' => 1
    ];

    $h5p_copyright = \Drupal::state()->get('h5p_copyright');
    $element['h5p_file_options_copyright'] = [
      '#type' => 'checkbox',
      '#title' => t('Copyright button'),
      '#states' => [
        'visible' => [
          ':input[name="field_h5p[' . $delta  . '][h5p_upload][h5p_file_options]"]' => array('checked' => TRUE)
        ]
      ]
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

    list($field_name, $delta) = $element['#parents'];
    $file_field = "{$field_name}_{$delta}";
    if (empty($_FILES['files']['name'][$file_field])) {
      return; // Only need to validate if the field actually has a file
    }

    // Prepare file validators
    $validators = array(
      'file_validate_extensions' => array('h5p'),
    );

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
      return;
    }

    // Indicate that we have a valid file upload
    $form_state->setValue($element['#parents'], 1);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // We only message after validation has completed
    if (!$form_state->isValidationComplete()) {
      return $values;
    }

    // Determine if new revisions should be made
    $do_new_revision = self::doNewRevision($form_state);

    $return_values = [];
    foreach ($values as $delta => $value) {
      // Massage out each H5P Upload from the submitted form
      $return_values[$delta] = $this->massageFormValue($value['h5p_upload'], $delta, $do_new_revision);
    }

    return $return_values;
  }

  /**
   * Help message out each value from the submitted form
   *
   * @param array $value
   * @param integer $delta
   * @param boolean $do_new_revision
   */
  private function massageFormValue(array $value, $delta, $do_new_revision) {
    // Prepare default messaged return values
    $return_value = [
      'h5p_content_id' => $value['h5p_content_id'],
    ];

    // Determine if a H5P file has been uploaded
    $file_is_uploaded = ($value['h5p_file'] === 1);
    if (!$file_is_uploaded) {
      return $return_value; // No new file
    }

    // Store the uploaded file
    $field_name = $this->fieldDefinition->getName();
    $storage = H5PDrupal::getInstance('storage', "{$field_name}_{$delta}");

    $content = [
      'uploaded' => TRUE, // Used when logging event in insertContent or updateContent
    ];

    $has_content = !empty($return_value['h5p_content_id']);
    if ($has_content && !$do_new_revision) {
      // Use existing id = update existing content
      $content['id'] = $return_value['h5p_content_id'];
    }

    // Save and update content id
    $storage->savePackage($content);
    $return_value['h5p_content_id'] = $storage->contentId;
    $return_value['h5p_content_revisioning_handled'] = TRUE;

    return $return_value;
  }

  /**
   * Determine if the current entity is creating a new revision.
   * This is useful to avoid changing the H5P content belonging to
   * an older revision of the entity.
   *
   * @param FormStateInterface $form_state
   * @return boolean
   */
  public static function doNewRevision(FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    $entity = $form_object->getEntity();

    // Determine if this is a new revision
    $is_new_revision = ($entity->getEntityType()->hasKey('revision') && $form_state->getValue('revision'));

    // Determine if we do revisioning for H5P content
    // (may be disabled to save disk space)
    $interface = H5PDrupal::getInstance();
    return $interface->getOption('revisioning', TRUE) && $is_new_revision;
  }

}

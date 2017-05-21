<?php

namespace Drupal\H5PEditor\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;


/**
 * Plugin implementation of the 'h5p_editor' widget.
 *
 * @FieldWidget(
 *   id = "h5p_editor",
 *   label = @Translation("H5P Editor"),
 *   field_types = {
 *     "h5p"
 *   }
 * )
 */
class H5PEditorWidget extends WidgetBase {

  private $content_id;
  private $params;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Don't allow setting default values
    if ($this->isDefaultValueWidget($form_state)) {
      return array('value' => $element);
    }

    $values = $items->getValue();

    // Get content id
    $this->content_id = 0;
    if (isset($values) && isset($values[$delta]['h5p_content_id'])) {
      $this->content_id = (int) $values[$delta]['h5p_content_id'];
    }

    // Load existing content settings
    if ($this->content_id) {
      $h5p_content = H5PContent::load($this->content_id);
      $this->params = $h5p_content->get('parameters')->value;
      $library = $h5p_content->getLibrary();
      $formatted_library = array(
        'machineName' => $library->name,
        'majorVersion' => $library->major,
        'minorVersion' => $library->minor,
      );
      $library_string = \H5PCore::libraryToString($formatted_library);
    }

    // Always default to create for editor widget
    $form['h5p_type']['#default_value'] = 'create';
    $form['h5p_type']['#type'] = 'hidden';

    $integration = H5PDrupal\H5PDrupal::getGenericH5PIntegrationSettings();
    $settings = h5p_get_editor_settings($this->content_id);

    $element += array(
      '#type' => 'item',
      '#title' => t('Content type'),
      '#markup' => '<div class="h5p-editor">' . t('Waiting for javascript...') . '</div>',
      '#attached' => array(
        'drupalSettings' => array(
          'h5p' => array(
            'H5PIntegration' => $integration,
            'drupal_h5p_editor' => $settings,
          ),
        ),
        'library' => array(
          'h5peditor/h5peditor',
        ),
      ),
    );

    $element['json_content'] = array(
      '#type' => 'hidden',
      '#default_value' => isset($this->params) ? $this->params : '',
    );

    $element['h5p_library'] = array(
      '#type' => 'hidden',
      '#default_value' => isset($library_string) ? $library_string : '',
    );

    return array('value' => $element);
  }

  /**
   * Validate the color text field.
   */
  public function validate($element, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Only save content when validation has completed
    if (!$form_state->isValidationComplete()) {
      return $values;
    }

    $library_string = $values[0]['value']['h5p_library'];
    $params = $values[0]['value']['json_content'];

    $library = h5peditor_get_library_property($library_string);
    $library['libraryId'] = h5peditor_get_library_property($library_string, 'libraryId');

    // TODO: Handle cloning, revisioning and translating

    // Save to db
    $core = H5PDrupal\H5PDrupal::getInstance('core');
    $libraryData = array(
      'id' => $this->content_id ? $this->content_id : NULL,
      'library' => $library,
      'params' => $params
    );
    $h5p_content_id = $core->saveContent($libraryData);

    // Move files.
    $editor = h5peditor_get_instance();

    // Find old data for comparison
    if ($this->content_id) {
      $h5p_content = H5PContent::load($this->content_id);
      $library_data = $h5p_content->getLibrary();
      $old_library = array(
        'name' => $library_data->name,
        'majorVersion' => $library_data->major,
        'minorVersion' => $library_data->minor
      );
      $old_params = $this->params;
    }

    // Keep new files, delete files from old parameters
    $editor->processParameters(
      $h5p_content_id,
      $library,
      json_decode($params),
      isset($old_library) ? $old_library : NULL,
      isset($old_params) ? json_decode($old_params) : NULL
    );

    return [
      'h5p_content_id' => (int) $h5p_content_id,
    ];
  }
}

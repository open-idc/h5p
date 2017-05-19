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

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $values = $items->getValue();
    if (isset($values) && isset($values[$delta]['h5p_content_id'])) {
      $h5p_content = H5PContent::load((int) $values[$delta]['h5p_content_id']);
      $params = $h5p_content->get('parameters')->value;
      $library = $h5p_content->getLibrary();
      $formatted_library = array(
        'machineName' => $library->name,
        'majorVersion' => $library->major,
        'minorVersion' => $library->minor,
      );
      $library_string = \H5PCore::libraryToString($formatted_library);
    }

    $hub_is_enabled = TRUE;
    if ($hub_is_enabled) {
      $form['h5p_type']['#default_value'] = 'create';
      $form['h5p_type']['#type'] = 'hidden';
    }


    $integration = H5PDrupal\H5PDrupal::getGenericH5PIntegrationSettings();
    $settings = h5p_get_editor_settings();

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
      '#default_value' => isset($params) ? $params : '',
    );

    // TODO: Are we using this ?
    $element['main_library_id'] = array(
      '#type' => 'value',
      '#default_value' => '',
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

    $content_id = 0;
    $library_string = $values[0]['value']['h5p_library'];
    $params = $values[0]['value']['json_content'];

    $library = h5peditor_get_library_property($library_string);
    $library['libraryId'] = h5peditor_get_library_property($library_string, 'libraryId');

    // TODO: Handle cloning, revisioning and translating

    // Save to db
    $core = H5PDrupal\H5PDrupal::getInstance('core');
    $libraryData = array(
      'id' => $content_id ? $content_id : NULL,
      'library' => $library,
      'params' => $params
    );
    $h5p_content_id = $core->saveContent($libraryData);

    return [
      'h5p_content_id' => (int) $h5p_content_id,
    ];
  }
}

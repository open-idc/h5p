<?php

namespace Drupal\H5PEditor\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;
use Drupal\h5p\Plugin\Field\FieldWidget\H5PUploadWidget;

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
      $element += [
        '#type' => 'markup',
        '#markup' => '<p>' . t('Currently, not supported.'). '</p>',
      ];
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

    $integration = H5PDrupal::getGenericH5PIntegrationSettings();
    $settings = H5PEditorUtilities::getEditorSettings($items->getName(), $delta, $this->content_id);

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

    $element['h5p_frame'] = [
      '#type' => 'checkbox',
      '#title' => t('Display buttons (download, embed and copyright)'),
      '#default_value' => 1
    ];

    // Only show a checkbox if H5PAdminSettingsForm allow author to change its value
    $h5p_export = \Drupal::state()->get('h5p_export');
    $h5p_export_default_value = ($h5p_export == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON ? 1 : 0);
    if ($h5p_export == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON || $h5p_export == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF) {
      $element['h5p_export'] = [
        '#type' => 'checkbox',
        '#title' => t('Download button'),
        '#default_value' => $h5p_export_default_value,
        '#states' => [
          'visible' => [
            ':input[name="field_' . $element['#title'] . '[' . $delta  . '][value][h5p_frame]"]' => array('checked' => TRUE)
          ]
        ]
      ];
    } else {
      $element['h5p_export'] = [
        '#type' => 'value',
        '#value' => $h5p_export
      ];
    }

    // Only show a checkbox if H5PAdminSettingsForm allow author to change its value
    $h5p_embed = \Drupal::state()->get('h5p_embed');
    $h5p_embed_default_value = ($h5p_embed == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON ? 1 : 0);
    if ($h5p_embed == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON || $h5p_embed == \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF) {
      $element['h5p_embed'] = [
        '#type' => 'checkbox',
        '#title' => t('Embed button'),
        '#default_value' => $h5p_embed_default_value,
        '#states' => [
          'visible' => [
            ':input[name="field_' . $element['#title'] . '[' . $delta  . '][value][h5p_frame]"]' => array('checked' => TRUE)
          ]
        ]
      ];
    } else {
      $element['h5p_embed'] = [
        '#type' => 'value',
        '#value' => $h5p_embed
      ];
    }

    $h5p_copyright = \Drupal::state()->get('h5p_copyright');
    $element['h5p_file_options_copyright'] = [
      '#type' => 'checkbox',
      '#title' => t('Copyright button'),
      '#default_value' => $h5p_copyright,
      '#states' => [
        'visible' => [
          ':input[name="field_' . $element['#title'] . '[' . $delta  . '][value][h5p_frame]"]' => array('checked' => TRUE)
        ]
      ]
    ];

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
    $library = H5PEditorUtilities::getLibraryProperty($library_string);

    // Determine if we need to create a new revision of the content
    $do_new_revision = H5PUploadWidget::doNewRevision($form_state);

    // Save to db
    $core = H5PDrupal::getInstance('core');
    $libraryData = array(
      'id' => $this->content_id && !$do_new_revision ? $this->content_id : NULL,
      'library' => $library,
      'params' => $params
    );
    $h5p_content_id = $core->saveContent($libraryData);

    if ($do_new_revision && $this->content_id) {
      // Copy content folder of old revision to get the uploaded files
      $core = H5PDrupal::getInstance('core');
      $core->fs->cloneContent($this->content_id, $h5p_content_id);
    }

    // Move files.
    $editor = H5PEditorUtilities::getInstance();

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
      'h5p_content_revisioning_handled' => TRUE,
    ];
  }
}

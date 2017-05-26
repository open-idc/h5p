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
      return ['value' => $element];
    }

    $field_name = $items->getName();

    // Element contains multiple form elements
    $element += [
      '#type' => 'fieldset',
    ];

    $h5p_content_id = $items[$delta]->h5p_content_id;

    // Keep track of current Content ID
    $element['h5p_content_id'] = [
      '#type' => 'value',
      '#value' => $h5p_content_id,
    ];

    if ($h5p_content_id) {
      // Load H5P Content entity
      $h5p_content = H5PContent::load($h5p_content_id);
    }

    $element['parameters'] = [
      '#type' => 'hidden',
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getFilteredParameters(),
    ];

    $element['library'] = [
      '#type' => 'hidden',
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getLibraryString(),
    ];

    // Add editor element
    $element['editor'] = [
      '#type' => 'item',
      '#title' => t('Content type'),
      '#markup' => '<div class="h5p-editor" data-field="' . $field_name . '" data-delta="' . $delta . '"' . (empty($h5p_content) ? '' : ' data-content-id="' . $h5p_content_id . '"') . '>' . t('Waiting for javascript...') . '</div>',
      '#attached' => [
        'drupalSettings' => [
          'h5p' => [
            'H5PIntegration' => H5PDrupal::getGenericH5PIntegrationSettings()
          ],
          'h5peditor' => H5PEditorUtilities::getEditorSettings($h5p_content_id),
        ],
        'library' => [
          'h5peditor/h5peditor',
        ],
      ],
    ];

    // Make it possible to clear a field
    $element['h5p_clear_content'] = [
      '#type' => 'checkbox',
      '#title' => t('Clear content'),
      '#description' => t('Warning! Your content will be completely deleted'),
      '#default_value' => 0
    ];

    $element['h5p_frame'] = [
      '#type' => 'checkbox',
      '#title' => t('Display buttons (download, embed and copyright)'),
      '#default_value' => 1
    ];
    $h5p_frame_selector = ':input[name="' . $field_name . '[' . $delta  . '][value][h5p_frame]"]';

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
            "$h5p_frame_selector" => ['checked' => TRUE],
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
            "$h5p_frame_selector" => ['checked' => TRUE],
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
          "$h5p_frame_selector" => ['checked' => TRUE],
        ]
      ]
    ];

    return ['value' => $element];
  }

  /**
   * Delete content by id
   *
   * @param int $content_id Content id
   */
  private function deleteContent($content_id) {
    if ($content_id) {
      $h5p_content = H5PContent::load($content_id);
      $h5p_content->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // Only save content when validation has completed
    if (!$form_state->isValidationComplete()) {
      return $values;
    }

    // Skip saving content if no library
    $library_string = $values[0]['value']['library'];
    if (!$library_string) {
      return [
        'h5p_content_id' => NULL,
      ];
    }

    // Content has been cleared
    $clear_field = $values[0]['value']['h5p_clear_content'];
    if ($clear_field) {
      $content_id = $values[0]['value']['h5p_content_id'];
      $this->deleteContent($content_id);
      return [
        'h5p_content_id' => NULL,
      ];
    }

    // Determine if new revisions should be made
    $do_new_revision = H5PUploadWidget::doNewRevision($form_state);

    $return_values = [];
    foreach ($values as $delta => $value) {
      // Massage out each H5P Upload from the submitted form
      $return_values[$delta] = $this->massageFormValue($value['value'], $delta, $do_new_revision);
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
      'h5p_content_revisioning_handled' => TRUE,
      'h5p_content_id' => $value['h5p_content_id'],
    ];

    // Load existing content
    if ($value['h5p_content_id']) {
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      $old_library = $h5p_content->getLibrary(TRUE);
      $old_library['name'] = $old_library['machineName'];
      $old_params = $h5p_content->getParameters();
    }
    else {
      $old_library = NULL;
      $old_params = NULL;
    }

    // Prepare content values
    $core = H5PDrupal::getInstance('core');
    $content = [
      'library' => H5PEditorUtilities::getLibraryProperty($value['library']),
      'params' => $value['parameters'],
    ];
    if ($value['h5p_content_id'] && !$do_new_revision) {
      $content['id'] = $value['h5p_content_id'];
    }

    // Save the new content
    $return_value['h5p_content_id'] = $core->saveContent($content);

    // If we had existing content and did a new revision we need to make a copy
    // of the content folder from the old revision
    if ($value['h5p_content_id'] && $do_new_revision) {
      $core->fs->cloneContent($value['h5p_content_id'], $return_value['h5p_content_id']);
    }

    // Keep new files, delete files from old parameters
    $editor = H5PEditorUtilities::getInstance();
    $editor->processParameters(
      $return_value['h5p_content_id'],
      $content['library'],
      json_decode($content['params']),
      $old_library,
      $old_params
    );

    return $return_value;
  }

}

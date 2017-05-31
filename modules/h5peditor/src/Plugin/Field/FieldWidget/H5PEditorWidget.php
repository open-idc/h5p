<?php

namespace Drupal\h5peditor\Plugin\Field\FieldWidget;

use Drupal\h5p\Plugin\Field\H5PWidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\Plugin\Field\FieldType\H5PItem;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;

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
class H5PEditorWidget extends H5PWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parentElement = parent::formElement($items, $delta, $element, $form, $form_state);
    $element = &$parentElement['h5p_content'];
    if (empty($element['id'])) {
      return $parentElement; // No content id, use parent element
    }

    $field_name = $items->getName();

    $h5p_content_id = $items[$delta]->h5p_content_id;
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

    return $parentElement;
  }

  /**
   * Help message out each value from the submitted form
   *
   * @param array $value
   * @param integer $delta
   * @param boolean $do_new_revision
   */
  protected function massageFormValue(array $value, $delta, $do_new_revision) {
    // Prepare default messaged return values
    $return_value = [
      'h5p_content_revisioning_handled' => TRUE,
      'h5p_content_id' => $value['id'],
    ];

    // Skip saving content if no library is selector, or clearing content
    if (!$value['library'] || $value['clear_content']) {
      $return_value['h5p_content_id'] = NULL;

      if ($value['id'] && !$do_new_revision) {
        // Not a new revision, delete existing content
        H5PItem::deleteH5PContent($value['id']);
      }

      return $return_value;
    }

    // Load existing content
    if ($value['id']) {
      $h5p_content = H5PContent::load($value['id']);
      $old_library = $h5p_content->getLibrary(TRUE);
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
      'disable' => $core->getStorableDisplayOptions($value, !empty($h5p_content) ? $h5p_content->get('disabled_features')->value : 0),
    ];
    if ($value['id'] && !$do_new_revision) {
      $content['id'] = $value['id'];
    }

    // Save the new content
    $return_value['h5p_content_id'] = $core->saveContent($content);

    // If we had existing content and did a new revision we need to make a copy
    // of the content folder from the old revision
    if ($value['id'] && $do_new_revision) {
      $core->fs->cloneContent($value['id'], $return_value['h5p_content_id']);
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

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

  protected static $counter = 0;

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
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getEditorJSON(),
      '#attributes' => [
        'id' => "h5p-content-parameters-" . self::$counter,
      ],
    ];

    $element['library'] = [
      '#type' => 'hidden',
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getLibraryString(),
      '#attributes' => [
        'id' => "h5p-content-library-" . self::$counter,
      ],
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];
    self::$counter++;

    // Add editor element
    $element['editor'] = [
      '#type' => 'item',
      '#title' => t('Content type'),
      '#markup' => '<div class="h5p-editor" data-parametersid="' . $element['parameters']['#attributes']['id'] . '" data-libraryid="' . $element['library']['#attributes']['id'] . '"' . (empty($h5p_content) ? '' : ' data-content-id="' . $h5p_content_id . '"') . '>' . t('Waiting for javascript...') . '</div>',
      '#attached' => [
        'drupalSettings' => [
          'h5p' => [
            'H5PIntegration' => H5PDrupal::getGenericH5PIntegrationSettings()
          ],
          'h5peditor' => H5PEditorUtilities::getEditorSettings(),
        ],
        'library' => [
          'h5peditor/h5peditor',
        ],
      ],
    ];

    return $parentElement;
  }

  /**
   * Validate the h5p file upload
   */
  public function validate($element, FormStateInterface $form_state) {
    // Check that this is the latest version of the content type we have
    $core = H5PDrupal::getInstance('core');
    if ($core->h5pF->libraryHasUpgrade(\H5PCore::libraryFromString($element['#value']))) {
      // We do not allow storing old content due to security concerns
      $form_state->setError($element, t('Something unexpected happened. We were unable to save this content.'));
    }
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
      'h5p_content_new_translation' => $value['new_translation'],
    ];

    // Skip saving content if no library is selector, or clearing content
    if (!$value['library'] || $value['clear_content']) {
      $return_value['h5p_content_id'] = NULL;

      if ($value['id'] && !$do_new_revision && !$value['new_translation']) {
        // Not a new revision or translation, delete existing content
        H5PItem::deleteH5PContent($value['id']);
      }

      return $return_value;
    }

    // Load existing content
    $h5p_content = $value['id'] ? H5PContent::load($value['id']) : NULL;
    $old_library = empty($h5p_content) ? NULL : $h5p_content->getLibrary(TRUE);
    $old_params = empty($h5p_content) ? NULL : $h5p_content->getParameters();
    if (empty($h5p_content)) {
      $value['id'] = NULL; // Invalid, content has been deleted
    }

    $params = json_decode($value['parameters']);

    // Prepare content values
    $core = H5PDrupal::getInstance('core');
    $content = [
      'library' => H5PEditorUtilities::getLibraryProperty($value['library']),
      'params' => json_encode($params->params),
      'metadata' => $params->metadata,
      'disable' => $core->getStorableDisplayOptions($value, !empty($h5p_content) ? $h5p_content->get('disabled_features')->value : 0),
    ];
    if ($value['id'] && !$do_new_revision && !$value['new_translation']) {
      $content['id'] = $value['id'];
    }

    // Save the new content
    $return_value['h5p_content_id'] = $core->saveContent($content);

    // If we had existing content and did a new revision we need to make a copy
    // of the content folder from the old revision
    if ($value['id'] && ($do_new_revision || $value['new_translation'])) {
      $core->fs->cloneContent($value['id'], $return_value['h5p_content_id']);
    }

    // Keep new files, delete files from old parameters
    $editor = H5PEditorUtilities::getInstance();
    $editor->processParameters(
      $return_value['h5p_content_id'],
      $content['library'],
      $params->params,
      $old_library,
      $old_params
    );

    return $return_value;
  }

}

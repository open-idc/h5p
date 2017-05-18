<?php

namespace Drupal\H5PEditor\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;

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
    $library_id = isset($items[$delta]->library_id) ? $items[$delta]->library_id : '0';

    $hub_is_enabled = TRUE;
    if ($hub_is_enabled) {
      $form['h5p_type']['#default_value'] = 'create';
      $form['h5p_type']['#type'] = 'hidden';
    }


    $integration = H5PDrupal::getGenericH5PIntegrationSettings();
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

    return array('value' => $element);
  }

  /**
   * Validate the color text field.
   */
  public function validate($element, FormStateInterface $form_state) {

    // TODO: Skip if action === delete ?

  }
}

<?php

namespace Drupal\H5PEditor\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

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

    $element += array(
      '#type' => 'textfield',
      '#default_value' => $library_id,
      '#size' => 7,
      '#maxlength' => 7,
      '#element_validate' => array(
        array($this, 'validate'),
      ),
    );

    // TODO: Add disable settings

    return array('value' => $element);
  }

  /**
   * Validate the color text field.
   */
  public function validate($element, FormStateInterface $form_state) {

    // TODO: Skip if action === delete ?

  }
}

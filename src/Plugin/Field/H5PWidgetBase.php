<?php

namespace Drupal\h5p\Plugin\Field;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;

abstract class H5PWidgetBase extends WidgetBase {

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
      return ['h5p_content' => $element];
    }

    $element += [
      '#type' => 'fieldset',
    ];

    $h5p_content_id = $items[$delta]->h5p_content_id;
    $element['id'] = [
      '#type' => 'value',
      '#value' => $h5p_content_id,
    ];

    // Make it possible to clear a field
    $element['clear_content'] = [
      '#type' => 'checkbox',
      '#title' => t('Clear content'),
      '#description' => t('Warning! Your content will be completely deleted'),
      '#default_value' => 0,
      '#weight' => 40,
    ];

    // Determine if this is a new translation or not
    $element['new_translation'] = [
      '#type' => 'value',
      '#value' => !empty($form_state->get('content_translation')),
    ];

    // Load content
    $disable = NULL;
    $h5p_content = $h5p_content_id ? H5PContent::load($h5p_content_id) : NULL;
    if (!empty($h5p_content)) {
      $disable = $h5p_content->get('disabled_features')->value;
    }

    // Add display options
    $field_name = $items->getName();
    $core = H5PDrupal::getInstance('core');
    $display_options = $core->getDisplayOptionsForEdit($disable === \H5PCore::DISABLE_NONE ? NULL : $disable);
    $labels = [
      \H5PCore::DISPLAY_OPTION_FRAME => t('Display buttons (download, embed and copyright)'),
      \H5PCore::DISPLAY_OPTION_DOWNLOAD => t('Allow users to download the content'),
      \H5PCore::DISPLAY_OPTION_EMBED => t('Embed button'),
      \H5PCore::DISPLAY_OPTION_COPYRIGHT => t('Copyright button'),
      \H5PCore::DISPLAY_OPTION_ABOUT => t('About H5P button')
    ];
    foreach ($display_options as $name => $value) {
      $element[$name] = [
        '#type' => 'checkbox',
        '#title' => $labels[$name],
        '#default_value' => $value,
        '#weight' => 40,
        '#attributes' => [
          'id' => str_replace('_', '-', $field_name) . "-{$delta}-h5p-content-{$name}",
        ],
      ];

      if ($name !== \H5PCore::DISPLAY_OPTION_FRAME) {
        $element[$name]['#states'] = [
          'visible' => [
            '#' . str_replace('_', '-', $field_name) . "-{$delta}-h5p-content-" . \H5PCore::DISPLAY_OPTION_FRAME => ['checked' => TRUE],
          ]
        ];
      }

      if ($name === \H5PCore::DISPLAY_OPTION_DOWNLOAD) {
        $element[$name]['#description'] = t('If checked a reuse button will always be displayed for this content and allow users to download the content as an .h5p file');
      }
    }

    return ['h5p_content' => $element];
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
      if (!isset($value['h5p_content'])) {
        continue; // Prevent crashing when there's no data
      }

      // Massage out each H5P Upload from the submitted form
      $return_values[$delta] = $this->massageFormValue($value['h5p_content'], $delta, $do_new_revision);
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
  protected function massageFormValue(array $value, $delta, $do_new_revision) {
    return [];
  }

  /**
   * Determine if the current entity is creating a new revision.
   * This is useful to avoid changing the H5P content belonging to
   * an older revision of the entity.
   *
   * @param FormStateInterface $form_state
   * @return boolean
   */
  private static function doNewRevision(FormStateInterface $form_state) {
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

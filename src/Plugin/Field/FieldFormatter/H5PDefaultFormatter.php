<?php

namespace Drupal\H5P\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'h5p_default' formatter.
 *
 * @FieldFormatter(
 *   id = "h5p_default",
 *   label = @Translation("Interactive Content"),
 *   field_types = {
 *     "h5p"
 *   }
 * )
 */
class H5PDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    //$settings = $this->getSettings();

    $summary[] = t('Displays interactive content.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = array(
        '#type' => 'markup',
        '#markup' => $item->value,
      );
    }

    return $element;
  }

}

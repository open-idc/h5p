<?php

namespace Drupal\h5p;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides views data for the H5P entity type.
 */
class H5PContentViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['h5p_points']['table']['group'] = $this->t('H5P Points');

    $data['h5p_points']['table']['join'] = [
      'h5p_content' => [
        'field' => 'content_id',
        'left_field' => 'id',
      ],
    ];

    $data['h5p_points']['uid'] = [
      'title' => $this->t('H5P user points'),
      'field' => [
        'id' => 'standard',
       ],
       'filter' => [
         'id' => 'standard',
       ],
       'argument' => [
         'id' => 'standard',
       ],
       'sort' => [
         'id' => 'standard',
       ],
       'relationship' => [
         'base' => 'users_field_data',
         'base field' => 'uid',
         'id' => 'standard',
         'label' => t('H5P user points'),
       ],
    ];

    $data['h5p_points']['started'] = [
      'title' => $this->t('Started'),
      'field' => [
        'id' => 'date',
       ],
       'filter' => [
         'id' => 'date',
       ],
       'argument' => [
         'id' => 'date',
       ],
       'sort' => [
         'id' => 'date',
       ],
    ];

    $data['h5p_points']['finished'] = [
      'title' => $this->t('Finished'),
      'field' => [
        'id' => 'date',
       ],
       'filter' => [
         'id' => 'date',
       ],
       'argument' => [
         'id' => 'date',
       ],
       'sort' => [
         'id' => 'date',
       ],
    ];

    $data['h5p_points']['points'] = [
      'title' => $this->t('Points'),
      'field' => [
        'id' => 'numeric',
       ],
       'filter' => [
         'id' => 'numeric',
       ],
       'argument' => [
         'id' => 'numeric',
       ],
       'sort' => [
         'id' => 'standard',
       ],
    ];

    $data['h5p_points']['max_points'] = [
      'title' => $this->t('Max points'),
      'field' => [
        'id' => 'numeric',
       ],
       'filter' => [
         'id' => 'numeric',
       ],
       'argument' => [
         'id' => 'numeric',
       ],
       'sort' => [
         'id' => 'standard',
       ],
    ];

    return $data;
  }
}

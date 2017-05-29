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

    //$data['h5p_content']['table']['base']['help'] = $this->t('TBD');
    //$data['h5p_content']['table']['base']['defaults']['field'] = 'id';
    $data['h5p_content']['table']['wizard_id'] = 'h5p_content';

    $data['h5p_content']['id']['relationship'] = [
      'title' => $this->t('H5P Points'),
      'help' => $this->t('Relate H5P entities to points.'),
      'id' => 'standard',
      'base' => 'h5p_points',
      'base field' => 'content_id',
      'field' => 'id',
      'label' => $this->t('H5P Points'),
    ];

    $data['h5p_points']['table']['group'] = $this->t('H5P Points');

    $data['h5p_points']['table']['join'] = [
      'h5p_content' => [
        'field' => 'content_id',
        'left_field' => 'id',
      ],
    ];

    $data['h5p_points']['uid']['relationship'] = [
      'base' => 'users',
      'base field' => 'uid',
      'label' => t('user'),
    ];

    $data['users']['h5p_uid_points']['relationship'] = [
      'title' => t('H5P Points for user'),
      'label'  => t('H5P Points for user'),
      'help' => t('Will return an entry for each H5P that is stored for the user in the database.'),
      'relationship field' => 'uid',
      'outer field' => 'users.uid',
      'argument table' => 'users',
      'argument field' =>  'uid',
      'base'   => 'h5p_points',
      'field'  => 'uid',
      'base field' => 'uid',
    ];

    $data['h5p_points']['uid'] = [
      'title' => $this->t('User id'),
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

    $data['h5p_points']['started'] = [
      'title' => $this->t('Started'),
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

    $data['h5p_points']['finished'] = [
      'title' => $this->t('Finished'),
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

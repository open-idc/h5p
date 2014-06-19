<?php

/**
 * @file
 * This file provides metadata to the Views 2 API for views integration.
 *
 */
function h5p_views_handlers() {
  return array(
    'info' => array(
      'path' => H5P_VIEWS_DIR . '/handlers',
    ),
    /*'handlers' => array(
      // Fields
      'h5p_views_handler_field_started' => array(
        'parent' => 'views_handler_field',
      ),
      'h5p_views_handler_field_finished' => array(
        'parent' => 'views_handler_field',
      ),
      'h5p_views_handler_field_points' => array(
        'parent' => 'views_handler_field_numeric',
      ),
      'h5p_views_handler_field_max_pooints' => array(
        'parent' => 'views_handler_field_numeric',
      ),
      // Filters
      'h5p_views_handler_filter_started' => array(
        'parent' => 'views_handler_filter',
      ),
      'h5p_views_handler_filter_finished' => array(
        'parent' => 'views_handler_filter',
      ),
      'h5p_views_handler_filter_points' => array(
        'parent' => 'views_handler_filter',
      ),
      'h5p_views_handler_filter_max_points' => array(
        'parent' => 'views_handler_filter',
      ),
      // Arguments
      'h5p_views_handler_argument_h5p_nid' => array(
        'parent' => 'views_handler_argument_numeric',
      ),
      'h5p_views_handler_argument_user_uid_nullable' => array(
        'parent' => 'views_handler_argument_user_uid',
      ),
    ),*/
  );
}

/**
 * Implementation of hook_views_data().
 */
function h5p_views_data() {
  // ----------------------------------------------------------------
  // h5p_points table -- basic table information.

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['h5p_points']['table']['group'] = t('Content');
  
  // explain how we join
  $data['h5p_points']['table']['join'] = array(
    // Directly links to node table.
    'node' => array(
      'left_field' => 'content_id',
      'field' => 'vid',
    ),
  );

//  $data['h5p_points']['table']['default_relationship'] = array(
//    'node' => array(
//      'table' => 'node',
//      'left_field' => 'nid',
//      'field' => 'content_id',
//    ),
//  );
  
  // ----------------------------------------------------------------
  // points table -- fields

  // contet id
  $data['h5p_points']['content_id'] = array(
    'title' => t('Content ID'),
    'help' => t('The ID of content. nid og vid depending on the sites settings.'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_node_nid',
      'name_field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'nid',
    ),
    
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    'sort' => array('handler' => 'views_handler_sort'),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'node',
      'left_field' => 'content_id',
      'field' => 'nid',
      'label' => t('Node ID')
    ),
  );
  
  // uid
  $data['node']['h5p_nid_points'] = array(
    'relationship' => array(
      'title' => t('H5P Points'),
      'label'  => t('H5P Points'),
      'help' => t('Will return an entry for each result that is stored for the node in the database.'),
      'handler' => 'views_handler_relationship',
      'relationship field' => 'nid',
      'outer field' => 'node.nid',
      'argument table' => 'node',
      'argument field' =>  'nid',
      'base'   => 'h5p_points',
      'field'  => 'content_id',
      'base field' => 'content_id',
    ),
  );
  
  // uid field
  $data['h5p_points']['uid'] = array(
    'title' => t('User'),
    'help' => t('Relate a score to the user who performed the activity.'),
    'relationship' => array(
      'handler' => 'views_handler_relationship',
      'base' => 'users',
      'base field' => 'uid',
      'label' => t('user'),
    ),
  );
  
  // uid
  $data['users']['h5p_uid_points'] = array(
    'relationship' => array(
      'title' => t('H5P Points'),
      'label'  => t('H5P Points'),
      'help' => t('Will return an entry for each H5P that is stored for the user in the database.'),
      'handler' => 'views_handler_relationship',
      'relationship field' => 'uid',
      'outer field' => 'users.uid',
      'argument table' => 'users',
      'argument field' =>  'uid',
      'base'   => 'h5p_points',
      'field'  => 'uid',
      'base field' => 'uid',
    ),
  );
  
  $data['h5p_points']['started'] = array(
    'title' => t('Start time'),
    'help' => t('Timestamp for when the user began this activity.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array('handler' => 'views_handler_sort_date'),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
      'allow empty' => TRUE,
    ),
  );
  $data['h5p_points']['finished'] = array(
    'title' => t('Finish time'),
    'help' => t('Time the activity was finished.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array('handler' => 'views_handler_sort_date'),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
      'allow empty' => TRUE,
    ),
  );
  $data['h5p_points']['points'] = array(
    'title' => t('Points awarded'),
    'help' => t('Points awarded for answer(s) given'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
      'numeric' => TRUE,
    ),
    'sort' => array('handler' => 'views_handler_sort'),
  );
  
  $data['h5p_points']['max_points'] = array(
    'title' => t('Max points awarded'),
    'help' => t('The maximum amoount of points the user could achieve'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
      'numeric' => TRUE,
    ),
    'sort' => array('handler' => 'views_handler_sort'),
  );  

  return $data;
}

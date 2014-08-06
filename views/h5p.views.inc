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
  );
}

/**
 * Implementation of hook_views_data().
 */
function h5p_views_data() {

  // ----------------------------------------------------------------
  // h5p_nodes table -- basic table information.

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['h5p_nodes']['table']['group'] = t('H5P');

  // explain how we join
  $data['h5p_nodes']['table']['join'] = array(
    // Directly links to node table.
    'node' => array(
      'left_field' => 'nid',
      'field' => 'nid',
    ),
  );

  // content id
  $data['h5p_nodes']['content_id'] = array(
    'title' => t('Content ID'),
    'help' => t('The ID of content. nid or vid depending on the sites settings.'),
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
  );

  // nid
  $data['h5p_nodes']['nid'] = array(
    'title' => t('Nid'),
    'help' => t('The node ID to which the H5P belongs to.'),
    'relationship' => array(
      'title' => t('Content'),
      'help' => t('The content to which the H5P belongs to.'),
      'base' => 'node',
      'base field' => 'nid',
      'handler' => 'views_handler_relationship',
      'label' => t('Content'),
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
  );


  // ----------------------------------------------------------------
  // h5p_points table -- basic table information.

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['h5p_points']['table']['group'] = t('H5P');
  
  // explain how we join
  $data['h5p_points']['table']['join'] = array(
    // Directly links to h5p_nodes table.
    'h5p_nodes' => array(
      'left_field' => 'content_id',
      'field' => 'content_id',
    ),
  );
  
  // ----------------------------------------------------------------
  // points table -- fields

  // content id
  $data['h5p_points']['content_id'] = array(
    'title' => t('Content ID'),
    'help' => t('The ID of content. nid or vid depending on the sites settings.'),
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
      'base' => 'h5p_nodes',
      'base field' => 'content_id',
      'field' => 'content_id',
      'label' => t('H5P Points - H5P Nodes')
    ),
  );

   // User - H5P points relationship
  $data['h5p_nodes']['h5p_points'] = array(
    'relationship' => array(
      'title' => t('H5P Points for node'),
      'label'  => t('H5P'),
      'help' => t('Will return points.'),
      'handler' => 'views_handler_relationship',
      'relationship field' => 'content_id',
      'argument table' => 'h5p_points',
      'argument field' =>  'content_id',
      'base'   => 'h5p_nodes',
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
  
  // User - H5P points relationship
  $data['users']['h5p_uid_points'] = array(
    'relationship' => array(
      'title' => t('H5P Points for user'),
      'label'  => t('H5P Points for user'),
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

/**
 * Implements hook_views_plugins()
 */
function h5p_views_plugins() {
  $plugins = array(
    'access' => array(
      'h5p-node' => array(
        'title' => t('Access node points'),
        'help' => t('Is the logged in user allowed to see result data for this node?'),
        'handler' => 'h5p_access_node_points_plugin',
      ),
      'h5p-user' => array(
        'title' => t('Access user points'),
        'help' => t('Is the logged in user allowed to see result data for this user?'),
        'handler' => 'h5p_access_user_points_plugin',
      ),
    ),
  );
  return $plugins;
}

/**
 * Access plugin that provides access control based on what user tries to access what content.
 */
class h5p_access_node_points_plugin extends views_plugin_access {
  function summary_title() {
    return t('Access H5P node points');
  }
  /**
   * Determine if the current user has access or not.
   */
  function access($account) {
    return h5p_access_node_points($account);
  }
  function get_access_callback() {
    return array('h5p_access_node_points', array());
  }
}

/**
 * Access plugin that provides access control based on what users points are beeing accessed
 */
class h5p_access_user_points_plugin extends views_plugin_access {
  function summary_title() {
    return t('Access H5P user points');
  }
  /**
   * Determine if the current user has access or not.
   */
  function access($account) {
    return h5p_access_user_points($account);
  }
  function get_access_callback() {
    return array('h5p_access_user_points', array());
  }
}


// Temporary view export:
$view = new view();
$view->name = 'node_points';
$view->description = '';
$view->tag = 'default';
$view->base_table = 'node';
$view->human_name = 'Node points';
$view->core = 7;
$view->api_version = '3.0';
$view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

/* Display: Master */
$handler = $view->new_display('default', 'Master', 'default');
$handler->display->display_options['title'] = 'Node points';
$handler->display->display_options['use_more_always'] = FALSE;
$handler->display->display_options['access']['type'] = 'h5p-node';
$handler->display->display_options['cache']['type'] = 'none';
$handler->display->display_options['query']['type'] = 'views_query';
$handler->display->display_options['exposed_form']['type'] = 'basic';
$handler->display->display_options['pager']['type'] = 'full';
$handler->display->display_options['pager']['options']['items_per_page'] = '20';
$handler->display->display_options['style_plugin'] = 'table';
/* Field: Content: Start time */
$handler->display->display_options['fields']['started']['id'] = 'started';
$handler->display->display_options['fields']['started']['table'] = 'h5p_points';
$handler->display->display_options['fields']['started']['field'] = 'started';
$handler->display->display_options['fields']['started']['date_format'] = 'long';
/* Field: Content: Finish time */
$handler->display->display_options['fields']['finished']['id'] = 'finished';
$handler->display->display_options['fields']['finished']['table'] = 'h5p_points';
$handler->display->display_options['fields']['finished']['field'] = 'finished';
$handler->display->display_options['fields']['finished']['date_format'] = 'long';
/* Field: Content: Points awarded */
$handler->display->display_options['fields']['points']['id'] = 'points';
$handler->display->display_options['fields']['points']['table'] = 'h5p_points';
$handler->display->display_options['fields']['points']['field'] = 'points';
/* Field: Content: Max points awarded */
$handler->display->display_options['fields']['max_points']['id'] = 'max_points';
$handler->display->display_options['fields']['max_points']['table'] = 'h5p_points';
$handler->display->display_options['fields']['max_points']['field'] = 'max_points';
$handler->display->display_options['fields']['max_points']['label'] = 'Max points';
/* Sort criterion: Content: Post date */
$handler->display->display_options['sorts']['created']['id'] = 'created';
$handler->display->display_options['sorts']['created']['table'] = 'node';
$handler->display->display_options['sorts']['created']['field'] = 'created';
$handler->display->display_options['sorts']['created']['order'] = 'DESC';
/* Filter criterion: Content: Published */
$handler->display->display_options['filters']['status']['id'] = 'status';
$handler->display->display_options['filters']['status']['table'] = 'node';
$handler->display->display_options['filters']['status']['field'] = 'status';
$handler->display->display_options['filters']['status']['value'] = 1;
$handler->display->display_options['filters']['status']['group'] = 1;
$handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
/* Filter criterion: Content: Type */
$handler->display->display_options['filters']['type']['id'] = 'type';
$handler->display->display_options['filters']['type']['table'] = 'node';
$handler->display->display_options['filters']['type']['field'] = 'type';
$handler->display->display_options['filters']['type']['value'] = array(
  'h5p_content' => 'h5p_content',
);

/* Display: Page */
$handler = $view->new_display('page', 'Page', 'page');
$handler->display->display_options['path'] = 'node/%/h5p-points';
$handler->display->display_options['menu']['type'] = 'tab';
$handler->display->display_options['menu']['title'] = 'Points';
$handler->display->display_options['menu']['weight'] = '0';
$handler->display->display_options['menu']['context'] = 0;
$handler->display->display_options['menu']['context_only_inline'] = 0;


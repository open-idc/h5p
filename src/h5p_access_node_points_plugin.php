<?php
namespace Drupal\h5p;

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

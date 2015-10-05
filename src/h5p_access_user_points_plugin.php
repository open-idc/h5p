<?php
namespace Drupal\h5p;

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

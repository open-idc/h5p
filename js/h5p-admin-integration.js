/* global Drupal */
(function () {
  'use strict';

  Drupal.behaviors.drupal_h5p_admin_integration = {
    attach: function (context, settings) {
      window.H5PAdminIntegration = settings.h5p.drupal_h5p_admin_integration.H5PAdminIntegration;
    }
  };
})();

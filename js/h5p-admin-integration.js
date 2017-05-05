// global var from h5p js library
var H5PAdminIntegration;

(function ($) {
    'use strict';

    Drupal.behaviors.drupal_h5p_admin_integration = {
        attach: function(context, settings) {

            H5PAdminIntegration = settings.h5p.drupal_h5p_admin_integration.H5PAdminIntegration;

        }
    };
}) (jQuery)
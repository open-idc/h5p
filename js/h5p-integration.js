// global var from h5p js library
var H5PIntegration;

(function ($) {
    'use strict';

    Drupal.behaviors.drupal_h5p_integration = {
        attach: function(context, settings) {

            H5PIntegration = settings.h5p.drupal_h5p_integration.H5PIntegration;

        }
    };
}) (jQuery)
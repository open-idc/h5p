var H5PEditor = H5PEditor || {};
var ns = H5PEditor;

(function ($) {
  'use strict';

  Drupal.behaviors.drupal_h5p_editor = {
    attach: function (context, settings) {

      ns.init = function () {
        var h5peditor;
        var $upload = $('input[name="files[h5p]"]').parents('.form-item');
        var $editor = $('.h5p-editor');
        var $create = $('#edit-h5p-editor').hide();
        var $type = $('input[name="h5p_type"]');
        var $params = $('input[name="json_content"]');
        var $library = $('input[name="h5p_library"]');
        var library = $library.val();

        ns.$ = H5P.jQuery;
        ns.basePath = drupalSettings.path.baseUrl + settings.h5p.drupal_h5p_editor.h5peditor.modulePath + '/h5p-editor';
        ns.contentId = settings.h5p.drupal_h5p_editor.h5peditor.nodeVersionId;
        ns.fileIcon = settings.h5p.drupal_h5p_editor.h5peditor.fileIcon;
        ns.ajaxPath = settings.h5p.drupal_h5p_editor.h5peditor.ajaxPath;
        ns.filesPath = settings.h5p.drupal_h5p_editor.h5peditor.filesPath;
        ns.relativeUrl = settings.h5p.drupal_h5p_editor.h5peditor.relativeUrl;
        ns.contentRelUrl = settings.h5p.drupal_h5p_editor.h5peditor.contentRelUrl;
        ns.editorRelUrl = settings.h5p.drupal_h5p_editor.h5peditor.editorRelUrl;
        ns.apiVersion = settings.h5p.drupal_h5p_editor.h5peditor.apiVersion;

        // Semantics describing what copyright information can be stored for media.
        ns.copyrightSemantics = settings.h5p.drupal_h5p_editor.h5peditor.copyrightSemantics;

        // Required styles and scripts for the editor
        ns.assets = settings.h5p.drupal_h5p_editor.h5peditor.assets;

        // Required for assets
        ns.baseUrl = drupalSettings.path.baseUrl;

        $type.change(function () {
          if ($type.filter(':checked').val() === 'upload') {
            $create.hide();
            $upload.show();
          }
          else {
            $upload.hide();
            if (h5peditor === undefined) {
              h5peditor = new ns.Editor(library, $params.val(), $editor[0]);
            }
            $create.show();
          }
        }).change();

        $('#node-h5p-content-form, #node-h5p-content-edit-form').submit(function () {
          if (h5peditor !== undefined) {
            var params = h5peditor.getParams();

            if (params === false) {
              // return false;
              /*
               * TODO: Give good feedback when validation fails. Currently it seems save and delete buttons
               * aren't working, but the user doesn't get any indication of why they aren't working.
               */
            }

            if (params !== undefined) {
              $library.val(h5peditor.getLibrary());
              $params.val(JSON.stringify(params));
            }
          }
        });
      };

      ns.getAjaxUrl = function (action, parameters) {
        var url = settings.h5p.drupal_h5p_editor.h5peditor.ajaxPath + action;

        if (parameters !== undefined) {
          for (var key in parameters) {
            url += '/' + parameters[key];
          }
        }

        return url;
      };

      $(document).ready(ns.init);

    }
  }
})(H5P.jQuery);

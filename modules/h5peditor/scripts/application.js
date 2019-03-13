/* global drupalSettings jQuery Drupal */

(function ($, Drupal, H5P, H5PEditor) {
  var initialized;
  var submitHandlers = [];

  /**
   * One time setup of the H5PEditor
   *
   * @param Object settings from drupal
   */
  H5PEditor.init = function () {
    if (initialized) {
      return; // Prevent multi init
    }
    initialized = true;

    // Set up editor settings
    H5PEditor.$ = H5P.jQuery;
    H5PEditor.baseUrl = drupalSettings.path.baseUrl;
    H5PEditor.basePath = drupalSettings.h5peditor.libraryPath;
    H5PEditor.contentLanguage = drupalSettings.h5peditor.language;
    mapProperties(H5PEditor, drupalSettings.h5peditor,
      ['contentId', 'fileIcon', 'relativeUrl', 'contentRelUrl', 'editorRelUrl', 'apiVersion', 'copyrightSemantics', 'metadataSemantics', 'assets']);
  };

  // Init editors
  Drupal.behaviors.H5PEditor = {
    attach: function (context, settings) {
      $('.h5p-editor', context).once('H5PEditor').each(function () {
        H5PEditor.init();

        // Grab data values specifc for editor instance
        var $this = $(this);
        var parametersFieldId = $this.data('parametersid');
        var libraryFieldId = $this.data('libraryid');
        var contentId = $this.data('contentId');
        var $form = $this.parents('form');

        // Locate parameters field
        var $params = $('#' + parametersFieldId, context);

        // Locate library field
        var $library = $('#' + libraryFieldId, context);

        // Create form submit handler
        var submit = {
          element: null,
          handler: function (event) {
            if (h5peditor !== undefined) {

              var params = h5peditor.getParams();

              if (params !== undefined && params.params !== undefined) {
                // Validate mandatory main title. Prevent submitting if that's not set.
                // Deliberatly doing it after getParams(), so that any other validation
                // problems are also revealed
                if (!h5peditor.isMainTitleSet()) {
                  return event.preventDefault();
                }

                // Set main library
                $library.val(h5peditor.getLibrary());

                // Set params
                $params.val(JSON.stringify(params));

                // TODO - Calculate & set max score
                // $maxscore.val(h5peditor.getMaxScore(params.params));
              }
            }
          }
        };
        submitHandlers.push(submit);

        // Create new editor
        var h5peditor = new H5PEditor.Editor($library.val(), $params.val(), this, function () {
          submit.element = this.frameElement; // Update frame element
          var iframeH5PEditor = this.H5PEditor;
          iframeH5PEditor.contentId = (contentId ? contentId : undefined);
          iframeH5PEditor.ajaxPath = settings.h5peditor.ajaxPath.replace(':contentId', (contentId ? contentId : 0));
          iframeH5PEditor.filesPath = settings.h5peditor.filesPath + '/editor';

          /**
           * Help build URLs for AJAX requests
           */
          iframeH5PEditor.getAjaxUrl = function (action, parameters) {
            var url = iframeH5PEditor.ajaxPath + action;

            if (parameters !== undefined) {
              for (var key in parameters) {
                url += '/' + parameters[key];
              }
            }

            return url;
          };
        });

        $form.submit(submit.handler);
      });
    },
    detach: function (context, settings, trigger) {
      if (trigger === 'serialize') {
        $('.h5p-editor-iframe', context).once('H5PEditor').each(function () {
          for (var i = 0; i < submitHandlers.length; i++) {
            if (submitHandlers[i].element === this) {
              // Trigger submit handler
              submitHandlers[i].handler();
            }
          }
        });
      }
    }
  };

  /**
   * Map properties from one object to the other
   * @private
   * @param {Object} to
   * @param {Object} from
   * @param {Array} props
   */
  var mapProperties = function (to, from, props) {
    for (var i = 0; i < props.length; i++) {
      var prop = props[i];
      to[prop] = from[prop];
    }
  };

})(jQuery, Drupal, H5P, H5PEditor);

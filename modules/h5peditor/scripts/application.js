(function ($, Drupal, H5P, H5PEditor) {
  var initialized;

  /**
   * One time setup of the H5PEditor
   *
   * @param Object settings from drupal
   */
  H5PEditor.init = function (settings) {
    if (initialized) {
      return; // Prevent multi init
    }
    initialized = true;

    // Set up editor settings
    H5PEditor.$ = H5P.jQuery;
    H5PEditor.baseUrl = drupalSettings.path.baseUrl;
    H5PEditor.basePath = drupalSettings.h5peditor.libraryPath;
    mapProperties(H5PEditor, drupalSettings.h5peditor,
      ['contentId', 'fileIcon', 'relativeUrl', 'contentRelUrl', 'editorRelUrl', 'apiVersion', 'copyrightSemantics', 'assets']);
  };

  // Init editors
  Drupal.behaviors.H5PEditor = {
    attach: function (context, settings) {
      $('.h5p-editor', context).once('H5PEditor').each(function () {
        H5PEditor.init(settings);

        // Grab data values specifc for editor instance
        var $this = $(this);
        var field = $this.data('field');
        var delta = $this.data('delta');
        var contentId = $this.data('contentId');
        var $form = $this.parents('form');

        // Locate parameters field
        var $params = $('input[name="' + field + '[' + delta + '][h5p_content][parameters]"]', context);

        // Locate library field
        var $library = $('input[name="' + field + '[' + delta + '][h5p_content][library]"]', context);

        // Create new editor
        var h5peditor = new ns.Editor($library.val(), $params.val(), this, function () {
          var iframeH5PEditor = this.H5PEditor;
          iframeH5PEditor.contentId = (contentId ? contentId : 0);
          iframeH5PEditor.ajaxPath = settings.h5peditor.ajaxPath.replace(':contentId', this.H5PEditor.contentId);
          iframeH5PEditor.filesPath = settings.h5peditor.filesPath + (contentId ? '/content/' + contentId : '/editor');

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

        // Handle form submit
        $form.submit(function () {
          var params = h5peditor.getParams();

          if (params !== undefined) {
            $library.val(h5peditor.getLibrary());
            $params.val(JSON.stringify(params));
          }
        });

      });
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

H5peditor.init = function () {
  var h5peditor;
  var $upload = $('#edit-h5p-wrapper');
  var $editor = $('.h5p-editor');
  var $create = $editor.parent().hide();
  var $type = $('input[name="h5p_type"]');
  var library = $('#edit-h5p-library').val();
  
  H5peditor.$ = $;
  H5peditor.basePath = Drupal.settings.basePath + 'h5peditor/';
  H5peditor.contentId = Drupal.settings.nodeVersionId;
  H5peditor.filesPath = Drupal.settings.filesPath;
  H5peditor.fileIcon = Drupal.settings.fileIcon;
  
  $type.change(function () {
    if ($type.filter(':checked').val() == 'upload') {
      $create.hide();
      $upload.show();
    }
    else {
      $upload.hide();
      if (h5peditor == undefined) {
        h5peditor = new H5peditor(library, JSON.parse($('#edit-h5p-params').val()));
        h5peditor.replace($editor);
      }
      $create.show();
    }
  });
  
  if (library) {
    $type.filter('input[value="create"]').attr('checked', true).change();
  }

  $('#node-form').submit(function () {
    if (h5peditor != undefined) {
      var params = h5peditor.getParams();
      if (params) {
        $('#edit-h5p-library').val(h5peditor.getLibrary());
        $('#edit-h5p-params').val(JSON.stringify(params));
      }
    }
  });
}

$(document).ready(function () {
  H5peditor.init();
});
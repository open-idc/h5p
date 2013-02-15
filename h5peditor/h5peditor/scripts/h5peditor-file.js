/**
 * Adds a file upload field to the form.
 */
function H5peditorFile(parent, field, params, setValue) {
  this.field = field;
  this.params = params;
  this.setValue = setValue;
  
  this.changes = [];
}

/**
 * Append field to the given wrapper.
 */
H5peditorFile.prototype.appendTo = function ($wrapper) {
  H5peditorFile.addIframe();
  
  var label = this.field.label == undefined ? '' : '<label>' + this.field.label + '</label>'; 
  this.$file = H5peditor.$(H5peditorForm.createItem(this.field.type, label + '<div class="file"></div>')).appendTo($wrapper).children('.file');
  this.addFile(true);
  this.$errors = this.$file.next();
}

/**
 * Creates thumbnail HTML and actions.
 */
H5peditorFile.prototype.addFile = function (init) {
  var that = this;
  
  if (this.params == undefined) {
    this.$file.html('<a href="#" class="add" title="' + H5peditor.t('addFile') + '"></a>').children('.add').click(function () {
      that.uploadFile();
      return false;
    });
    return;
  }

  var thumbnail;
  if (this.field.type == 'image') {
    thumbnail = {};
    thumbnail.path = (init == undefined ? H5peditor.filesPath + '/h5peditor/' : H5peditor.filesPath + '/h5p/content/' + H5peditor.contentId + '/') + this.params.path,
    thumbnail.height = 100;
    thumbnail.width = thumbnail.height * (this.params.width / this.params.height);
  }
  else {
    thumbnail = H5peditor.fileIcon;
  }
  
  this.$file.html('<a href="#" title="' + H5peditor.t('changeFile') + '" class="thumbnail"><img src="' + thumbnail.path + '" width="' + thumbnail.width + '" height="' + thumbnail.height + '" alt="' + (this.field.label == undefined ? '' : this.field.label) + '"/><a href="#" class="remove" title="' + H5peditor.t('removeFile') + '"></div>').children(':eq(0)').click(function () {
    that.uploadFile();
    return false;
  }).next().click(function (e) {
    if (!confirm(H5peditor.t('confirmRemoval', {':type': 'file'}))) {
      return false;
    }
    delete that.params;
    that.setValue(that.field);
    that.addFile();
    return false;
  });
}

/**
 * Start a new upload.
 */
H5peditorFile.prototype.uploadFile = function () {
  var that = this;
  
  if (H5peditorFile.$file == 0) {
    return; // Wait for our turn :)
  }
  
  H5peditorFile.callback = function (json) {
    try {
      var result = JSON.parse(json);
      if (result['error'] != undefined) {
        throw(result['error']);
      }
      
      that.params = result;
      that.setValue(that.field, that.params);
      that.addFile();
      
      for (var i = 0; i < that.changes.length; i++) {
        that.changes[i](result);
      }
    }
    catch (error) {
      that.$errors.append(H5peditorForm.createError(error));
    }
  }
  
  H5peditorFile.$field.val(JSON.stringify(this.field));
  H5peditorFile.$file.click();
}

/**
 * Add the iframe we use for uploads.
 */
H5peditorFile.addIframe = function () {
  if (H5peditorFile.$field != undefined) {
    return;
  }
  
  // All editor uploads share this iframe to conserve valuable resources.
  H5peditor.$('<iframe id="h5peditor-uploader"></iframe>').load(function () {
    var $body = $(this).contents().find('body');
    var json = $body.text();
    if (H5peditorFile.callback != undefined) {
      H5peditorFile.callback(json);
    }
    
    $body.html('');
    var $form = H5peditor.$('<form method="post" enctype="multipart/form-data" action="' + H5peditor.basePath + 'files"><input name="file" type="file"/><input name="field" type="hidden"/></form>').appendTo($body);
    
    H5peditorFile.$field = $form.children('input[type="hidden"]');
    H5peditorFile.$file = $form.children('input[type="file"]');
    
    H5peditorFile.$file.change(function () {
      H5peditorFile.$field = 0;
      H5peditorFile.$file = 0;
      $form.submit();
    });
    
  }).appendTo('body');
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.image = H5peditorFile;
H5peditor.fieldTypes.file = H5peditorFile;
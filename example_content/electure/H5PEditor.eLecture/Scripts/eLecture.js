var H5PEditor = H5PEditor || {};

/**
 * Create a field for the form.
 * 
 * @param {mixed} parent
 * @param {Object} field
 * @param {mixed} params
 * @param {function} setValue
 * @returns {H5PEditor.Text}
 */
H5PEditor.eLecture = function (parent, field, params, setValue) {
  var that = this;

  this.parent = parent;
  this.field = field;
  
  this.findVideoField(function (field) {
    if (field.params !== undefined) {
      that.setVideo(field.params);
    }

//      field.changes.push(function (file) {
//        // TODO: This should be removed once this item is removed.
//        that.setMax(file.params.width, file.params.height);
//      });
  });

  this.params = params;
  this.setValue = setValue;
  
  console.log(params);
};

/**
 * Find the video field to use for the electure, then run the callback.
 * 
 * @param {type} callback
 * @returns {undefined}
 */
H5PEditor.eLecture.prototype.findVideoField = function (callback) {
  var that = this;
  
  // Find field when tree is ready.
  this.parent.ready(function () {
    var path = that.field.video;
      
    that.field.video = H5PEditor.findField(that.field.video, that.parent);
    if (!that.field.video) {
      throw H5PEditor.t('unknownFieldPath', {':path': path});
    }
    if (that.field.video.field.type !== 'video') {
      throw H5PEditor.t('notVideoField', {':path': path});
    }
      
    callback(that.field.video);
  });
};

H5PEditor.eLecture.prototype.setVideo = function (files) {
  this.field.video = [];
  for (var i in files) {
    this.field.video.push({
      path: files[i].path,
      mime: files[i].mime
    });
  }
  console.log('Video set', this.field.video);
  this.validate();
};

/**
 * Append field to wrapper.
 * 
 * @param {type} $wrapper
 * @returns {undefined}
 */
H5PEditor.eLecture.prototype.appendTo = function ($wrapper) {
  this.$item = H5PEditor.$(this.createHtml()).appendTo($wrapper);
  this.$editor = this.$item.children('.editor');
  this.$errors = this.$item.children('.errors');
};

/**
 * Create HTML for the field.
 */
H5PEditor.eLecture.prototype.createHtml = function () {
  return H5PEditor.createItem(this.field.widget, '<div class="editor">Video:)</div>');
};

/**
 * Validate the current field.
 */
H5PEditor.eLecture.prototype.validate = function () {
  if (!this.field.video.length) {
    this.$errors.append(H5PEditor.createError(H5PEditor.t('selectVideo')));
    this.$editor.hide();
    return false;
  }
  this.$editor.show();
  
  return true;
};

/**
 * Remove this item.
 */
H5PEditor.eLecture.prototype.remove = function () {
  this.$item.remove();
};

// Tell the editor what widget we are.
H5PEditor.widgets.electure = H5PEditor.eLecture;

// Add translations
H5PEditor.l10n.selectVideo = 'You must select a video before adding interactions.';
H5PEditor.l10n.notVideoField = '":path" is not a video.';
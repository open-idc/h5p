/**
 * Adds a dimensions field to the form.
 * 
 * TODO: Make it possible to lock width/height ratio.
 */
function H5peditorDimensions(parent, field, params, setValue) {
  var that = this;

  this.parent = parent;
  this.field = field;

  // Find image filed to get max size from.
  this.findImageField('max', function (field) {
    if (field instanceof H5peditorFile) {
      that.setMax(field.params.width, field.params.height);
      field.changes.push(function (file) {
        that.setMax(file.params.width, file.params.height);
      });
    }
  });
  
  if (typeof params == 'string') { 
    // Params points to another field
    params = {
      width: '',
      height: ''
    };
  }
  
  if (params.width == '' && params.height == '') {
    // Find image filed to get default size from.
    this.findImageField('default', function (field) {
      var width = field.params.width;
      var height = field.params.height;
      
      that.setSize(width, height);

      field.changes.push(function (file) {
        that.setSize(file.width, file.height);
        // TODO: Figure out if we should keep same ratio when image changes.
        //that.setSize(Math.round(file.width / (width / that.params.width)), Math.round(file.height / (height / that.params.height)));
      });
    });
  }

  this.params = params;
  this.setValue = setValue;
}

/**
 * Set max dimensions.
 */
H5peditorDimensions.prototype.setMax = function (width, height) {
  this.field.max = {
    width: width,
    height: height
  }
}

/**
 * Set current dimensions.
 */
H5peditorDimensions.prototype.setSize = function (width, height) {  
  this.params.width = width;
  this.params.height = height;
  this.setValue(this.field, this.params);
      
  this.$inputs.filter(':eq(0)').val(width).next().val(height);
}

/**
 * Find the image field for the given property and then run the callback.
 */
H5peditorDimensions.prototype.findImageField = function (property, callback) {
  var that = this;
  var str = 'string';
  
  if (typeof this.field[property] != str) {
    return;
  }
  
  // Find field when tree is ready.
  this.parent.ready(function () {
    if (typeof that.field[property] != str) {
      if (that.field[property] != undefined) {
        callback(that.field[property]);
      }
      return; // We've already found this field before.
    }
    var path = that.field[property];
      
    that.field[property] = H5peditorForm.findField(that.field[property], that.parent);
    if (!that.field[property]) {
      throw H5peditor.t('unknownFieldPath', {':path': path});
    }
    if (that.field[property].field.type != 'image') {
      throw H5peditor.t('notImageField', {':path': path});
    }
      
    callback(that.field[property]);
  });
}

/**
 * Append the field to the given wrapper.
 */
H5peditorDimensions.prototype.appendTo = function ($wrapper) {
  var that = this;
  
  this.$item = H5peditor.$(H5peditorForm.createItem(this.field.type, this.createHtml())).appendTo($wrapper);
  this.$inputs = this.$item.find('input');
  this.$errors = this.$item.children('.errors');

  this.$inputs.change(function () {
    // Validate
    var value = that.validate();
    
    if (value) {
      // Set param
      that.params = value;
      that.setValue(that.field, value);
    }
  }).click(function () {
    return false;
  });
}

/**
 * Create HTML for the field.
 */
H5peditorDimensions.prototype.createHtml = function () {
  var html = '<label>';
  
  if (this.field.label != undefined) {
    html += '<span class="label">' + this.field.label + '</span>';
  }
  
  html += '<input type="text" placeholder="Width"';
  if (this.params != undefined) {
    html += ' value="' + this.params.width + '"';
  }
  html += ' maxlength="15"/> x <input type="text" placeholder="Height"';
  if (this.params != undefined) {
    html += ' value="' + this.params.height + '"';
  }
  
  return html + ' maxlength="15"/></label>';
}

/**
 * Validate the current text field.
 */
H5peditorDimensions.prototype.validate = function () {
  var that = this;
  var size = {};

  this.$inputs.each(function (i) {
    var $input = H5peditor.$(this);
    var value = $input.val().replace(/^\s+|\s+$/g, '');
    var property = i ? 'height' : 'width';
    
    if ((that.field.optional == undefined || !that.field.optional) && !value.length) {
      that.$errors.append(H5peditorForm.createError(H5peditor.t('requiredProperty', {':property': property})));
      return false;
    }
    else if (!value.match(new RegExp('^[0-9]+$'))) {
      that.$errors.append(H5peditorForm.createError(H5peditor.t('onlyNumbers', {':property': property})));
      return false;
    }
    else if (that.field.max != undefined && value > that.field.max[property]) {
      that.$errors.append(H5peditorForm.createError(H5peditor.t('exceedsMax', {':property': property, ':max': that.field.max[property]})));
      return false;
    }
    
    size[property] = value;
  });

  if (this.$errors.children().length) {
    this.$inputs.keyup(function (e) {
      if (e.keyCode == 9) { // TAB
        return;
      }
      that.$errors.html('');
      that.$inputs.unbind('keyup');
    });
    
    return false;
  }
  
  return size;
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.dimensions = H5peditorDimensions;
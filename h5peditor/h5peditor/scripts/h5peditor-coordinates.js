/**
 * Creates a coordinates picker for the form.
 */
function H5peditorCoordinates(parent, field, params, setValue) {
  var that = this;

  this.parent = parent;
  this.field = field;

  // Find image field to get max size from.
  this.findImageField('max', function (field) {
    if (field instanceof H5peditorFile) {
      that.setMax(field.params.width, field.params.height);

      field.changes.push(function (file) {
        that.setMax(file.params.width, file.params.height);
      });
    }
  });

  this.params = params;
  this.setValue = setValue;
}

/**
 * Set max coordinates.
 */
H5peditorCoordinates.prototype.setMax = function (x, y) {
  this.field.max = {
    x: x,
    y: y
  }
}

/**
 * Find the image field for the given property and then run the callback.
 */
H5peditorCoordinates.prototype.findImageField = function (property, callback) {
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
 * Append the field to the wrapper.
 */
H5peditorCoordinates.prototype.appendTo = function ($wrapper) {
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
 * Create HTML for the coordinates picker.
 */
H5peditorCoordinates.prototype.createHtml = function () {
  var html = '<label>';
  
  if (this.field.label != undefined) {
    html += '<span class="label">' + this.field.label + '</span>';
  }
  
  html += '<input type="text" placeholder="X"';
  if (this.params != undefined) {
    html += ' value="' + this.params.x + '"';
  }
  html += ' maxlength="15"/> , <input type="text" placeholder="Y"';
  if (this.params != undefined) {
    html += ' value="' + this.params.y + '"';
  }
  
  return html + ' maxlength="15"/></label>';
}

/**
 * Validate the current values.
 */
H5peditorCoordinates.prototype.validate = function () {
  var that = this;
  var size = {};

  this.$inputs.each(function (i) {
    var $input = H5peditor.$(this);
    var value = $input.val().replace(/^\s+|\s+$/g, '');
    var property = i ? 'y' : 'x';
    
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
H5peditor.fieldTypes.coordinates = H5peditorCoordinates;
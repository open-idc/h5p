/**
 * Creates a boolean field for the editor.
 */
function H5peditorBoolean(parent, field, params, setValue) {
  this.field = field;
  this.value = params;
  this.setValue = setValue;
}

/**
 * Create HTML for the boolean field.
 */
H5peditorBoolean.prototype.createHtml = function () {
  var input = '<input type="checkbox"';
  if (this.field.description != undefined) {
    input += ' title="' + this.field.description + '"';
  }
  if (this.value != undefined && this.value) {
    input += ' checked="checked"';
  }
  input += '/>';
  
  return '<label>' + input + (this.field.label != undefined ? this.field.label : '') + '</label>';
}

/**
 * "Validate" the current boolean field.
 */
H5peditorBoolean.prototype.validate = function () {
  return this.$input.is(':checked') ? 1 : 0;
}

/**
 * Append the boolean field to the given wrapper.
 */
H5peditorBoolean.prototype.appendTo = function ($wrapper) {
  var that = this;

  this.$item = H5peditor.$(H5peditorForm.createItem(this.field.type, this.createHtml())).appendTo($wrapper);
  this.$input = this.$item.children('label').children('input');
  this.$errors = this.$item.children('.h5peditor-errors');

  this.$input.change(function () {
    // Validate
    var value = that.validate();
    that.setValue(that.field, value);
  });
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes['boolean'] = H5peditorBoolean;
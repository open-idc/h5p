/**
 * Create a text field for the form.
 */
function H5peditorText(parent, field, params, setValue) {
  this.field = field;
  this.value = params;
  this.setValue = setValue;
}

/**
 * Append field to wrapper.
 */
H5peditorText.prototype.appendTo = function ($wrapper) {
  var that = this;
  
  this.$item = H5peditor.$(H5peditorForm.createItem(this.field.type, this.createHtml())).appendTo($wrapper);
  this.$input = this.$item.children('label').children('input');
  this.$errors = this.$item.children('.errors');
  
  this.$input.change(function () {
    // Validate
    var value = that.validate();
    
    if (value) {
      // Set param
      that.setValue(that.field, value);
    }
  });
}

/**
 * Create HTML for the text field.
 */
H5peditorText.prototype.createHtml = function () {
  var html = '<label>';
  
  if (this.field.length == undefined) {
    this.field.length = 255;
  }
  
  if (this.field.label != undefined) {
    html += '<span class="label">' + this.field.label + '</span>';
  }
  
  html += '<input type="text"';
  if (this.field.description != undefined) {
    html += ' title="' + this.field.description + '" placeholder="' + this.field.description + '"';
  }
  
  if (this.value != undefined) {
    html += ' value="' + this.value + '"';
  }
  
  return html + ' maxlength="' + this.field.length  + '"/></label>';
}

/**
 * Validate the current text field.
 */
H5peditorText.prototype.validate = function () {
  var that = this;
  
  var value = this.$input.val().replace(/^\s+|\s+$/g, '');
    
  if (this.field.required != undefined && this.field.required && !value.length) {
    this.$errors.append(H5peditorForm.createError(H5peditor.t('requiredProperty', {':property': 'text field'})));
  }
  else if (value.length > this.field.length) {
    this.$errors.append(H5peditorForm.createError(H5peditor.t('tooLong', {':max': this.field.length})));
  }
  else if (this.field.regexp != undefined && !value.match(new RegExp(this.field.regexp.pattern, this.field.regexp.modifiers))) {
    this.$errors.append(H5peditorForm.createError(H5peditor.t('invalidFormat')));
  }

  if (this.$errors.children().length) {
    this.$input.keyup(function () {
      that.$errors.html('');
      that.$input.unbind('keyup');
    });
    
    return false;
  }
  
  return value;
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.text = H5peditorText;
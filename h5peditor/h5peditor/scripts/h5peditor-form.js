/**
 * Construct a form from library semantics.
 */
function H5peditorForm() {
  this.params = {};
  this.readies = [];
  this.$form = H5peditor.$('<div class="h5peditor-form"></div>');
}

/**
 * Replace the given element with our form.
 */
H5peditorForm.prototype.replace = function ($element) {
  $element.replaceWith(this.$form);
}

/**
 * Remove the current form.
 */
H5peditorForm.prototype.remove = function () {
  this.$form.remove();
}

/**
 * Wrapper for processing the semantics.
 */
H5peditorForm.prototype.processSemantics = function (semantics, defaultParams) {
  try {
    this.params = defaultParams;
    H5peditorForm.processSemanticsChunk(semantics, this.params, this.$form, this);
    
    // Run ready callbacks.
    for (var i = 0; i < this.readies.length; i++) {
      this.readies[i]();
    }
    delete this.readies;
    // TODO: Validate fields on submit
  }
  catch (error) {
    var $error = H5peditor.$('<div class="h5peditor-error">' + H5peditor.t('semanticsError', {':error': error}) + '</div>');
    this.$form.replaceWith($error);
    this.$form = $error;
  }
}

/**
 * Collect functions to execute once the tree is complete.
 */
H5peditorForm.prototype.ready = function (ready) {
  this.readies.push(ready);
}

/**
 * Recursive processing of the semantics chunks.
 */
H5peditorForm.processSemanticsChunk = function (semanticsChunk, params, $wrapper, parent) {
  parent.children = [];
  
  for (var i = 0; i < semanticsChunk.length; i++) {
    var field = semanticsChunk[i];
      
    // TODO: Translate all error messages.
    
    // Check generic field properties.
    if (field.name == undefined) {
      throw H5peditor.t('missingProperty', {':index': i, ':property': 'name'});
    }
    if (field.type == undefined) {
      throw H5peditor.t('missingProperty', {':index': i, ':property': 'type'});
    }
    
    // Set default value.
    if (params[field.name] == undefined && field['default'] != undefined) {
      params[field.name] = field['default'];
    }
    
    // TODO: Set a default label if label == undefined or skip if label == 0. This should be done inside the class it self and not here.
    
    // TODO: Remove later, this is here for debugging purposes.
    if (H5peditor.fieldTypes[field.type] == undefined) {
      $wrapper.append('<div>[field:' + field.type + ':' + field.name + ']</div>');
      continue;
    }

    var fieldInstance = new H5peditor.fieldTypes[field.type](parent, field, params[field.name], function (field, value) {
      if (value == undefined) {
        delete params[field.name];
      }
      else {
        params[field.name] = value;
      }
    });
    fieldInstance.appendTo($wrapper);
    parent.children.push(fieldInstance);
  }
}

/**
 * Find field from path.
 */
H5peditorForm.findField = function (path, parent) {
  if (typeof path == 'string') {
    path = path.split('/');
  }
  
  if (path[0] == '..') {
    path.splice(0, 1)
    return H5peditorForm.findField(path, parent.parent);
  }
  
  for (var i = 0; i < parent.children.length; i++) {
    if (parent.children[i].field.name == path[0]) {
      path.splice(0, 1)
      if (path.length > 1) {
        return H5peditorForm.findField(path, parent.children[i]);
      }
      else {
        return parent.children[i];
      }
    }
  }
  
  return false;
}

/**
 * Create HTML wrapper for error messages.
 */
H5peditorForm.createError = function (message) {
  return '<p>' + message + '</p>';
}

/**
 * Create HTML wrapper for field items.
 */
H5peditorForm.createItem = function (type, content) {
  return '<div class="field ' + type + '">' + content + '<div class="errors"></div></div>';
}

/**
 * Create HTML for select options.
 */
H5peditorForm.createOption = function (value, text, selected) {
  return '<option value="' + value + '"' + (selected != undefined && selected ? ' selected="selected"' : '') + '>' + text + '</option>';
}
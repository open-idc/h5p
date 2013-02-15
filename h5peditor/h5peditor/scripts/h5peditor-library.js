/**
 * Create a field where one can select and include another library to the form.
 */
function H5peditorLibrary(parent, field, params, setValue) {
  var that = this;

  if (params == undefined) {
    this.params = {params: {}};
    setValue(field, this.params);
  } else {
    this.params = params;
  }
  
  this.field = field;
  this.parent = parent;
  
  this.queueReady = true;
  parent.ready(function () {
    that.queueReady = false;
  });
}

/**
 * Append the library selector to the form.
 */
H5peditorLibrary.prototype.appendTo = function ($wrapper) {
  var that = this;

  var options = H5peditorForm.createOption('-', '-');
  H5peditor.$('select[name="h5peditor-library"]').children('option').each(function () {
    var $option = $(this);
    for (var i = 0; i < that.field.options.length; i++) {
      var library = $option.val()
      if (library == that.field.options[i]) {
        options += H5peditorForm.createOption(library, $option.text(), library == that.params.library)
      }
    }
  });
  
  this.$select = H5peditor.$(H5peditorForm.createItem(this.field.type, (this.field.label == undefined ? '' : '<label>' + this.field.label + '</label>') + '<select>' + options + '</select><div class="libwrap"></div>')).appendTo($wrapper).children('select').change(function () {
    that.loadLibrary(H5peditor.$(this).val());
  });
  
  this.$libraryWrapper = this.$select.next();
  
  // Load default selected library.
  if (this.$select.val() != '-') {
    this.$select.change();
  }
}

/**
 * Load the selected library.
 */
H5peditorLibrary.prototype.loadLibrary = function (library) {
  var that = this;
  
  if (library == '-') {
    this.$libraryWrapper.html('');
    return;
  }
  
  this.$libraryWrapper.html(H5peditor.t('loading', {':type': 'semantics'}));
  
  H5peditorLibrarySelector.loadLibrary(library, function (semantics) {
    H5peditorForm.processSemanticsChunk(semantics, that.params.params, that.$libraryWrapper.html(''), that);
  });
}

/**
 * Validate this field and its children.
 */
H5peditorLibrary.prototype.validate = function () {
  if (this.$select.val() == '-') {
    return false;
  }
  
  for (var i = 0; i < this.children.length; i++) {
    if (!this.children[i].validate()) {
      return false;
    }
  }
  
  return true;
}

/**
 * Collect functions to execute once the tree is complete.
 */
H5peditorLibrary.prototype.ready = function (ready) {
  if (this.queueReady) {
    this.parent.ready(ready);
  }
  else {
    ready();
  }
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.library = H5peditorLibrary;
/**
 * Create a group of fields.
 */
function H5peditorGroup(parent, field, params, setValue) {
  this.parent = parent;
  this.field = field;
  this.params = params;
  this.setValue = setValue;
}

/**
 * Append group to its wrapper.
 */
H5peditorGroup.prototype.appendTo = function ($wrapper) {
  var that = this;

  this.$group = H5peditor.$('<fieldset class="field group"><div class="title"><a href="#" class="expand" title="' + H5peditor.t('expandCollapse') + '"></a><span class="text"></span></div><div class="content"></div></fieldset>').appendTo($wrapper).find('.expand').click(function () {
    that.expand();
    return false;
  }).end();
  
  if (this.field.fields.length == 1) {
    this.children = [];
    this.children[0] = new H5peditor.fieldTypes[this.field.fields[0].type](this, this.field.fields[0], this.params, function (field, value) {
      that.setValue(that.field, value);
    });
    this.children[0].appendTo(this.$group.children('.content'));
  } 
  else {
    if (this.params == undefined) {
      this.params = {};
      this.setValue(this.field, this.params);
    }
    H5peditorForm.processSemanticsChunk(this.field.fields, this.params, this.$group.children('.content'), this);
  }
  
  // Set summary
  this.findSummary();
}

/**
 * Expand the given group.
 */
H5peditorGroup.prototype.expand = function () {
  var expandedClass = 'expanded';
  
  if (this.$group.hasClass(expandedClass)) {
    this.$group.removeClass(expandedClass);
  }
  else {
    this.$group.addClass(expandedClass);
  }
}

/**
 * Find summary to display in group header.
 */
H5peditorGroup.prototype.findSummary = function () {
  var that = this;
  var summary;
  
  for (var j = 0; j < this.children.length; j++) {
    var child = this.children[j];
    var params = this.field.fields.length == 1 ? this.params : this.params[child.field.name];
      
    if (child instanceof H5peditorText) {
      if (params != undefined && params != '') {
        summary = this.field.label + ': ' + params;
      }
      child.$input.change(function () {
        if (params != undefined && params != '') {
          that.setSummary(that.field.label + ': ' + params);
        }
      });
      break;
    }
    else if (child instanceof H5peditorLibrary) {
      if (params.library != undefined) {
        summary = this.field.label + ': ' + child.$select.children(':selected').text();
      }
      child.$select.change(function () {
        that.setSummary(that.field.label + ': ' + child.$select.children(':selected').text());
      });
      break;
    }
  }
  this.setSummary(summary);
}

/**
 * Set the given group summary.
 */
H5peditorGroup.prototype.setSummary = function (summary) {
  this.$group.children('.title').children('.text').text(summary == undefined ? this.field.label : summary);
}

/**
 * Validate all children.
 */
H5peditorGroup.prototype.validate = function () {
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
H5peditorGroup.prototype.ready = function (ready) {
  this.parent.ready(ready);
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.group = H5peditorGroup;
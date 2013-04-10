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
H5PEditor.Wizard = function (parent, field, params, setValue) {
  this.parent = parent;
  this.field = field;
  this.params = params;
  this.setValue = setValue;
  this.library = parent.library + '/' + field.name;
  this.children = [];
};

/**
 * Append field to wrapper.
 * 
 * @param {type} $wrapper
 * @returns {undefined}
 */
H5PEditor.Wizard.prototype.appendTo = function ($wrapper) {
  var that = this;
  
  this.$item = H5PEditor.$(this.createHtml()).appendTo($wrapper);
  this.$errors = this.$item.children('.errors');
  var $panesWrapper = H5PEditor.$('<div class="panes"></div>').insertBefore(this.$errors);

  if (this.params === undefined) {
    this.params = {};
    this.setValue(this.field, this.params);
  }
  H5PEditor.processSemanticsChunk(this.field.fields, this.params, $panesWrapper, this);
  
  // TODO: Add preview pane?
  
  this.$panes = $panesWrapper.children();
  
  this.$tabs = this.$item.find('ol > li > a').click(function () {
    that.showTab(H5PEditor.$(this));
    return false;
  });
  this.$tabs.eq(0).click();
};

/**
 * Create HTML for the field.
 */
H5PEditor.Wizard.prototype.createHtml = function () {
  var tabs = '<ol>';
  
  for (var i = 0; i < this.field.fields.length; i++) {
    var field = this.field.fields[i];
    tabs += H5PEditor.Wizard.createTab(i, field.label);
  }
  
  tabs += H5PEditor.Wizard.createTab(i, 'Preview');
  tabs += '</ol>';
  
  return H5PEditor.createItem(this.field.widget, tabs);
};

/**
 * 
 * @param {type} $tab
 * @returns {undefined}
 */
H5PEditor.Wizard.prototype.showTab = function ($tab) {
  var id = $tab.attr('data-id');
  this.$panes.hide().eq(id).show();
  this.$tabs.removeClass('active');
  $tab.addClass('active');
};

/**
 * Validate the current field.
 */
H5PEditor.Wizard.prototype.validate = function () {
  for (var i = 0; i < this.children.length; i++) {
    if (!this.children[i].validate()) {
      return false;
    }
  }
  
  return true;
};

/**
 * Collect functions to execute once the tree is complete.
 * 
 * @param {function} ready
 * @returns {undefined}
 */
H5PEditor.Wizard.prototype.ready = function (ready) {
  this.parent.ready(ready);
};

/**
 * Remove this item.
 */
H5PEditor.Wizard.prototype.remove = function () {
  H5PEditor.removeChildren(this.children); 
  this.$item.remove();
};

/**
 * 
 * @param {type} id
 * @param {type} label
 * @returns {String}
 */
H5PEditor.Wizard.createTab = function (id, label) {
  return '<li><a href="#" data-id="' + id + '">' + label + '</a></li>';
};

// Tell the editor what widget we are.
H5PEditor.widgets.wizard = H5PEditor.Wizard;
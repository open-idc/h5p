/**
 * Create a list of fields for the form.
 */
function H5peditorList(parent, field, params, setValue) {
  var that = this;
  
  if (field.max == undefined) {
    field.max = 15;
  }
  if (field.entity == undefined) {
    field.entity = 'item';
  }
  
  if (params == undefined) {
    this.params = [];
    setValue(field, this.params);
  } else {
    this.params = params;
  }
  hm = this.params;
  
  this.field = field;
  this.parent = parent;
  this.$items = [];
  this.children = [];
  
  this.queueReady = true;
  parent.ready(function () {
    that.queueReady = false;
  });
}

/**
 * Append list to wrapper.
 */
H5peditorList.prototype.appendTo = function ($wrapper) {
  var that = this;
  
  this.$list = H5peditor.$(H5peditorForm.createItem(this.field.type, (this.field.label == undefined ? '' : '<label>' + this.field.label + '</label>') + '<ul></ul><input type="button" value="' + H5peditor.t('addEntity', {':entity': this.field.entity}) + '"/>')).appendTo($wrapper).children('ul');
  this.$add = this.$list.next().click(function () {
    if (that.params.length == that.field.max) {
      return;  
    }
    var item = that.addItem();
    if (item instanceof H5peditorGroup) {
      item.expand();
    }
  });
  
  for (var i = 0; i < this.params.length; i++) {
    this.addItem(i);
  }
}

/**
 * Add an item to the list.
 */
H5peditorList.prototype.addItem = function (i) {
  var that = this;
  
  if (i == undefined) {
    i = this.params.length;
  }
  
  var $item = H5peditor.$('<li><a href="#" class="remove"></a><div class="content"></div></li>').appendTo(this.$list).children('.remove').click(function () {
    that.removeItem(that.getIndex($item));
    return false;
  }).end();
  this.children[i] = new H5peditor.fieldTypes[this.field.field.type](this, this.field.field, this.params[i], function (field, value) {
    that.params[that.getIndex($item)] = value;
  });
  this.children[i].appendTo($item.children('.content'));
  
  this.$items[i] = $item;

  return this.children[i];
}

/**
 * Remove and item from the list.
 */
H5peditorList.prototype.removeItem = function (i) {
  if (!confirm(H5peditor.t('confirmRemoval', {':type': this.field.entity}))) {
    return;
  }
  
  this.$items[i].remove();
  
  this.$items.splice(i, 1);
  this.params.splice(i, 1);
  this.children.splice(i, 1);
}

/**
 * Get the index for the given item.
 */
H5peditorList.prototype.getIndex = function ($item) {
  for (var i = 0; i < this.$items.length; i++) {
    if (this.$items[i] == $item) {
      break;
    }
  }
  
  return i;
}

/**
 * Validate all fields in the list.
 */
H5peditorList.prototype.validate = function () {
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
H5peditorList.prototype.ready = function (ready) {
  if (this.queueReady) {
    this.parent.ready(ready);
  }
  else {
    ready();
  }
}

// Tell the editor what semantic field we are.
H5peditor.fieldTypes.list = H5peditorList;
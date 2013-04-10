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
H5PEditor.Istribute = function (parent, field, params, setValue) {
  this.field = field;
  this.value = params;
  this.setValue = setValue;
  console.log(params);
};

/**
 * Append field to wrapper.
 * 
 * @param {type} $wrapper
 * @returns {undefined}
 */
H5PEditor.Istribute.prototype.appendTo = function ($wrapper) {
  var that = this;
  
  this.$item = H5PEditor.$(this.createHtml()).appendTo($wrapper);
  this.$errors = this.$item.children('.errors');
  
  var containerId = 'kaptein-krok';
  var $iframe;
  
  H5PEditor.Istribute.loadEasyXDM(function () {
    var path = Drupal.settings.basePath + 'ndla-h5p/istribute-url/eLecture';
    if (that.value !== undefined) {
      path += '/' + that.value;
    }
     
    H5PEditor.$.get(path, function (url) {
      new easyXDM.Socket({
        remote: url,
        container: containerId,
				onMessage: function (message, origin) {
          var cmd = message.split(/ /);
          
          if ($iframe === undefined) {
            $iframe = that.$item.find('iframe');
          }
          
          switch(cmd[0]) {
            case 'isuploading':
              // Prevent submit?
              break;
            
            case 'isnotuploading':
              break;
            
            case 'dialogify':
              $iframe.css('height', cmd[2] + 'px');
              break;
            
            case 'undialogify':
              $iframe.css('height', '90px');
              break;
            
            case 'setvalue':
              that.value = message.substring(9);
              that.setValue(that.field, that.value);
              break;
          }
        },
        props: {
          allowtransparency: true,
          style: {
            border: 0,
            padding: 0,
            margin: 0,
            width: '100%',
            height: '90px'
          }
        }
      });
    });
  });
};

/**
 * Create HTML for the field.
 */
H5PEditor.Istribute.prototype.createHtml = function () {
  var label = '';
  if (this.field.label !== 0) {
    label = '<label>' + (this.field.label === undefined ? this.field.name : this.field.label) + '</label>';
  }
  
  return H5PEditor.createItem(this.field.type, label + '<div class="video" id="kaptein-krok"></div>');
};

/**
 * Validate the current field.
 */
H5PEditor.Istribute.prototype.validate = function () {
  var that = this;
  
  var value = H5PEditor.trim(this.$input.val());
    
  if ((that.field.optional === undefined || !that.field.optional) && !value.length) {
    this.$errors.append(H5PEditor.createError(H5PEditor.t('requiredProperty', {':property': 'text field'})));
  }
  else if (value.length > this.field.maxLength) {
    this.$errors.append(H5PEditor.createError(H5PEditor.t('tooLong', {':max': this.field.maxLength})));
  }
  else if (this.field.regexp !== undefined && !value.match(new RegExp(this.field.regexp.pattern, this.field.regexp.modifiers))) {
    this.$errors.append(H5PEditor.createError(H5PEditor.t('invalidFormat')));
  }

  return H5PEditor.checkErrors(this.$errors, this.$input, value);
};

/**
 * Remove this item.
 */
H5PEditor.Istribute.prototype.remove = function () {
  this.$item.remove();
};

/**
 * Load easyXDM then run callback
 * 
 * @param {function} callback
 * @returns {unresolved}
 */
H5PEditor.Istribute.loadEasyXDM = function (callback) {
  if (window['easyXDM'] !== undefined) {
    callback();
    return;
  }
  
  if (H5PEditor.Istribute.easyXDMQueue !== undefined) {
    H5PEditor.Istribute.easyXDMQueue.push(callback);
    return;
  }
  
  H5PEditor.Istribute.easyXDMQueue = [callback];
  $.getScript('http://api.istribute.com/v1/video/uploader/lib/easyXDM/easyXDM.min.js', function () {
    for (var i = 0; i < H5PEditor.Istribute.easyXDMQueue.length; i++) {
      H5PEditor.Istribute.easyXDMQueue[i]();
    }
    delete H5PEditor.Istribute.easyXDMQueue;
  });
};

// Tell the editor what semantic field we are.
H5PEditor.fieldTypes.istribute = H5PEditor.Istribute;
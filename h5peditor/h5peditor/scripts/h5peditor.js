/**
 * Construct the editor.
 */
function H5peditor(library, defaultParams) {
  var that = this;
  
  // Create a wrapper
  this.$wrapper = H5peditor.$('<div class="h5peditor">' + H5peditor.t('loading', {':type': 'libraries'}) + '</div>');

  // Load libraries.
  H5peditor.$.get(H5peditor.basePath + 'libraries', function (data) {
    that.selector = new H5peditorLibrarySelector(JSON.parse(data), library, defaultParams);
    that.selector.appendTo(that.$wrapper.html(''));
    if (library) {
      that.selector.$selector.change();
    }
  });
}

/**
 * Replace $element with our editor element.
 */
H5peditor.prototype.replace = function ($element) {
  $element.replaceWith(this.$wrapper);
}

/**
 * Return library used.
 */
H5peditor.prototype.getLibrary = function () {
  if (this.selector != undefined) {
    return this.selector.$selector.val();
  }
}

/**
 * Return params needed to start library.
 */
H5peditor.prototype.getParams = function () {
  if (this.selector != undefined) {
    return this.selector.getParams();
  }
}

/**
 * The current localization mapping. To be translated by your framework.
 */
H5peditor.l10n = {
  missingTranslation: '[Missing translation :key]',
  loading: 'Loading :type...',
  selectLibrary: 'Select the library you wish to use for your content.',
  unknownFieldPath: 'Unable to find ":path".',
  notImageField: '":path" is not an image.',
  requiredProperty: 'The :property is required and must have a value.',
  onlyNumbers: 'The :property value can only contain numbers.',
  exceedsMax: 'The :property value exceeds the max of :max.',
  addFile: 'Add file',
  removeFile: 'Remove file',
  confirmRemoval: 'Are you sure you wish to remove this :type?',
  changeFile: 'Change file',
  semanticsError: 'Semantics error: :error',
  missingProperty: 'Field :index is missing its :property property.',
  expandCollapse: 'Expand/Collapse',
  addEntity: 'Add :entity',
  tooLong: 'Field value is too long, should contain :max letters or less.',
  invalidFormat: 'Field value contains an invalid format or characters that are forbidden.'
};

/**
 * Translate text strings.
 */
H5peditor.t = function (key, vars) {
  if (H5peditor.l10n[key] == undefined) {
    return key == 'missingTranslation' ? '[Missing translation "' + key + '"]' : H5peditor.t('missingTranslation', {':key': key});
  }
  
  var translation = H5peditor.l10n[key];
  
  // Replace placeholder with variables.
  for (var placeholder in vars) {
    translation = translation.replace(placeholder, vars[placeholder]);
  }
  
  return translation;
}

// Keep track of our semantic field types.
H5peditor.fieldTypes = {};

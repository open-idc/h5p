/**
 * Construct a library selector.
 */
function H5peditorLibrarySelector(libraries, defaultLibrary, defaultParams) {
  var that = this;
  var options = '<option value="-">-</option>';
  
  this.defaultParams = defaultParams;
  this.defaultLibrary = defaultLibrary;
  
  for (var i = 0; i < libraries.length; i++) {
    options += '<option value="' + libraries[i].name + '"'
    if (libraries[i].name == defaultLibrary) {
      options += ' selected="selected"';
    }
    options += '>' + libraries[i].label + '</option>';
  }
  
  this.$selector = H5peditor.$('<select name="h5peditor-library" title="' + H5peditor.t('selectLibrary') + '">' + options + '</select>').change(function () {
    var library = that.$selector.val();
    that.loadSemantics(library);
  });
}

/**
 * Append the selector html to the given container.
 */
H5peditorLibrarySelector.prototype.appendTo = function ($element) {
  this.$selector.appendTo($element);
}

/**
 * Display loading message and load library semantics.
 */
H5peditorLibrarySelector.prototype.loadSemantics = function (library) {
  var that = this;

  if (this.form != undefined) {
    // Remove old form.
    this.form.remove();
  }
  
  if (library == '-') {
    // No library chosen.
    return;
  }

  // Display loading message
  var $loading = $('<div>' + H5peditor.t('loading', {':type': 'semantics'}) + '</div>').insertAfter(this.$selector);
  
  this.$selector.attr('disabled', true);
  
  H5peditorLibrarySelector.loadLibrary(library, function (semantics) {
    that.form = new H5peditorForm();
    that.form.replace($loading);
    
    that.form.processSemantics(semantics, (library == that.defaultLibrary ? that.defaultParams : {}));

    that.$selector.attr('disabled', false);
    $loading.remove();
  });
}

/**
 * Return params needed to start library.
 */
H5peditorLibrarySelector.prototype.getParams = function () {
  // TODO: Only return if all fields has validated.
  if (this.form != undefined) {
    return this.form.params;
  }
}

/**
 * Extremely advanced function that loads the given library, inserts any css and js and
 * then runs the callback with the samantics as an argument.
 */
H5peditorLibrarySelector.loadLibrary = function (libraryName, callback) {
  switch (H5peditorLibrarySelector.loadedSemantics[libraryName]) {
    default:
      // Get semantics from cache.
      callback(H5peditorLibrarySelector.loadedSemantics[libraryName]);
      break;
      
    case 0:
      // Add to queue.
      H5peditorLibrarySelector.semanticsLoaded[libraryName].push(callback);
      break;
    
    case undefined:
      // Load semantics.
      H5peditorLibrarySelector.loadedSemantics[libraryName] = 0; // Indicates that others should queue.
      H5peditorLibrarySelector.semanticsLoaded[libraryName] = []; // Other callbacks to run once loaded.

      H5peditor.$.get(H5peditor.basePath + 'libraries/' + libraryName, function (data) {
        var library = JSON.parse(data);
        library.semantics = JSON.parse(library.semantics);
        H5peditorLibrarySelector.loadedSemantics[libraryName] = library.semantics;
        
        // Add CSS.
        if (library.css != undefined) {
          H5peditor.$('head').append('<style type="text/css">' + library.css + '</style>');
        }
        
        // Add JS.
        if (library.javascript != undefined) {
          eval(library.javascript);
        }
        
        callback(library.semantics);

        // Run queue.
        for (var i = 0; i < H5peditorLibrarySelector.semanticsLoaded[libraryName].length; i++) {
          H5peditorLibrarySelector.semanticsLoaded[libraryName][i](library.semantics);
        }
      });
  }
}

/**
 * Keeps track of which semantics are loaded.
 */
H5peditorLibrarySelector.loadedSemantics = {}

/**
 * Keeps track of callbacks to run once a semantic gets loaded.
 */
H5peditorLibrarySelector.semanticsLoaded = {}
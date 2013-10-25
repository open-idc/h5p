// TODO: Why can't h5pintegration.js just hook into the H5P namespace instead of creating its own?
var H5PIntegration = H5PIntegration || {};
var H5P = H5P || {};

// If run in an iframe, use parent version of globals.
if (window.parent !== window) {
  Drupal = window.parent.Drupal;
  $ = window.parent.$;
}

$(document).ready(function () {
  H5P.loadedJs = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedJs !== undefined ? Drupal.settings.h5p.loadedJs : [];
  H5P.loadedCss = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedCss !== undefined ? Drupal.settings.h5p.loadedCss : [];
});

H5PIntegration.getJsonContent = function (contentId) {
  return Drupal.settings.h5p.content['cid-' + contentId].jsonContent;
};

// Window parent is always available.
var locationOrigin = window.parent.location.protocol + "//" + window.parent.location.host;
H5PIntegration.getContentPath = function (contentId) {
  if (Drupal.settings.h5p !== undefined && contentId !== undefined) {
    return locationOrigin + Drupal.settings.h5p.jsonContentPath + contentId + '/';
  }
  else if (Drupal.settings.h5peditor !== undefined)  {
    return Drupal.settings.h5peditor.filesPath + '/h5peditor/';
  }
};

/**
 * Get the path to the library
 *
 * TODO: Make this use machineName instead of machineName-majorVersion-minorVersion
 *
 * @param {string} library
 *  The library identifier as string, for instance 'downloadify-1.0'
 * @returns {string} The full path to the library
 */
H5PIntegration.getLibraryPath = function (library) {
  // TODO: This is silly and needs to be changed, why does the h5peditor
  // have its own namespace for these things?
  var libraryPath = Drupal.settings.h5p !== undefined ? Drupal.settings.h5p.libraryPath : Drupal.settings.h5peditor.libraryPath

  return Drupal.settings.basePath + libraryPath + library;
};

/**
 * Get Fullscreenability setting.
 */
H5PIntegration.getFullscreen = function (contentId) {
  return Drupal.settings.h5p.content['cid-' + contentId].fullScreen === '1';
};

/**
 *
 */
H5PIntegration.addFilesToIframe = function ($iframe, contentId) {
  var styles = Array().concat(Drupal.settings.h5p.core.styles, Drupal.settings.h5p['cid-'+contentId].styles),
    scripts = Array().concat(Drupal.settings.h5p.core.scripts, Drupal.settings.h5p['cid-'+contentId].scripts),
    basePath = window.location.protocol + '//' + window.location.host + Drupal.settings.basePath;
  H5P.jQuery.each(styles, function (idx, style) {
    $iframe.contents().find('head') // TODO: Stop abusing jQuery selectors...
      .append('<link type="text/css" rel="Stylesheet" href="' + basePath + style + '"/>');
  });
  var doc = $iframe[0].contentDocument;
  var scriptCounter = 0;
  function addScriptAndWait() {
    // Cannot use jQuery append/prepend here. It does magic with scripts
    // that mess stuff up. (Basically, ends up staying in main window
    // context, re-initing H5P, etc.
    var script = doc.createElement('script');
    script.type = 'text/javascript';

    if (scriptCounter == scripts.length) {
      script.textContent = "H5P.init()";
    }
    else { // Add next script
      script.src = basePath + scripts[scriptCounter];
      script.onload = addScriptAndWait;
      scriptCounter++;
    }
    doc.body.appendChild(script);
  }
  addScriptAndWait();
}

H5PIntegration.fullscreenText = Drupal.t('Fullscreen');

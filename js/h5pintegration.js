// If run in an iframe, use parent version of globals.
if (window.parent !== window) {
  Drupal = window.parent.Drupal;
  jQuery = window.parent.jQuery;
}

H5P.jQuery(document).ready(function () {
  if (Drupal.settings.h5p === undefined) {
    return;
  }

  H5P.loadedJs = Drupal.settings.h5p.loadedJs;
  H5P.loadedCss = Drupal.settings.h5p.loadedCss;
  H5P.postUserStatistics = Drupal.settings.h5p.postUserStatistics;
  H5P.ajaxPath = Drupal.settings.h5p.ajaxPath;
  H5P.url = Drupal.settings.h5p.url;
  H5P.l10n = {H5P: Drupal.settings.h5p.i18n};
  H5P.contentDatas = Drupal.settings.h5p.content;
  H5P.user = Drupal.settings.h5p.user;

  H5P.init();
});

/**
 * Loop trough styles and create a set of tags for head.
 * TODO: Cache base tags or something to improve performance.
 *
 * @param {Array} styles List of stylesheets
 * @returns {String} HTML
 */
H5P.getHeadTags = function (contentId) {
  var basePath = window.location.protocol + '//' + window.location.host + Drupal.settings.basePath;

  var createUrl = function (path) {
    if (path.substring(0,7) !== 'http://' && path.substring(0,8) !== 'https://') {
      // Not external, add base path.
      path = basePath + path;
    }
    return path;
  };

  var createStyleTags = function (styles) {
    var tags = '';
    for (var i = 0; i < styles.length; i++) {
      tags += '<link rel="stylesheet" href="' + createUrl(styles[i]) + '">';
    }
    return tags;
  };

  var createScriptTags = function (scripts) {
    var tags = '';
    for (var i = 0; i < scripts.length; i++) {
      tags += '<script src="' + createUrl(scripts[i]) + '"></script>';
    }
    return tags;
  };

  return createStyleTags(Drupal.settings.h5p.core.styles) +
         createStyleTags(Drupal.settings.h5p['cid-' + contentId].styles) +
         createScriptTags(Drupal.settings.h5p.core.scripts) +
         createScriptTags(Drupal.settings.h5p['cid-' + contentId].scripts);
};

/**
 * @namespace H5PIntegration
 * Only used by libraries admin
 */
var H5PIntegration = H5PIntegration || {};

/**
 *  Returns an object containing a library metadata
 *
 *  @returns {object} { listData: object containing libraries, listHeaders: array containing table headers (translation done server-side) }
 */
H5PIntegration.getLibraryList = function () {
  return Drupal.settings.h5p.libraries;
};

/**
 *  Returns an object containing detailed info for a library
 *
 *  @returns {object} { info: object containing libraryinfo, content: array containing content info, translations: an object containing key/value }
 */
H5PIntegration.getLibraryInfo = function () {
  return Drupal.settings.h5p.library;
};

/**
 * Get the DOM element where the admin UI should be rendered
 *
 * @returns {jQuery object} The jquery object where the admin UI should be rendered
 */
H5PIntegration.getAdminContainer = function () {
  return H5P.jQuery('#h5p-admin-container');
};

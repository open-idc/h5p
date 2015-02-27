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

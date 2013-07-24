// TODO: Why can't h5pintegration.js just hook into the H5P namespace instead of creating its own?
var H5PIntegration = H5PIntegration || {};
var H5P = H5P || {};

$(document).ready(function () {
  H5P.loadedJs = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedJs !== undefined ? Drupal.settings.h5p.loadedJs : [];
  H5P.loadedCss = Drupal.settings.h5p !== undefined && Drupal.settings.h5p.loadedCss !== undefined ? Drupal.settings.h5p.loadedCss : [];
});

H5PIntegration.getJsonContent = function (contentId) {
  return Drupal.settings.h5p.content[contentId].jsonContent;
};

H5PIntegration.getContentPath = function (contentId) {
  if (Drupal.settings.h5p !== undefined && contentId !== undefined) {
    return Drupal.settings.h5p.jsonContentPath + contentId + '/';
  }
  else if (Drupal.settings.h5peditor !== undefined)  {
    return Drupal.settings.h5peditor.filesPath + '/h5peditor/';
  }
};

/**
 * Get the path to the library
 * 
 * @param {string} machineName The machine name of the library
 * @returns {string} The full path to the library
 */
H5PIntegration.getLibraryPath = function (machineName) {
  return Drupal.settings.basePath + Drupal.settings.h5p.libraryPath + '/' + machineName;
};

H5PIntegration.getFullscreen = function (contentId) {
  return Drupal.settings.h5p.content[contentId].fullScreen === '1';
};

H5PIntegration.fullscreenText = Drupal.t('Fullscreen');
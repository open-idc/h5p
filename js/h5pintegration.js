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
  if (Drupal.settings.h5p !== undefined) {
    return Drupal.settings.h5p.jsonContentPath + contentId + '/';
  }
};

H5PIntegration.getFullscreen = function (contentId) {
  return Drupal.settings.h5p.content[contentId].fullScreen === '1';
};

H5PIntegration.fullscreenText = Drupal.t('Fullscreen');
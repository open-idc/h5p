var H5PIntegration = H5PIntegration || {};

H5PIntegration.getJsonContent = function (contentId) {
  return Drupal.settings.h5p.jsonContent[contentId];
};

H5PIntegration.getContentPath = function (contentId) {
  if (Drupal.settings.h5p !== undefined) {
    return Drupal.settings.h5p.jsonContentPath + contentId + '/';
  }
};

H5PIntegration.getFullscreen = function (contentId) {
  return Drupal.settings.h5p.fullScreen[contentId] === '1';
};

H5PIntegration.fullscreenText = Drupal.t('Fullscreen');
var H5PIntegration = H5PIntegration || {};

H5PIntegration.getJsonContent = function (contentId) {
  return Drupal.settings.h5p.jsonContent[contentId];
};

H5PIntegration.getContentPath = function (contentId) {
  return Drupal.settings.h5p.jsonContentPath + contentId + '/';
};
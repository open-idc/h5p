/* globals drupalSettings */

try {
  window.H5PIntegration = drupalSettings.h5p.H5PIntegration;
}
catch (err) {
  console.error('Missing H5PIntegration settings.');
}

var H5P = H5P || {};

/**
 * 
 * @param {type} params
 * @param {type} id
 * @returns {undefined}
 */
H5P.eLecture = function (params, id) {
  console.log(params, id);
  
  this.electure = params.electure;
  this.contentPath = H5P.getContentPath(10);
};

/**
 * 
 * @param {type} $container
 * @returns {undefined}
 */
H5P.eLecture.prototype.attach = function ($container) {
  var videoHtml = '<video width="640" height="360">';
  
  for (var i = 0; i < this.electure.video.length; i++) {
    var video = this.electure.video[i];
    videoHtml += '<source src="' + this.contentPath + video.path + '" type="' + video.mime + '">';
  }
  
  videoHtml += 'Your browser does not support the video tag.</video>';
  
  $container.html(videoHtml);
};
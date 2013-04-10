var H5P = H5P || {};

/**
 * 
 * @param {type} params
 * @returns {undefined}
 */
H5P.cpImage = function (params, contentPath) {
  this.file = contentPath + params.file.path;
};

/**
 * 
 * @returns {undefined}
 */
H5P.cpImage.prototype.appendTo = function ($slide, width, height, x, y) {
  H5P.jQuery('<img src="' + this.file + '" alt="" width="' + width + '" height="' + height + '" style="position:absolute;top:' + y + 'px;left:' + x + 'px"/>').appendTo($slide);
};
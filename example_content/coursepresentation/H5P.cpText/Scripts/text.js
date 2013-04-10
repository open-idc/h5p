var H5P = H5P || {};

/**
 * 
 * @param {type} params
 * @returns {undefined}
 */
H5P.cpText = function (params, contentPath) {
  this.text = params.text;
};

/**
 * 
 * @returns {undefined}
 */
H5P.cpText.prototype.appendTo = function ($slide, width, height, x, y) {
  H5P.jQuery('<div style="position:absolute;padding:10px;width:' + width + 'px;height:' + height + 'px;top:' + y + 'px;left:' + x + 'px">' + this.text + '</div>').appendTo($slide);
};
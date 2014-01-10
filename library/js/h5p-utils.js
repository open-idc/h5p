var H5PUtils = H5PUtils || {};

(function ($) {
  /**
   * Generic function for creating a table including the headers
   * 
   * @param {array} headers List of headers
   */
  H5PUtils.createTable = function (headers) {
    var $table = $('<table class="h5p-admin-table"></table>');
    var $thead = $('<thead></thead>');
    var $tr = $('<tr></tr>');

    $.each(headers, function (index, value) {
      $tr.append('<th>' + value + '</th>');
    });
    
    return $table.append($thead.append($tr));
  };
  
  /**
   * Generic function for creating a table row
   * 
   * @param {array} rows Value list. Object name is used as class name in <TD>
   */
  H5PUtils.createTableRow = function (rows) {
    var $tr = $('<tr></tr>');
    
    $.each(rows, function (index, value) {
      $tr.append('<td>' + value + '</td>');
    });
    
    return $tr;
  };
  
})(H5P.jQuery);
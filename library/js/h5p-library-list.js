var H5PLibraryList= H5PLibraryList || {};

(function ($) {

  /**
   * Initializing
   */
  H5PLibraryList.init = function () {
    var $adminContainer = H5PIntegration.getAdminContainer();
    $adminContainer.append(H5PLibraryList.createLibraryList(H5PIntegration.getLibraryList()));
  };
  
  /**
   * 
   * 
   * @param {object} libraries List of libraries and headers
   */
  H5PLibraryList.createLibraryList = function (libraries) {
    // Create table
    var $table = H5PUtils.createTable(libraries.listHeaders);
    $table.addClass('libraries');
    
    // Add libraries
    $.each (libraries.listData, function (index, library) {
      var $libraryRow = H5PUtils.createTableRow([library.name, library.machineName, library.contentCount]);
      
      // Open details view when clicked
      $libraryRow.on('click', function (){
        window.location.href = H5PIntegration.getLibraryDetailsUrl(library.id);
      });
      
      $table.append($libraryRow);
    });
    
    return $table;
  };
 
  
  // Initialize me:
  $(document).ready(function () {
    if (!H5PLibraryList.initialized) {
      H5PLibraryList.initialized = true;
      H5PLibraryList.init();
    }
  });
  
})(H5P.jQuery);
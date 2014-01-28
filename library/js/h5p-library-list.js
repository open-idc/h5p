var H5PLibraryList= H5PLibraryList || {};

(function ($) {

  /**
   * Initializing
   */
  H5PLibraryList.init = function () {
    var $adminContainer = H5PIntegration.getAdminContainer();
    
    // Create library list
    $adminContainer.append(H5PLibraryList.createLibraryList(H5PIntegration.getLibraryList()));
  };
  
  /**
   * Create the library list
   * 
   * @param {object} libraries List of libraries and headers
   */
  H5PLibraryList.createLibraryList = function (libraries) {
    // Create table
    var $table = H5PUtils.createTable(libraries.listHeaders);
    $table.addClass('libraries');
    
    // Add libraries
    $.each (libraries.listData, function (index, library) {
      var $libraryRow = H5PUtils.createTableRow([
        library.name, 
        library.machineName, 
        library.contentCount, 
        '<button class="h5p-admin-view-library">&#xf002;</button>' +
        '<button class="h5p-admin-delete-library">&#xf057;</button>'
      ]);
      
      if(library.contentCount > 0) {
        $('.h5p-admin-delete-library', $libraryRow).addClass('disabled');
      }
      
      // Open details view when clicked
      $libraryRow.on('click', function (){
        window.location.href = library.detailsUrl;
      });
      
      $('.h5p-admin-delete-library', $libraryRow).on('click', function (event) {
        window.location.href = library.deleteUrl;
        return false;
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
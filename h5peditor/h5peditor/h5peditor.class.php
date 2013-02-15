<?php

class H5peditor {

  public static $styles = array(
    'styles/css/application.css',
  );
  public static $scripts = array(
    'scripts/h5peditor.js',
    'scripts/h5peditor-library-selector.js',
    'scripts/h5peditor-form.js',
    'scripts/h5peditor-text.js',
    'scripts/h5peditor-file.js',
    'scripts/h5peditor-group.js',
    'scripts/h5peditor-boolean.js',
    'scripts/h5peditor-list.js',
    'scripts/h5peditor-library.js',
    'scripts/h5peditor-dimensions.js',
    'scripts/h5peditor-coordinates.js',
  );
  private $storage, $files_directory;

  /**
   * Constructor.
   * 
   * @param object $storage
   * @param string $files_directory
   */
  function __construct($storage, $files_directory) {
    $this->storage = $storage;
    $this->files_directory = $files_directory;
  }

  /**
   * Create directories for uploaded content.
   * 
   * @param int $id
   * @return boolean
   */
  public function createDirectories($id) {
    $this->content_directory = $this->files_directory . '/h5p/content/' . $id . '/';

    $sub_directories = array('', 'files', 'images');
    foreach ($sub_directories AS $sub_directory) {
      $sub_directory = $this->content_directory . $sub_directory;
      if (!is_dir($sub_directory) && !@mkdir($sub_directory)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Move uploaded files and remove old files.
   * 
   * @param string $old_library
   * @param string $old_parameters
   * @param string $new_library
   * @param string $new_parameters
   */
  public function processParameters($contentId, $new_library, $new_parameters, $old_library = NULL, $old_parameters = NULL) {
    // TODO: Add support for versioning of libraries and dependencies
    $new_files = array();
    $old_files = array();
    $new_libraries = array($new_library, 'EmbeddedJS');
    $old_libraries = array($old_library, 'EmbeddedJS');

    // Find new libraries and files.  
    $this->processSemantics($new_files, $new_libraries, json_decode($this->storage->getSemantics($new_library)), $new_parameters);

    // TODO: Replace this with code that is aware of library versions
    foreach ($new_libraries as $key => $library_name) {
      // TODO: This is temporary. There is to be no queries in here!
      $new_libraries[$key] = array();
      $new_libraries[$key]['library']['libraryId'] = db_result(db_query(
          "SELECT library_id
        FROM {h5p_library}
        WHERE machine_name = '%s'", $library_name
        ));
      $new_libraries[$key]['preloaded'] = 1; // TODO: This is just for testing/demoing...
    }
    $frameworkInterface = _h5p_get_instance('interface'); // TODO: saver???
    $frameworkInterface->saveLibraryUsage($contentId, $new_libraries);

    if ($old_library) {
      // Find old files and libraries.
      $this->processSemantics($old_files, $old_libraries, json_decode($this->storage->getSemantics($old_library)), $old_parameters);

      // Remove old files.
      for ($i = 0, $s = count($old_files); $i < $s; $i++) {
        if (!in_array($old_files[$i], $new_files)) {
          $remove_file = $this->content_directory . $old_files[$i];
          unlink($remove_file);
          $this->storage->removeFile($remove_file);
        }
      }
    }
  }

  /**
   * Recursive function that moves the new files in to the h5p content folder and generates a list over the old files.
   * Also locates all the librares.
   * 
   * @param array $files
   * @param array $libraries
   * @param array $schema
   * @param array $params
   */
  private function processSemantics(&$files, &$libraries, $semantics, &$params) {
    for ($i = 0, $s = count($semantics); $i < $s; $i++) {
      $field = $semantics[$i];
      if (!isset($params->{$field->name})) {
        continue;
      }
      
      $this->processField($field, $params->{$field->name}, $files, $libraries);
    }
  }

  /**
   * Process a single field.
   * 
   * @staticvar string $h5peditor_path
   * @param object $field
   * @param mixed $params
   * @param array $files
   * @param array $libraries
   */
  private function processField(&$field, &$params, &$files, &$libraries) {
    static $h5peditor_path;
    if (!$h5peditor_path) {
      $h5peditor_path = $this->files_directory . '/h5peditor/';
    }
    
    switch ($field->type) {
      case 'file':
      case 'image':
        if (isset($params->path)) {
          $temp_file = $h5peditor_path . $params->path;
          if (file_exists($temp_file)) {
            rename($temp_file, $this->content_directory . $params->path);
            $this->storage->removeFile($temp_file);
          }

          $files[] = $params->path;
        }
        break;

      case 'library':
        if (isset($params->library) && isset($params->params)) {
          $libraries[$params->library] = $params->library; // TODO: Add version info
          $this->processSemantics($files, $libraries, json_decode($this->storage->getSemantics($params->library)), $params->params);
        }
        break;

      case 'group':
        if (isset($params)) {
          $this->processSemantics($files, $libraries, $field->fields, $params);
        }
        break;

      case 'table':
        if (is_array($params)) {
          for ($j = 0, $t = count($params); $j < $t; $j++) {
            $this->processSemantics($files, $libraries, $field->fields, $params[$j]);
          }
        }
        break;

      case 'list':
        if (is_array($params)) {
          for ($j = 0, $t = count($params); $j < $t; $j++) {
            $this->processField($field->field, $params[$j], $files, $libraries);
          }
        }
        break;
    }
  }

  /**
   * Get all scripts, css and semantics data for a library
   *
   * @param string $library_name
   *  Name of the library we want to fetch data for
   */
  public function getLibraryData($libraryName) {
    // TODO: Support different versions of the lib
    $libraryData = new stdClass();
    $libraryData->semantics = $this->storage->getSemantics($libraryName);

    $editorLibraryNames = $this->storage->getEditorLibraries($libraryName);

    foreach ($editorLibraryNames as $editorLibraryName) {
      $filePaths = $this->storage->getFilePaths($editorLibraryName);

      if (!empty($filePaths['js'])) {
        $libraryData->javascript = '';
        foreach ($filePaths['js'] as $jsFilePath) {
          $libraryData->javascript .= file_get_contents($jsFilePath);
        }
      }
      if (!empty($filePaths['css'])) {
        $libraryData->css = '';
        foreach ($filePaths['css'] as $cssFilePath) {
          $libraryData->css .= file_get_contents($cssFilePath);
        }
      }
    }

    return json_encode($libraryData);
  }

}
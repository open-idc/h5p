<?php

class H5peditor {

  public static $styles = array(
    'styles/css/application.css',
  );
  public static $scripts = array(
    'scripts/h5peditor.js',
    'scripts/h5peditor-editor.js',
    'scripts/h5peditor-library-selector.js',
    'scripts/h5peditor-form.js',
    'scripts/h5peditor-text.js',
    'scripts/h5peditor-html.js',
    'scripts/h5peditor-number.js',
    'scripts/h5peditor-textarea.js',
    'scripts/h5peditor-file.js',
    'scripts/h5peditor-av.js',
    'scripts/h5peditor-group.js',
    'scripts/h5peditor-boolean.js',
    'scripts/h5peditor-list.js',
    'scripts/h5peditor-library.js',
    'scripts/h5peditor-select.js',
    'scripts/h5peditor-dimensions.js',
    'scripts/h5peditor-coordinates.js',
    'scripts/h5peditor-none.js',
    'ckeditor/ckeditor.js',
  );
  private $storage, $files_directory, $basePath, $development;

  /**
   * Constructor.
   *
   * @param object $storage
   * @param string $files_directory
   */
  function __construct($storage, $filesDirectory, $basePath, $development = NULL) {
    $this->storage = $storage;
    $this->files_directory = $filesDirectory;
    $this->basePath = $basePath;
    $this->development = $development;
  }
  
  /**
   * Get list of libraries.
   *
   * @return array
   */
  public function getLibraries() {
    if (isset($_POST['libraries'])) {
      // Get details for the specified libraries.
      $libraries = array();
      foreach ($_POST['libraries'] as $libraryName) {
        $matches = array();
        preg_match_all('/(.+)\s(\d)+\.(\d)$/', $libraryName, $matches);
        if ($matches) {
          $libraries[] = (object) array(
            'uberName' => $libraryName,
            'name' => $matches[1][0],
            'majorVersion' => $matches[2][0],
            'minorVersion' => $matches[3][0]        
          );
        }
      }
    }
  
    $libraries = $this->storage->getLibraries($libraries === NULL ? NULL : $libraries);
    
    if ($this->development !== NULL) {
      $devLibs = $this->development->getLibraries();
      
      // Replace libraries with devlibs
      for ($i = 0, $s = count($libraries); $i < $s; $i++) {
        $lid = $libraries[$i]->name . ' ' . $libraries[$i]->majorVersion . '.' . $libraries[$i]->minorVersion;
        if (isset($devLibs[$lid])) {
          $libraries[$i] = (object) array(
            'uberName' => $lid,
            'name' => $devLibs[$lid]['machineName'],
            'title' => $devLibs[$lid]['title'],
            'majorVersion' => $devLibs[$lid]['majorVersion'],
            'minorVersion' => $devLibs[$lid]['minorVersion'],
            'runnable' => $devLibs[$lid]['runnable'],
          );
        }
      }
    }
    
    return json_encode($libraries);
  }
  
  /**
   * Keep track of temporary files.
   *
   * @param object file
   */
  public function addTmpFile($file) {
    $this->storage->addTmpFile($file);
  }

  /**
   * Create directories for uploaded content.
   *
   * @param int $id
   * @return boolean
   */
  public function createDirectories($id) {
    $this->content_directory = $this->files_directory . '/content/' . $id . '/';

    $sub_directories = array('', 'files', 'images', 'videos', 'audios');
    foreach ($sub_directories AS $sub_directory) {
      $sub_directory = $this->content_directory . $sub_directory;
      if (!is_dir($sub_directory) && !@mkdir($sub_directory)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Move uploaded files, remove old files and update library usage.
   *
   * @param string $oldLibrary
   * @param string $oldParameters
   * @param object $newLibrary
   * @param string $newParameters
   */
  public function processParameters($contentId, $newLibrary, $newParameters, $oldLibrary = NULL, $oldParameters = NULL) {
    $newFiles = array();
    $oldFiles = array();
    $newLibraries = array($newLibrary['machineName'] => $newLibrary);
    $oldLibraries = array($oldLibrary);

    // Find new libraries and files.
    $this->processSemantics($newFiles, $newLibraries, $this->storage->getSemantics($newLibrary['machineName'], $newLibrary['majorVersion'], $newLibrary['minorVersion']), $newParameters);

    $h5pStorage = _h5p_get_instance('storage');

    $librariesUsed = $newLibraries; // Copy

    foreach ($newLibraries as $library) {
      $libraryFull = $h5pStorage->h5pF->loadLibrary($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      $librariesUsed[$library['machineName']]['library'] = $libraryFull;
      $librariesUsed[$library['machineName']]['type'] = H5PCore::DEPENDENCY_TYPE_PRELOADED;
      $h5pStorage->findLibraryDependencies($librariesUsed, $libraryFull);
    }

    $h5pStorage->h5pF->deleteLibraryUsage($contentId);
    $h5pStorage->h5pF->saveLibraryUsage($contentId, $librariesUsed);

    if ($oldLibrary) {
      // Find old files and libraries.
      $this->processSemantics($oldFiles, $oldLibraries, $this->storage->getSemantics($oldLibrary['machineName'], $oldLibrary['majorVersion'], $oldLibrary['minorVersion']), $oldParameters);

      // Remove old files.
      for ($i = 0, $s = count($oldFiles); $i < $s; $i++) {
        if (!in_array($oldFiles[$i], $newFiles) && substr($oldFiles[$i], 0, 7) != 'http://') {
          $removeFile = $this->content_directory . $oldFiles[$i];
          unlink($removeFile);
          $this->storage->removeFile($removeFile);
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
      $h5peditor_path = $this->files_directory . '/editor/';
    }
    switch ($field->type) {
      case 'file':
      case 'image':
        if (isset($params->path)) {
          $oldPath = $h5peditor_path . $params->path;
          $newPath = $this->content_directory . $params->path;
          if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
            $this->storage->keepFile($oldPath, $newPath);
          }
          elseif (file_exists($newPath)) {
            $this->storage->keepFile($newPath, $newPath);
          }

          $files[] = $params->path;
        }
        break;

      case 'video':
      case 'audio':
        if (is_array($params)) {
          for ($i = 0, $s = count($params); $i < $s; $i++) {
            $oldPath = $h5peditor_path . $params[$i]->path;
            $newPath = $this->content_directory . $params[$i]->path;
            if (file_exists($oldPath)) {
              rename($oldPath, $newPath);
              $this->storage->keepFile($oldPath, $newPath);
            }
            elseif (file_exists($newPath)) {
              $this->storage->keepFile($newPath, $newPath);
            }
            $files[] = $params[$i]->path;
          }
        }
        break;

      case 'library':
        if (isset($params->library) && isset($params->params)) {
          $libraryData = h5peditor_get_library_property($params->library);
          $libraries[$libraryData['machineName']] = $libraryData;
          $this->processSemantics($files, $libraries, $this->storage->getSemantics($libraryData['machineName'], $libraryData['majorVersion'], $libraryData['minorVersion']), $params->params);
        }
        break;

      case 'group':
        if (isset($params)) {
          if (count($field->fields) == 1) {
            $params = (object) array($field->fields[0]->name => $params);
          }
          $this->processSemantics($files, $libraries, $field->fields, $params);
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
   * This really belongs on a library class... which doesn't exist.
   */
  public function getLibraryLanguage($machineName, $majorVersion, $minorVersion) {
    if ($this->development !== NULL) {
      // Try to get language development library first.
      $language = $this->development->getLanguage($machineName, $majorVersion, $minorVersion);
    }
    
    if (isset($language) === FALSE) {
      $language = $this->storage->getLanguage($machineName, $majorVersion, $minorVersion);
    }
    
    return ($language === FALSE ? NULL : $language);
  }
  
  /**
   * This really belongs on a library class... which doesn't exist.
   */
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion) {
    if ($this->development !== NULL) {
      // Try to get development editor libraries first
      $editors = $this->development->getLibraryEditors($machineName, $majorVersion, $minorVersion);
    }
    
    if ($editors === NULL) {
      $editors = $this->storage->getEditorLibraries($machineName, $majorVersion, $minorVersion);
    }

    return $editors;
  }
  
  /**
   * This really belongs on a library class... which doesn't exist.
   */
  public function getLibraryFiles($machineName, $majorVersion, $minorVersion) {
    if ($this->development !== NULL) {
      // Try to get language development library first.
      $files = $this->development->getLibraryFiles($machineName, $majorVersion, $minorVersion);
    }
    
    if ($files === NULL) {
      $files = $this->storage->getLibraryFiles($machineName, $majorVersion, $minorVersion);
    }
    
    return $files;
  }

  /**
   * Get all scripts, css and semantics data for a library
   *
   * @param string $library_name
   *  Name of the library we want to fetch data for
   */
  public function getLibraryData($machineName, $majorVersion, $minorVersion) {
    $libraryData = new stdClass();
    
    $libraries = $this->storage->getEditorLibraries($machineName, $majorVersion, $minorVersion);
    $library = reset($libraries);
    
    // TODO: Dev mode is probably broken since load library doesn't read from file.
    $libraryData->semantics = $this->storage->getSemantics($library);

    // TODO: Get language from $library.
    $libraryData->language = $this->storage->getLanguage($machineName, $majorVersion, $minorVersion);
    // TODO: langauge gets added a second time for the same library in JS futher down in this function.

    // TODO: Remember to check dropLibraryCss...
    foreach ($libraries as $library) {
      $files = $this->getLibraryFiles($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      
      // Javascripts
      if (!empty($files['scripts'])) {
        foreach ($files['scripts'] as $script) {
          if (!isset($libraryData->javascript[$script])) {
            $libraryData->javascript[$script] = '';
          }
          // TODO: rtrim and check substr(-1) === '}'? jsmin?
          // TODO: Perhaps just using the JS files would be fine? This would leverage browser caching and reduce server load.
          // TODO: Explain why we are using .= here.
          $libraryData->javascript[$script] .= "\n" . file_get_contents($script);
        }
      }
      
      $language = $this->getLibraryLanguage($library['machineName'], $library['majorVersion'], $library['minorVersion']);
    
    
      // Languages
      $language = $this->storage->getLanguage($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      if ($language !== NULL) {
        $lang = '; H5PEditor.language["' . $library['machineName'] . '"] = ' . $language . ';';
        $libraryData->javascript[md5($lang)] = $lang;
      }
      
      // Stylesheets
      if (!empty($files['styles'])) {
        foreach ($files['styles'] as $css) {
          H5peditor::buildCssPath(NULL, $this->basePath . dirname($css) . '/');
          $libraryData->css[$css] = preg_replace_callback('/url\([\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\)/i', 'H5peditor::buildCssPath', file_get_contents($css));
        }
      }
    }

    return json_encode($libraryData);
  }

  /**
   * This function will prefix all paths within a CSS file.
   * Copied from Drupal 6.
   *
   * @staticvar type $_base
   * @param type $matches
   * @param type $base
   * @return type
   */
  public static function buildCssPath($matches, $base = NULL) {
    static $_base;
    // Store base path for preg_replace_callback.
    if (isset($base)) {
      $_base = $base;
    }

    // Prefix with base and remove '../' segments where possible.
    $path = $_base . $matches[1];
    $last = '';
    while ($path != $last) {
      $last = $path;
      $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
    }
    return 'url('. $path .')';
  }
}

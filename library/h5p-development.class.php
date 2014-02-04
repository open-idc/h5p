<?php

/**
 * This is a data layer which uses the file system so it isn't specific to any framework.
 */
class H5PDevelopment {

  const MODE_NONE = 0;
  const MODE_CONTENT = 1;
  const MODE_LIBRARY = 2;

  private $implements, $libraries, $language;

  /**
   * Constructor.
   *
   * @param string Files path
   * @param array $libraries Optional cache input.
   */
  public function __construct($outerface, $filesPath, $language, $libraries = NULL) {
    $this->implements = $outerface;
    $this->language = $language;
    if ($libraries !== NULL) {
      $this->libraries = $libraries;
    }
    else {
      $this->findLibraries($filesPath . '/development');
    }
  }
  
  /**
   * Get contents of file.
   *
   * @param string File path.
   * @return mixed String on success or NULL on failure.
   */
  private function getFileContents($file) {
    if (file_exists($file) === FALSE) {
      return NULL;
    }
    
    $contents = file_get_contents($file);
    if ($contents === FALSE) {
      return NULL;
    }
    
    return $contents;
  }
  
  /**
   * Scans development directory and find all libraries.
   *
   * @param string $path Libraries development folder
   */
  private function findLibraries($path) {
    $this->libraries = array();
    
    if (is_dir($path) === FALSE) {
      return; 
    }
    
    $contents = scandir($path);
    
    for ($i = 0, $s = count($contents); $i < $s; $i++) {
      if ($contents[$i]{0} === '.') {
        continue; // Skip hidden stuff.
      }
      
      $libraryPath = $path . '/' . $contents[$i];
      $libraryJSON = $this->getFileContents($libraryPath . '/library.json');
      if ($libraryJSON === NULL) {
        continue; // No JSON file, skip.
      }
      
      $library = json_decode($libraryJSON, TRUE);
      if ($library === FALSE) {
        continue; // Invalid JSON.
      }
      
      // TODO: Validate props? Not really needed, is it? this is a dev site.
      
      // Save/update library.
      $library['libraryId'] = $this->implements->getLibraryId($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      $this->implements->saveLibraryData($library, $library['libraryId'] === FALSE);
      
      $library['path'] = $libraryPath;
      $this->libraries[H5PDevelopment::libraryToString($library['machineName'], $library['majorVersion'], $library['minorVersion'])] = $library;
    }
    
    // TODO: Should we remove libraries without files? Not really needed, but must be cleaned up some time, right?

    // Go trough libraries and insert dependencies. Missing deps. will just be ignored and not available. (I guess?!)
    foreach ($this->libraries as $library) {
      $this->implements->deleteLibraryDependencies($library['libraryId']); // This isn't very optimal, but it's the way of the core. Without it we would get duplicate warnings.
      $types = array('preloaded', 'dynamic', 'editor');
      foreach ($types as $type) {
        if (isset($library[$type . 'Dependencies'])) {
          $this->implements->saveLibraryDependencies($library['libraryId'], $library[$type . 'Dependencies'], $type);
        }
      }
    }
    // TODO: Apparently deps must be inserted into h5p_nodes_libraries as well... ? But only if they are used?!
  }
  
  /**
   * @return array Libraris in development folder.
   */
  public function getLibraries() {
    return $this->libraries;
  }
  
  /**
   * Get semantics for the given library.
   * 
   * @param string $name of the library.
   * @param int $majorVersion of the library.
   * @param int $minorVersion of the library.
   * @return string Semantics
   */
  public function getSemantics($name, $majorVersion, $minorVersion) {
    $library = H5PDevelopment::libraryToString($name, $majorVersion, $minorVersion);
    
    if (isset($this->libraries[$library]) === FALSE) {
      return NULL;
    }
    
    return $this->getFileContents($this->libraries[$library]['path'] . '/semantics.json');
  }
  
  /**
   * Get translations for the given library.
   * 
   * @param string $name of the library.
   * @param int $majorVersion of the library.
   * @param int $minorVersion of the library.
   * @return string Translation
   */
  public function getLanguage($name, $majorVersion, $minorVersion) {
    $library = H5PDevelopment::libraryToString($name, $majorVersion, $minorVersion);
    
    if (isset($this->libraries[$library]) === FALSE) {
      return NULL;
    }
    
    return $this->getFileContents($this->libraries[$library]['path'] . '/language/' . $this->language . '.json');
  }
  
  /**
   * Get editor library dependencies.
   *
   * @param string $name of the library.
   * @param int $majorVersion of the library.
   * @param int $minorVersion of the library.
   * @return null NULL.
   */
  public function getLibraryEditors($name, $majorVersion, $minorVersion) {
    $library = H5PDevelopment::libraryToString($name, $majorVersion, $minorVersion);
    
    if (isset($this->libraries[$library]) === FALSE) {
      return NULL;
    }
    $library = $this->libraries[$library];
    
    if ($library->editorDependencies === NULL) {
      return NULL;
    }
    
    // Apparently all dependencies has to be in the database.
    $editorlibraries = array();
    for ($i = 0, $s = count($library->editorDependencies); $i < $s; $i++) {
      $elid = $this->innerface->getLibraryId($library['machineName'], $library['majorVersion'], $library['minorVersion']);
      if ($elid === FALSE) {
        continue; // This dependency does not exist. TODO: Call somebody?
      }
      
      $editorlibraries[$elid] = $library->editorDependencies[$i];
    }
    
    return $editorlibraries;
  }
  
  /**
   * Get file paths for proloaded scripts and styles for the library in question.
   *
   * @param string $name of the library.
   * @param int $majorVersion of the library.
   * @param int $minorVersion of the library.
   * @return array with script and styles. NULL if not a dev lib.
   */
  public function getLibraryFiles($name, $majorVersion, $minorVersion) {
    $library = H5PDevelopment::libraryToString($name, $majorVersion, $minorVersion);

    if (isset($this->libraries[$library]) === FALSE) {
      return NULL;
    }
    $library = $this->libraries[$library];
    
    $file_paths = array(
      'scripts' => array(),
      'styles' => array()
    );
  
    // Add scripts
    if (isset($library['preloadedJs']) === TRUE) {
      for ($i = 0, $s = count($library['preloadedJs']); $i < $s; $i++) {
        $file_paths['scripts'][] = $library['path'] . '/' . $library['preloadedJs'][$i]['path'];
      }
    }
    
    // Add styles
    if (isset($library['preloadedCss']) === TRUE) {
      for ($i = 0, $s = count($library['preloadedCss']); $i < $s; $i++) {
        $file_paths['styles'][] = $library['path'] . '/' . $library['preloadedCss'][$i]['path'];
      }
    }

    return $file_paths;
  }
  
  /**
   * Writes library as string on the form "name majorVersion.minorVersion"
   * Really belongs as a toString on the library class...
   *
   * @param string $name Machine readable library name
   * @param integer $majorVersion
   * @param integer $majorVersion
   * @return string Library identifier.
   */
  public static function libraryToString($name, $majorVersion, $minorVersion) {
    return $name . ' ' . $majorVersion . '.' . $minorVersion;
  }
}


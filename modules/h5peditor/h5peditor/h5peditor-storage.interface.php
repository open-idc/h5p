<?php 
//TODO: Document this (What is provided in params (and how), and what is expected in return (how))

interface H5peditorStorage {
  public function getSemantics($machineName, $majorVersion = NULL, $minorVersion = NULL);
  public function getLanguage($machineName, $majorVersion, $minorVersion);
  public function getLibraryFiles($machineName, $majorVersion, $minorVersion);
  public function addTmpFile($file);
  public function removeFile($path);
  public function keepFile($oldPath, $newPath);
  public function getLibraries();
  public function getEditorLibraries($machineName, $majorVersion, $minorVersion);
}

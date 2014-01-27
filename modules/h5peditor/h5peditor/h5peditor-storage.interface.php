<?php 
//TODO: Document this (What is provided in params (and how), and what is expected in return (how))

interface H5peditorStorage {
  public function getSemantics($machine_name, $major_version, $minor_version);
  public function getLanguage($machineName, $majorVersion, $minorVersion);
  public function getLibraryFiles($machineName, $majorVersion, $minorVersion);
  public function addTmpFile($file);
  public function keepFile($oldPath, $newPath);
  public function removeFile($path);
  public function getLibraries();
  public function getLibraryEditors($machineName, $majorVersion, $minorVersion);
  public function findLibraryDependencies(&$libraries);
}

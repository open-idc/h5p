<?php 
//FIXME: Document this (What is provided in params (and how), and what is
//expected in return (how))

interface H5peditorStorage {
  public function getSemantics($machineName, $majorVersion = NULL, $minorVersion = NULL);
  public function addTempFile($file);
  public function removeFile($path);
  public function keepFile($oldPath, $newPath);
  public function getLibraries();
}

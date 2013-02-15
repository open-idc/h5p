<?php 

interface H5peditorStorage {
  public function getSemantics($library_name);
  public function addFile($file);
  public function removeFile($path);
  public function getLibraries();
}
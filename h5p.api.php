<?php
/**
 * @file
 * Describe hooks provided by the H5P module.
 */

function hook_h5p_semantics_alter(&$semantics, $machine_name, $major_version, $minor_version) {
  if ($machine_name == 'H5P.Text' && $major_version == 1 && $minor_version == 0) {
    $semantics[0]->tags[] = 'h4';
  }
}

?>

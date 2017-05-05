<?php

namespace Drupal\h5p\H5PDrupal;

use H5PEventBase;


/**
 * Makes it easy to track events throughout the H5P system.
 *
 * @package    H5P
 * @copyright  2016 Joubel AS
 * @license    MIT
 */
class H5PEvent extends H5PEventBase {

  /**
   * Overrides H5PEventBase::save().
   *
   * Stores the event data in the database.
   */
  protected function save() {

    // Get data in array format without NULL values
    $data = $this->getDataArray();

    // Add user
    $data['user_id'] = \Drupal::currentUser()->id();

    // Insert into DB
    $this->id = db_insert('h5p_events')
      ->fields($data)
      ->execute();

    return $this->id;
  }

  /**
   * Overrides H5PEventBase::saveStats().
   *
   * Add current event data to statistics counter.
   */
  protected function saveStats() {
    $type = $this->type . ' ' . $this->sub_type;

    // Verify if counter exists
    $current_num = db_query(
        "SELECT num
           FROM {h5p_counters}
          WHERE type = :type
            AND library_name = :library_name
            AND library_version = :library_version
        ", array(
          ':type' => $type,
          ':library_name' => $this->library_name,
          ':library_version' => $this->library_version
        ))->fetchField();

    if ($current_num === FALSE) {
      // Insert new counter
      db_insert('h5p_counters')
          ->fields(array(
            'type' => $type,
            'library_name' => $this->library_name,
            'library_version' => $this->library_version,
            'num' => 1
          ))
          ->execute();
    }
    else {
     // Update counter with num+1
     db_query(
         "UPDATE {h5p_counters}
             SET num = num + 1
           WHERE type = :type
             AND library_name = :library_name
             AND library_version = :library_version
         ", array(
           ':type' => $type,
           ':library_name' => $this->library_name,
           ':library_version' => $this->library_version
         ));
    }
  }
}

<?php

namespace Drupal\h5p\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a node insertion demo event for event listeners.
 */
class FinishedEvent extends Event {

  const FINISHED_EVENT = 'h5p.finished';

  /**
   * @var array
   */
  protected $quizData;

  /**
   * Constructs a FinishedEvent object.
   *
   * @param array $quiz_data
   *   Database connection service.
   */
  public function __construct(array $quiz_data) {
    $this->quizData = $quiz_data;
  }

  /**
   * @return array
   *   Quiz data.
   */
  public function getQuizFields() {
    return $this->quizData;
  }

}

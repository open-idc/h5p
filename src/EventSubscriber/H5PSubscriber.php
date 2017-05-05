<?php

/**
 * @file
 * Contains \Drupal\h5p\EventSubscriber\H5PSubscriber.
 *
 * @author
 * Jörg Matheisen, www.drupalme.de
 */

namespace Drupal\h5p\EventSubscriber;

// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
// We'll use this to perform a redirect if necessary.
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Code to run in conjunction with migrations.
 */
class H5PSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $event = array();
    $events[KernelEvents::REQUEST][] = array('onRequest');
    return $events;
  }

  /**
   * This method is called whenever the KernelEvents::REQUEST event is
   * dispatched.
   *
   * @param GetResponseEvent $event
   */
  public function onRequest(GetResponseEvent $event) {

  }

}

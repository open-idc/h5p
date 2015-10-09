<?php /**
 * @file
 * Contains \Drupal\h5p\EventSubscriber\InitSubscriber.
 */

namespace Drupal\h5p\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InitSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['onEvent', 0]];
  }

  public function onEvent() {
    $route = \Drupal::routeMatch()->getRouteObject();
    $is_admin = \Drupal::service('router.admin_context')->isAdminRoute($route);
    if ($is_admin && empty($_POST) && \Drupal::currentUser()->hasPermission('access administration pages')) {
      $core = _h5p_get_instance('core');
      $core->validateLibrarySupport();
      _h5p_display_unsupported_libraries(\Drupal::routeMatch()->getRouteName() === 'h5p.library_details');
    }
  }

}

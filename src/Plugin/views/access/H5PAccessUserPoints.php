<?php

namespace Drupal\h5p\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides access control for viewing H5P user points
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "h5paccessuserpoints",
 *   title = @Translation("Access H5P user points"),
 *   help = @Translation("Is the logged in user allowed to see H5P result data for this user?")
 * )
 */

class H5PAccessUserPoints extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if ($account->hasPermission('access all h5p results')) {
      return TRUE;
    }

    $current_path = explode('/', \Drupal::service('path.current')->getPath());

    return ($current_path[1] === 'user' && is_numeric($current_path[2]) &&
        $account->hasPermission('access own h5p results') &&
        $account->id() === $current_path[2]);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_access', 'TRUE');
  }
}

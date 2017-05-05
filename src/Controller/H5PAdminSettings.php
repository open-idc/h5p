<?php

/**
 * @file
 * H5PAdmin
 *
 * @author
 * Jörg Matheisen, www.drupalme.de
 */

namespace Drupal\h5p\Controller;

use Drupal\h5p\Helper;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Driver\mysql\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;


class H5PAdminSettings  extends ControllerBase {


  protected $database;

  /**
   * constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {

    $controller = new static(
      $container->get('database')
    );
    return $controller;
  }


  function adminSettingsForm() {
    return \Drupal::formBuilder()->getForm('Drupal\h5p\Form\H5PAdminSettingsForm');
  }


}
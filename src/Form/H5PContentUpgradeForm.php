<?php

namespace Drupal\h5p\Form;

use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Controller\H5PContentUpgrade;
use Drupal\h5p\Controller\H5PLibraryAdmin;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the H5PContentUpgradeForm form.
 */
class H5PContentUpgradeForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_content_upgrade_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $library_id = NULL) {
    $versions = H5PContentUpgrade::getLibraryVersions($library_id);
    $library = $versions[$library_id];
    $core = H5PDrupal::getInstance('core');
    $upgrades = $core->getUpgrades($library, $versions);

    if (count($versions) < 2) {
      return ['#markup' => t('There are no available upgrades for this library.')];
    }

    // Get num of contents that can be upgraded
    $contents = $core->h5pF->getNumContent($library_id);
    if (!$contents) {
      return ['#markup' => t('There are no content instances to upgrade.')];
    }

    $contents_plural = \Drupal::translation()->formatPlural($contents, '1 content instance', '@count content instances');
    $returnLink = Link::fromTextAndUrl(t('Return'), Url::fromUri('internal:/admin/content/h5p/'))->toString();
    $h5p_module_rel = base_path() . drupal_get_path('module', 'h5p');
    $settings = [
      'libraryInfo' => [
        'message' => t('You are about to upgrade %num. Please select upgrade version.', ['%num' => $contents_plural]),
        'inProgress' => t('Upgrading to %ver...'),
        'error' => t('An error occurred while processing parameters:'),
        'errorData' => t('Could not load data for library %lib.'),
        'errorScript' => t('Could not load upgrades script for %lib.'),
        'errorContent' => t('Could not upgrade content %id:'),
        'errorParamsBroken' => t('Parameters are broken.'),
        'errorLibrary' => t('Missing required library %lib.'),
        'errorTooHighVersion' => t('Parameters contain %used while only %supported or earlier are supported.'),
        'errorNotSupported' => t('Parameters contain %used which is not supported.'),
        'done' => t('You have successfully upgraded %num.', ['%num' => $contents_plural]) . $returnLink,
        'library' => [
          'name' => $library->machine_name,
          'version' => $library->major_version . '.' . $library->minor_version,
        ],
        'libraryBaseUrl' => Url::fromUri('internal:/admin/content/h5p/upgrade/library')->toString(),
        'scriptBaseUrl' => "{$h5p_module_rel}/vendor/h5p/h5p-core/js/",
        'buster' => '?' . \Drupal::state()->get('system.css_js_query_string', '0'),
        'versions' => $upgrades,
        'contents' => $contents,
        'buttonLabel' => t('Upgrade'),
        'infoUrl' => Url::fromUri("internal:/admin/content/h5p/libraries/{$library_id}/upgrade")->toString(),
        'total' => $contents,
        'token' => \H5PCore::createToken('contentupgrade'), // Use token to avoid unauthorized updating
      ]
    ];

    // Create page - add settings and JS
    $build['#markup'] = '<div id="h5p-admin-container">' . t('Please enable JavaScript.') . '</div>';
    $build['#attached'] = H5PLibraryAdmin::addSettings($settings);
    $build['#attached']['library'][] = 'h5p/h5p.admin.library.upgrade';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is intentionally left blank
  }
}

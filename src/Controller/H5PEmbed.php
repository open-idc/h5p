<?php

namespace Drupal\h5p\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\Core\Render\HtmlResponse;

/**
 * The embed controller
 */
class H5PEmbed extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function embed($id) {

    // Prepare reponse with cache tag for invalidation
    $response = [
      '#cache' => [
        'tags' => [
          'h5p_content:' . $id,
        ],
      ],
    ];

    $h5p_module_path = drupal_get_path('module', 'h5p');

    // Load requested content
    $h5p_content = H5PContent::load($id);

    // TODO: Check access to field / entity ?
    if (empty($h5p_content)) {
      $response['#markup'] = '<body style="margin:0"><div style="background: #fafafa url(' . base_path() . $h5p_module_path . 'vendor/h5p/h5p-core/images/h5p.svg) no-repeat center;background-size: 50% 50%;width: 100%;height: 100%;"></div><div style="width:100%;position:absolute;top:75%;text-align:center;color:#434343;font-family: Consolas,monaco,monospace">' . t('Content unavailable.') . '</div></body>';
      return new HtmlResponse($response);
    }

    // Grab the core integration settings
    $integration = H5PDrupal::getGenericH5PIntegrationSettings();

    // Add content specific settings
    $content_id_string = 'cid-' . $id;
    $integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings();

    // Load core assets
    $coreAssets = H5PDrupal::getCoreAssets();

    // Load dependencies
    $core = H5PDrupal::getInstance('core');
    $preloaded_dependencies = $core->loadContentDependencies($id, 'preloaded');
    $files = $core->getDependenciesFiles($preloaded_dependencies, H5PDrupal::getRelativeH5PPath());

    // Load public files
    $jsFilePaths = array_map(function($asset){ return $asset->path; }, $files['scripts']);
    $cssFilePaths = array_map(function($asset){ return $asset->path; }, $files['styles']);

    // Get aggregated assets
    $aggregatedAssets = H5PDrupal::aggregatedAssets([$coreAssets['scripts'], $jsFilePaths], [$coreAssets['styles'], $cssFilePaths]);
    // Merge assets
    $scripts = array_merge($aggregatedAssets['scripts'][0], $aggregatedAssets['scripts'][1]);
    $styles = array_merge($aggregatedAssets['styles'][0], $aggregatedAssets['styles'][1]);

    // Get current language
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $content = [
      'id' => $id,
      'title' => "H5P Content {$id}",
    ];

    // Render the page and add to the response
    ob_start();
    include $h5p_module_path . '/vendor/h5p/h5p-core/embed.php';
    $response['#markup'] = ob_get_clean();

    return new HtmlResponse($response);
  }

}

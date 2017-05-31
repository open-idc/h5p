<?php

namespace Drupal\h5p\Plugin\Field\FieldFormatter;

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Entity\H5PContent;


/**
 * Plugin implementation of the 'h5p_default' formatter.
 *
 * @FieldFormatter(
 *   id = "h5p_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "h5p"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class H5PDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Displays interactive H5P content.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();
    $jsOptimizer = \Drupal::service('asset.js.collection_optimizer');
    $cssOptimizer = \Drupal::service('asset.css.collection_optimizer');
    $moduleHandler = \Drupal::service('module_handler');
    $systemPerformance = \Drupal::config('system.performance');
    $cssAssetConfig = array( 'preprocess' => $systemPerformance->get('css.preprocess'), 'media' => 'css' );
    $jsAssetConfig = array( 'preprocess' => $systemPerformance->get('js.preprocess') );

    foreach ($items as $delta => $item) {
      $value = $item->getValue();

      // Load H5P Content entity
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $h5p_integration = H5PDrupal::getGenericH5PIntegrationSettings();

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $h5p_integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings();

      $core = H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      // Load dependencies
      $files = $core->getDependenciesFiles($preloaded_dependencies, H5PDrupal::getRelativeH5PPath());

      $loadpackages = [
        'h5p/h5p.content',
      ];

      // Load dependencies
      foreach ($preloaded_dependencies as $dependency) {
        $loadpackages[] = 'h5p/' . _h5p_library_machine_to_id($dependency);
      }

      // Add alter hooks
      $moduleHandler->alter('h5p_scripts', $files['scripts'], $loadpackages, $h5p_content->getLibrary()->embed_types);
      $moduleHandler->alter('h5p_styles', $files['styles'], $loadpackages, $h5p_content->getLibrary()->embed_types);

      // Determine embed type and HTML to use
      if ($h5p_content->isDivEmbeddable()) {
        $html = '<div class="h5p-content" data-content-id="' . $h5p_content->id() . '"></div>';
      }
      else {
        // reset packages sto be loaded dynamically
        $loadpackages = [
          'h5p/h5p.content',
        ];

        // set html
        $html = '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-' . $h5p_content->id() . '" class="h5p-iframe" data-content-id="' . $h5p_content->id() . '" style="height:1px" src="about:blank" frameBorder="0" scrolling="no"></iframe></div>';

        // Load core assets
        $coreAssets = H5PDrupal::getCoreAssets();

        $h5p_integration['core']['styles'] = $this->createCachedPublicFiles($coreAssets['styles'], $cssOptimizer, $cssAssetConfig);
        $h5p_integration['core']['scripts'] = $this->createCachedPublicFiles($coreAssets['scripts'], $jsOptimizer, $jsAssetConfig);

        // Load public files
        $jsFilePaths = array_map(function($asset){ return $asset->path; }, $files['scripts']);
        $cssFilePaths = array_map(function($asset){ return $asset->path; }, $files['styles']);

        $h5p_integration['contents'][$content_id_string]['styles'] = $this->createCachedPublicFiles($cssFilePaths, $cssOptimizer, $cssAssetConfig);
        $h5p_integration['contents'][$content_id_string]['scripts'] = $this->createCachedPublicFiles($jsFilePaths, $jsOptimizer, $jsAssetConfig);
      }

      // Render each element as markup.
      $element[$delta] = array(
        '#type' => 'markup',
        '#markup' => $html,
        '#allowed_tags' => ['div','iframe'],
        '#attached' => [
          'drupalSettings' => [
            'h5p' => [
              'H5PIntegration' => $h5p_integration,
            ]
          ],
          'library' => $loadpackages,
        ],
        '#cache' => [
          'tags' => [
            'h5p_content:' . $h5p_content->id()
          ]
        ],
      );
    }

    return $element;
  }

  /**
   * Combines a set of files to a cached version, that is public available
   *
   * @param string[] $filePaths
   * @param AssetCollectionOptimizerInterface $optimizer
   * @param array $assetConfig
   *
   * @return string[]
   */
  private function createCachedPublicFiles(array $filePaths, $optimizer, $assetConfig) {
    $assets = $this->createDependencyFileAssets($filePaths, $assetConfig);
    $cachedAsset = $optimizer->optimize($assets);

    return array_map(function($publicUrl){ return file_create_url($publicUrl); }, array_column($cachedAsset, 'data'));
  }

  /**
   * Takes a list of file paths, and creates drupal Assets
   *
   * @param string[] $filePaths
   * @param array $assetConfig
   *
   * @return array
   */
  private function createDependencyFileAssets($filePaths, $assetConfig) {
    $result = array();

    $defaultAssetConfig = [
      'type' => 'file',
      'group' => 'h5p',
      'cache' => TRUE,
      'attributes' => [],
      'version' => NULL,
      'browsers' => [],
    ];

    foreach ($filePaths as $index => $path) {
      $path = $this->cleanFilePath($path);

      $result[$path] = [
        'weight' => count($filePaths) - $index,
        'data' => $path,
      ] + $assetConfig + $defaultAssetConfig;
    }

    return $result;
  }

  /**
   * Remove leading / and remove query part of an URL
   *
   * @param string $path
   *
   * @return string
   */
  private function cleanFilePath($path){
    return explode('?', $path)[0];
  }
}

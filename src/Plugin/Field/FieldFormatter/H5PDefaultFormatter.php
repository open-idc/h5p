<?php

namespace Drupal\H5P\Plugin\Field\FieldFormatter;

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

      // TODO: Are alter hooks really needed with the new dependency system?
      //$library_list = _h5p_dependencies_to_library_list($preloaded_dependencies);
      //\Drupal::moduleHandler()->alter('h5p_scripts', $files['scripts'], $library_list, $embed_type);
      //\Drupal::moduleHandler()->alter('h5p_styles', $files['styles'], $library_list, $embed_type);

      $loadpackages = [
        'h5p/h5p.content',
      ];

      // Determine embed type and HTML to use
      if ($h5p_content->isDivEmbeddable()) {
        $html = '<div class="h5p-content" data-content-id="' . $h5p_content->id() . '"></div>';

        // Load dependencies
        foreach ($preloaded_dependencies as $dependency) {
          $loadpackages[] = 'h5p/' . _h5p_library_machine_to_id($dependency);
        }
      }
      else {
        $html = '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-' . $h5p_content->id() . '" class="h5p-iframe" data-content-id="' . $h5p_content->id() . '" style="height:1px" src="about:blank" frameBorder="0" scrolling="no"></iframe></div>';

        // Load core assets
        $coreAssets = H5PDrupal::getCoreAssets();

        $h5p_integration['core']['styles'] = $this->createCachedPublicFiles($coreAssets['styles'], $cssOptimizer);
        $h5p_integration['core']['scripts'] = $this->createCachedPublicFiles($coreAssets['scripts'], $jsOptimizer);

        // Load dependencies
        $files = $core->getDependenciesFiles($preloaded_dependencies);
        $jsFilePaths = $this->getDependencyFilePaths($files['scripts'], $h5p_integration['url']);
        $cssFilePaths = $this->getDependencyFilePaths($files['styles'], $h5p_integration['url']);

        $h5p_integration['contents'][$content_id_string]['styles'] = $this->createCachedPublicFiles($cssFilePaths, $cssOptimizer);
        $h5p_integration['contents'][$content_id_string]['scripts'] = $this->createCachedPublicFiles($jsFilePaths, $jsOptimizer);

        //$this->moduleHandler->alter('js', $javascript, $assets);
        //$this->themeManager->alter('js', $javascript, $assets);
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
   * Takes files configs and returns absolute file paths
   *
   * @param array $scripts
   * @param string $baseUrl
   *
   * @return string[]
   */
  private function getDependencyFilePaths(array $scripts, $baseUrl) {
    return preg_filter('/^/', ltrim($baseUrl, '/'), array_column($scripts, 'path'));
  }

  /**
   * Combines a set of files to a cached version, that is public available
   *
   * @param string[] $filePaths
   * @param AssetCollectionOptimizerInterface $optimizer
   *
   * @return string[]
   */
  private function createCachedPublicFiles(array $filePaths, $optimizer) {
    $assets = $this->createDependencyFileAssets($filePaths);
    $cachedAsset = $optimizer->optimize($assets);

    return array_map(function($publicUrl){ return file_create_url($publicUrl); }, array_column($cachedAsset, 'data'));
  }

  /**
   * Takes a list of file paths, and creates drupal Assets
   *
   * @param string[] $filePaths
   * @return array
   */
  private function createDependencyFileAssets($filePaths) {
    $result = array();

    foreach ($filePaths as $index => $path) {
      $path = $this->cleanFilePath($path);

      $result[$path] = [
        'type' => 'file',
        'group' => 'h5p',
        'weight' => count($filePaths) - $index,
        'cache' => TRUE,
        'preprocess' => TRUE,
        'attributes' => [],
        'version' => NULL,
        'browsers' => [],
        'data' => $path
      ];
    }

    return $result;
  }

  private function cleanFilePath($path){
    return explode('?', ltrim($path, '/'))[0];
  }
}

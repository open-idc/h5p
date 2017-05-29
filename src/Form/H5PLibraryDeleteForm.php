<?php

namespace Drupal\h5p\Form;

use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5p\Controller\H5PLibraryAdmin;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Implements the H5PLibraryDeleteForm form.
 */
class H5PLibraryDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_library_delete_form';
  }

  /**
   * Check if library is deletable
   *
   * @param string $library_id
   * @return boolean
   */
  private function isLibraryDeletable($library_id) {
    // Is library deletable ?
    $h5p_drupal = H5PDrupal::getInstance('interface');
    $notCached = $h5p_drupal->getNumNotFiltered();
    $library_usage = $h5p_drupal->getLibraryUsage($library_id, $notCached ? TRUE : FALSE);
    return ($library_usage['content'] === 0 && $library_usage['libraries'] === 0);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $library_id = NULL) {
    if ($this->isLibraryDeletable($library_id)) {
      // Create form:
      $form['library_id'] = array(
        '#type' => 'hidden',
        '#value' => $library_id
      );

      $form['info'] = array(
        '#markup' => '<div>' . t('Are you sure you would like to delete the @library_name H5P library?', array('@library_name' => H5PLibraryAdmin::libraryDetailsTitle($library_id))) . '</div>'
      );

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Delete'),
      );
    }
    else {
      // May not delete this one
      $form['undeletable'] = [
        '#markup' => t('Library is in use by content, or is dependent by other librarie(s), and can therefore not be deleted'),
      ];
    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $library_id = $form_state->getValue('library_id');
    if ($this->isLibraryDeletable($library_id)) {
      H5PDrupal::getInstance('core')->deleteLibrary($library_id);
    }
  }
}

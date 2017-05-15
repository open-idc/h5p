<?php

namespace Drupal\h5p\Form;

use Drupal\h5p\Helper;
use Drupal\h5p\H5PApi\H5PClasses;
use Drupal\h5p\H5PApi\H5PFileStorageInterface;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Implements teh UserRegisterPrivat form.
 */
class H5PLibraryDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_library_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $library_id = NULL, $library_name = NULL) {

    $form['library_id'] = array(
      '#type' => 'hidden',
      '#value' => $library_id
    );

    $form['info'] = array(
      '#markup' => '<div>' . t('Are you sure you would like to delete the @library_name H5P library?', array('@library_name' => $library_name)) . '</div>'
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
    );

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save package
    $helper = new Helper\H5PEnvironment();
    $core = $helper->getInstance('core');

    // Do the actual deletion:
    $library_id = $form_state->getValue('library_id');
    $core->deleteLibrary($library_id);
  }
}

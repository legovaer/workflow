<?php

/**
 * @file
 * Contains \Drupal\workflow_access\Form\WorkflowAccessSettingsForm.
 */

namespace Drupal\workflow_access\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the base form for workflow add and edit forms.
 */
class WorkflowAccessSettingsForm implements FormInterface { // extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_access_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['workflow_access'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('Workflow Access Settings'),
    );
    $form['workflow_access']['#tree'] = TRUE;

    $url = '';// @todo D8-port: $url = \Drupal\Core\Url::fromUri('https://api.drupal.org/api/drupal/core%21modules%21node%21node.api.php/function/hook_node_access_records/8');
    $form['workflow_access']['workflow_access_priority'] = array(
      '#type' => 'weight',
      '#delta' => 10,
      '#title' => t('Workflow Access Priority'),
      '#default_value' => \Drupal::config('workflow_access.settings')->get('workflow_access_priority'),
      '#description' => t('This sets the node access priority. Changing this
      setting can be dangerous. If there is any doubt, leave it at 0.
      <a href="@url">Read the manual at https://api.drupal.org/api/drupal/core%21modules%21node%21node.api.php/function/hook_node_access_records/8 .</a>', array('@url' => $url)),
    );

    $form += $this->actionsElement($form, $form_state);

    return $form;
  }

  /**
   * Returns the action form element for the current entity form.
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = $this->actions($form, $form_state);

    if (isset($element['submit'])) {
      // Give the primary submit button a #button_type of primary.
      $element['submit']['#button_type'] = 'primary';
    }

    if (!empty($element)) {
      $element['#type'] = 'actions';
    }

    return $element;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   */
  function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#submit' => array('::submitForm'),
    );

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $weight = $form_state->getValues()['workflow_access']['workflow_access_priority'];
    \Drupal::configFactory()->getEditable('workflow_access.settings')->set('workflow_access_priority', $weight)->save();
    $form_state->setRedirect('entity.workflow_type.collection');
  }

}

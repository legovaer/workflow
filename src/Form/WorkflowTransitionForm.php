<?php

/**
 * @file
 * Contains \Drupal\workflow\Form\WorkflowTransitionForm.
 */

namespace Drupal\workflow\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowConfigTransitionInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a Transition Form to be used in the Workflow Widget.
 */
class WorkflowTransitionForm extends ContentEntityForm {

  /*************************************************************************
   *
   * Implementation of interface FormInterface.
   *
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    /* @var $transition WorkflowTransitionInterface */
    $transition = $this->entity;
    $field_name = $transition->getFieldName();

    /* @var $entity EntityInterface */
    // Entity may be empty on VBO bulk form.
    // $entity = $transition->getEntity();
    // Compose Form Id from string + Entity Id + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form Id is unique, to allow for multiple forms per page.
    // $workflow_type_id = $transition->getWorkflowId();
    // Field name contains implicit entity_type & bundle (since 1 field per entity)
    // $entity_type = ($entity) ? $entity->getEntityTypeId() : '';
    // $entity_bundle = ($entity) ? $entity->bundle() : '';
    // $entity_id = ($entity) ? $entity->id() : '';

    // Emulate nodeForm convention.
    if ($transition->id()) {
      $suffix = 'edit_form';
    }
    else {
      $suffix = 'form';
    }
    $form_id = implode('_', array('workflow_transition', $field_name, $suffix));

    return $form_id;
  }

  /**
   * {@inheritdoc}
   *
   * N.B. The D8-version of this form is stripped. If any use case is missing:
   * - compare with the D7-version of WorkflowTransitionForm::submitForm()
   * - compare with the D8-version of WorkflowTransitionElement::copyFormValuesToEntity()
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    return;
  }

  /*************************************************************************
   *
   * Implementation of interface EntityFormInterface (extends FormInterface).
   *
   */

  /**
   * This function is called by buildForm().
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // $form = parent::form($form, $form_state);

    /*
     * Input
     */
    $transition = $this->entity;

    /*
     * Output
     *
     * @TODO D8-port: use a proper WorkflowTransitionElement call.
     */
    // $form = $this->element($form, $form_state, $transition);
    //    $form['workflow_transition'] = array(
    //      '#type' => 'workflow_transition',
    //      '#title' => t('Workflow transition'),
    //      '#default_value' => $transition,
    //    );
    $element['#default_value'] = $transition;
    $form += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
    return $form;
  }

  /**
   * Returns the action form element for the current entity form.
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);

    if (!_workflow_use_action_buttons()) {
      return $element;
    }

    // Change the Form, to add action buttons, not select list/radios.
    // @see EntityForm's parent::actionsElement($form, $form_state);
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    return $element;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   * Caveat: !! It is not declared in the EntityFormInterface !!
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @return
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if (!_workflow_use_action_buttons()) {
      return $actions;
    }

    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = clone $this->entity;
    // N.B. Use a proprietary version of copyFormValuesToEntity,
    // where $entity is passed by reference.
    // $this->copyFormValuesToEntity($entity, $form, $form_state);
    $item = $form_state->getValues();
    $entity = WorkflowTransitionElement::copyFormItemValuesToEntity($entity, $form, $item);

    // Mark the entity as NOT requiring validation. (Used in validateForm().)
    $entity->setValidationRequired(FALSE);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    // Add class following node-form pattern (both on form and container).
    // D8-port: This is apparently already magically set in parent.
    // $form['#attributes']['class'][] = 'workflow-transition-' . $workflow_type_id . '-form';
    // $form['#attributes']['class'][] = 'workflow-transition-form';
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This is called from submitForm().
   */
  public function save(array $form, FormStateInterface $form_state) {
    // $result = parent::save($form, $form_state);

    /*  @var $transition WorkflowTransitionInterface */
    $transition = $this->entity;

    // Execute transition and update the attached entity.
    return Workflow::workflowManager()->executeTransition($transition);
  }

  /*************************************************************************
   *
   * Implementation of interface ContentEntityFormInterface (extends EntityFormInterface).
   *
   */

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Workflow module does not add any validations. They are on element level.
    //    parent::validateForm($form, $form_state);
    return;
  }

}

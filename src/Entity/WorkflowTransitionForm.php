<?php

/**
 * @file
 * Contains \Drupal\workflow\Entity\WorkflowTransitionForm.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;

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
    /* @var $entity EntityInterface */
    $entity = $transition->getEntity();

    // Compose Form Id from string + Entity Id + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form Id is unique, to allow for multiple forms per page.
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_id = $entity->id();
    $field_name = $transition->getFieldName();

    $form_id = implode('_', array('workflow_transition_form', $entity_type, $entity_bundle, $field_name, $entity_id));

//    workflow_debug(__FILE__, __FUNCTION__, __LINE__, $form_id);  // @todo D8-port: still test this snippet.

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
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This is called from submitForm().
   */
  public function save(array $form, FormStateInterface $form_state) {
    // $result = parent::save($form, $form_state);

    /*
     * Input
     */
    /*  @var $transition WorkflowTransitionInterface */
    $transition = $this->entity;

    /*
     * Process
     */
    // DO NOT call workflow_execute_transition(), since we might be saving a Scheduled Transition.
    // // Save the transition, Update the entity.
    // workflow_execute_transition($transition);

    // Save the (scheduled) transition.
    $result = $transition->execute();
    if ($result == $transition->getToSid()) {
      if (!$transition->isScheduled()) {
        // Update the entity.
        $result = $transition->updateEntity();
      }
    }
    else {
      // The transition was not allowed.
      // @todo: validateForm().
    }

    return $result;
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

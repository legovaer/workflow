<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Action\NextWorkflowState.
 */

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Plugin\Action\WorkflowStateActionBase;

/**
 * Sets an entity to the next state.
 *
 * The only change is the 'type' in tha Annotation, so it works on Nodes,
 * and can be seen on admin/content page.
 *
 * @Action(
 *   id = "workflow_node_next_state_action",
 *   label = @Translation("Change a node to next Workflow state"),
 *   type = "node"
 * )
 */
class NextNodeWorkflowStateAction extends WorkflowStateActionBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($field_name, $form_state);

    // Remove to_sid. User can't set it, since we want a dynamic 'next' state.
    unset($form['workflow']['workflow_to_sid']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    $entity = $object;

    // Add actual data.
    $transition = $this->getTransitionForExecution($entity);

    /*
     * Set the new next state.
     */
    $entity = $transition->getEntity();
    $field_name = $transition->getFieldName();
    $user = $transition->getOwner();
    // $comment = $transition->getComment();

    $current_state = $transition->getFromState();
    $current_sid = $current_state->id();

    // Get the node's new State Id (which is the next available state).
    $to_sid = $current_sid;
    $options = $current_state->getOptions($entity, $field_name, $user, FALSE);
    $flag = $current_state->isCreationState();
    foreach ($options as $sid => $name) {
      if ($flag) {
        $to_sid = $sid;
        break;
      }
      if ($sid == $current_sid) {
        $flag = TRUE;
      }
    }

    // Add actual data.
    $transition->to_sid = $to_sid;
//    $transition->setValues($entity, $field_name, $current_sid, $to_sid, $user->id(), REQUEST_TIME, $comment, TRUE);

//    $force = $this->configuration['workflow']['workflow_force'];
//    $transition->force();

    // Fire the transition.
    workflow_execute_transition($transition, $force);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(){
    return [
      'module' => array('workflow', 'node'),
    ];
  }

}

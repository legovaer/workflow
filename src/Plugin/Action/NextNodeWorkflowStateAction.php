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
    // Add actual data.
    $transition = $this->getTransitionForExecution($object);

    /*
     * Set the new next state.
     */
    $entity = $transition->getEntity();
    $field_name = $transition->getFieldName();
    $user = $transition->getOwner();
    // $comment = $transition->getComment();

    $current_state = $transition->getFromState();

    $force = FALSE;
//    $force = $this->configuration['workflow']['workflow_force'];

    $workflow = $transition->getWorkflow();
    // Get the node's new State Id (which is the next available state).
    $to_sid = $workflow->getNextSid($entity, $field_name, $user, $force);

    // Add actual data.
    $transition->to_sid = $to_sid;
//    $transition->setValues($entity, $field_name, $current_state->id(), $to_sid, $user->id(), REQUEST_TIME, $comment, TRUE);

//    $transition->force($force);

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

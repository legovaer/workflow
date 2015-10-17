<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Action\NextWorkflowState.
 */

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Sets an entity to the next state.
 *
 * @Action(
 *   id = "workflow_next_state_action",
 *   label = @Translation("Change to next Workflow state"),
 *   type = "workflow"
 * )
 */
class NextWorkflowState extends ActionBase {

  /**
   * Implements a Drupal action. Move a node to the next state in the workflow.
   *
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
//    $entity->sticky = NODE_STICKY;
//    $entity->save();

    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $user = workflow_current_user();

    if (!$entity) {
      return;
    }

    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    // In 'after saving new content', the node is already saved. Avoid second insert.
    // Todo: clone?
    $entity->enforceIsNew(FALSE);

    // Get the entity type and numeric ID.
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();

    if (!$entity_id) {
      \Drupal::logger('workflow_action')->notice('Unable to get current entity ID - entity is not yet saved.', []);
      return;
    }

    // Assume a one-workflow entity for this non-configurable action.
    $field_name = workflow_get_field_name('');
    $current_sid = workflow_node_current_state($entity, $field_name);
    if (!$current_sid) {
      \Drupal::logger('workflow_action')->notice('Unable to get current workflow state of entity %id.', array('%id' => $entity_id));
      return;
    }

    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // Get the node's new State Id (which is the next available state).
    /* @var $current_state \Drupal\workflow\Entity\WorkflowState */
    $current_state = WorkflowState::load($current_sid);
    $options = $current_state->getOptions($entity, $field_name, $user, FALSE);
    $new_sid = $current_sid;
    $flag = $current_state->isCreationState();
    foreach ($options as $sid => $name) {
      if ($flag) {
        $new_sid = $sid;
        break;
      }
      if ($sid == $current_sid) {
        $flag = TRUE;
      }
    }

    $force = FALSE;
    // Get the Comment. It is empty.
    $comment = '';

    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // Fire the transition.
    $transition = WorkflowTransition::create();
    $transition->setValues($entity, $field_name, $current_sid, $new_sid, $user->id(), REQUEST_TIME, $comment);
    workflow_execute_transition($transition, $force);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, \Drupal\Core\Session\AccountInterface $account = NULL, $return_as_object = FALSE) {
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    /** @var \Drupal\node\NodeInterface $object */
    $access = $object->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

}

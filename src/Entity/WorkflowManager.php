<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowManager.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Plugin\Condition\UserRole;
use Drupal\workflow\Entity\WorkflowConfigTransition;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Manages entity type plugin definitions.
 *
 */
class WorkflowManager { // extendds EntityManager {

  /**
   * Constructs a new Entity plugin manager.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  function executeScheduledTransitionsBetween($start = 0, $end = 0) {
    $clear_cache = FALSE;

    // If the time now is greater than the time to execute a transition, do it.
    foreach (WorkflowScheduledTransition::loadBetween($start, $end) as $scheduled_transition) {
      $field_name = $scheduled_transition->getFieldName();
      $entity = $scheduled_transition->getEntity();
      $entity_type = $entity->getEntityTypeId();

      // If user didn't give a comment, create one.
      $comment = $scheduled_transition->getComment();
      if (empty($comment)) {
        $scheduled_transition->addDefaultComment();
      }


      // Make sure transition is still valid: the node must still be in the state
      // it was in, when the transition was scheduled.
      $current_sid = workflow_node_current_state($entity, $field_name);
      if ($current_sid == $scheduled_transition->getFromSid()) {

        // Do transition. Force it because user who scheduled was checked.
        // The scheduled transition is not scheduled anymore, and is also deleted from DB.
        // A watchdog message is created with the result.
        workflow_execute_transition($scheduled_transition, TRUE);

        if (!$field_name) {
          $clear_cache = TRUE;
        }
      }
      else {
        // Node is not in the same state it was when the transition
        // was scheduled. Defer to the node's current state and
        // abandon the scheduled transition.
        $scheduled_transition->delete();
      }
    }

    if ($clear_cache) {
      // Clear the cache so that if the transition resulted in a node
      // being published, the anonymous user can see it.
      Cache::invalidateTags(array('rendered'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(AccountInterface $account) {
    dpm('TODO D8-port: test function workflow.module::' . __FUNCTION__ );
    self::cancelUser([], $account, 'user_cancel_delete');
  }

  /**
   * {@inheritdoc}
   */
  public function cancelUser($edit, AccountInterface $account, $method) {

    switch ($method) {
      case 'user_cancel_block': // Disable the account and keep its content.
      case 'user_cancel_block_unpublish': // Disable the account and unpublish its content.
        // Do nothing.
        break;
      case 'user_cancel_reassign': // Delete the account and make its content belong to the Anonymous user.
      case 'user_cancel_delete': // Delete the account and its content.

        // Update tables for deleted account, move account to user 0 (anon.)
        // ALERT: This may cause previously non-Anonymous posts to suddenly
        // be accessible to Anonymous.

        /**
         * Given a user id, re-assign history to the new user account. Called by user_delete().
         */
        $uid = $account->id();
        $new_uid = 0;

        db_update('workflow_transition_history')
          ->fields(array('uid' => $new_uid))
          ->condition('uid', $uid, '=')
          ->execute();
        db_update('workflow_transition_schedule')
          ->fields(array('uid' => $new_uid))
          ->condition('uid', $uid, '=')
          ->execute();

        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  function insertUserRole(Role $role) {
    user_role_change_permissions($role->id(), array('participate in workflow' => 1));
  }

}


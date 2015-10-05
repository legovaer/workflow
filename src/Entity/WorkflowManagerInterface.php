<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowManagerInterface.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;

/**
 * Provides an interface for workflow manager.
 *
 * Contains lost of functions from D7 workflow.module file.
 */
interface WorkflowManagerInterface {

  /**
   * Given a timeframe, execute all scheduled transitions.
   *
   * Implements hook_cron().
   *
   * @param int $start
   * @param int $end
   */
  public function executeScheduledTransitionsBetween($start = 0, $end = 0);

  /**
   * Hook-implementing functions.
   */

  /**
   * Implements hook_user_role_insert().
   *
   * Make sure new roles are allowed to participate in workflows by default.
   */
  function insertUserRole(Role $role);

  /**
   * Implements hook_user_delete().
   */
  public function deleteUser(AccountInterface $account);

    /**
   * Implements hook_user_cancel().
   * Implements deprecated workflow_update_workflow_transition_history_uid().
   *
   * " When cancelling the account
   * " - Disable the account and keep its content.
   * " - Disable the account and unpublish its content.
   * " - Delete the account and make its content belong to the Anonymous user.
   * " - Delete the account and its content.
   * "This action cannot be undone.
   */
  public function cancelUser($edit, AccountInterface $account, $method);

  /**
   * Helper functions.
   */

  /**
   * Gets the current state ID of a given entity.
   *
   * There is no need to use a page cache.
   * The performance is OK, and the cache gives problems when using Rules.
   *
   * @param EntityInterface $entity
   *   The entity to check. May be an EntityDrupalWrapper.
   * @param string $field_name
   *   The name of the field of the entity to check.
   *   If empty, the field_name is determined on the spot. This must be avoided,
   *   since it makes having multiple workflow per entity unpredictable.
   *   The found field_name will be returned in the param.
   *
   * @return string $sid
   *   The ID of the current state.
   */
  function getCurrentStateId(EntityInterface $entity, $field_name = '');

  /**
   * Gets the previous state ID of a given entity.
   *
   * @param EntityInterface $entity
   * @param string $field_name
   *
   * @return string $previous_sid
   */
  function getPreviousStateId(EntityInterface $entity, $field_name = '');


}

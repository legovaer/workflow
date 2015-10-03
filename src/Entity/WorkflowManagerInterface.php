<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowManagerInterface.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

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
   * Implements hook_user_role_insert().
   *
   * Make sure new roles are allowed to participate in workflows by default.
   */
  function insertUserRole($role);

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

}

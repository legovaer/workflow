<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowConfigTransitionInterface.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Session\AccountInterface;

/**
 * Defines a common interface for Workflow*Transition* objects.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 * @see \Drupal\workflow\Entity\WorkflowTransition
 * @see \Drupal\workflow\Entity\WorkflowScheduledTransition
 */
interface WorkflowConfigTransitionInterface {

  /**
   * Determines if the current transition between 2 states is allowed.
   *
   * - In settings;
   * - In permissions;
   * - By permission hooks, implemented by other modules.
   *
   * @param array $roles
   * @param \Drupal\Core\Session\AccountInterface|NULL $user
   * @param bool $force
   *
   * @return bool
   *   TRUE if OK, else FALSE.
   *
   *   Having both $roles AND $user seems redundant, but $roles have been
   *   tampered with, even though they belong to the $user.
   *
   * @see WorkflowConfigTransition::isAllowed()
   */
  public function isAllowed(array $roles, AccountInterface $user, $force = FALSE);

  /**
   * @return Workflow $workflow
   */
  public function getWorkflow();

  /**
   * Returns the Workflow ID of this Transition
   *
   * @return string
   *   Workflow Id.
   */
  public function getWorkflowId();

  /**
   * @return WorkflowState
   */
  public function getFromState();

  /**
   * @return WorkflowState
   */
  public function getToState();

  /**
   * @return string
   */
  public function getFromSid();

  /**
   * @return string
   */
  public function getToSid();

}

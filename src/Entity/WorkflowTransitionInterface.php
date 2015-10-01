<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowTransitionInterface.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Defines a common interface for Workflow*Transition* objects.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 * @see \Drupal\workflow\Entity\WorkflowTransition
 * @see \Drupal\workflow\Entity\WorkflowScheduledTransition
 */
interface WorkflowTransitionInterface extends WorkflowConfigTransitionInterface {

  /**
   * Helper function for __construct. Used for all children of WorkflowTransition (aka WorkflowScheduledTransition)
   */
  public function setValues($entity, $field_name, $from_sid, $to_sid, $uid = NULL, $timestamp = REQUEST_TIME, $comment = '');

  /**
   * Load WorkflowTransitions, most recent first.
   *
   * @return WorkflowTransitionInterface
   *   object representing one row from the {workflow_transition_history} table.
   */
  public static function loadByProperties($entity_type, $entity_id, array $revision_ids, $field_name, $langcode = '', $transition_type = '');

  /**
   * Given an entity, get all transitions for it.
   *
   * Since this may return a lot of data, a limit is included to allow for only one result.
   *
   * @param string $entity_type
   * @param int[] $entity_ids
   * @param int[] $revision_ids
   * @param string $field_name
   *   Optional. Can be NULL, if you want to load any field.
   * @param int $limit
   *   Optional. Can be NULL, if you want to load all transitions.
   * @param string $langcode
   *   Optional. Can be empty, if you want to load any language.
   * @param string $transition_type
   *   The type trnastion to be fetched.
   *
   * @return WorkflowTransitionInterface[] $transitions
   *   An array of transitions.
   */
  public static function loadMultipleByProperties($entity_type, array $entity_ids, array $revision_ids, $field_name, $limit = NULL, $langcode = '', $transition_type = '');

    /**
   * Execute a transition (change state of an entity).
   *
   * @param bool $force
   *   If set to TRUE, workflow permissions will be ignored.
   *
   * @return $sid
   *   New state ID. If execution failed, old state ID is returned,
   */
  public function execute($force = FALSE);

  /**
   * Invokes 'transition post'.
   *
   * Add the possibility to invoke the hook from elsewhere.
   */
  public function post_execute($force = FALSE);

  /**
   * Get the Entity, that is added to the Transition.
   *
   * @return EntityInterface
   *   The entity, that is added to the Transition.
   */
  public function getEntity();

  /**
   * Set the Entity, that is added to the Transition.
   * Also set all dependent fields, that will be saved in tables {workflow_transition_*}
   *
   * @param EntityInterface $entity
   *   The Entity ID or the Entity object, to add to the Transition.
   *
   * @return object $entity
   *   The Entity, that is added to the Transition.
   */
  public function setEntity($entity);

  /**
   * Get the field_name for which the Transition is valid.
   *
   * @return string $field_name
   *   The field_name, that is added to the Transition.
   */
  public function getFieldName();

  /**
   * Get the language code for which the Transition is valid.
   *
   * @return string $langcode
   */
  public function getLangcode();

  /**
   * Set the User Id.
   *
   * @param int $uid
   *
   * @return WorkflowTransitionInterface
   */
  public function setUserId($uid);

  /**
   * Set the User.
   *
   * @param AccountInterface $account
   *
   * @return WorkflowTransitionInterface
   */
  public function setUser(AccountInterface $account);

  /**
   * Get the user.
   *
   * @return \Drupal\Core\Session\AccountInterface $user
   *   The entity, that is added to the Transition.
   */

  /**
   * Get the comment of the Transition.
   *
   * @return
   *   The comment
   */
  public function getComment();

  /**
   * Get the comment of the Transition.
   *
   * @param $value
   *   The new comment.
   *
   * @return WorkflowTransitionInterface
   */
  public function setComment($value);

  /**
   * Returns the time on which the transitions was or will be executed.
   *
   * @return
   */
  public function getTimestamp();

  /**
   * Returns the time on which the transitions was or will be executed.
   *
   * @param $value
   *   The new timestamp.
   * @return WorkflowTransitionInterface
   */
  public function setTimestamp($value);

  /**
   * Returns if this is a Scheduled Transition.
   */
  public function isScheduled();
  public function schedule($schedule = TRUE);
  public function isExecuted();

  /**
   * A transition may be forced skipping checks.
   *
   * @return bool
   *  If the transition is forced. (Allow not-configured transitions).
   */
  public function isForced();
  public function force($force = TRUE);


}

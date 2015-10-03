<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowConfigTransition.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\ConfigEntityStorage;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_config_transition",
 *   label = @Translation("Workflow config transition"),
 *   module = "workflow",
 *   handlers = {
 *     "list_builder" = "Drupal\workflow_ui\Controller\WorkflowConfigTransitionListBuilder",
 *     "form" = {
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *      }
 *   },
 *   admin_permission = "administer workflow",
 *   config_prefix = "workflow_config_transition",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "module",
 *     "from_sid",
 *     "to_sid",
 *     "roles",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/workflow/{workflow_workflow}/transitions",
 *   }
 * )
 */
class WorkflowConfigTransition extends ConfigEntityBase {

  // Transition data.
  public $id;
  public $from_sid;
  public $to_sid;
  public $roles = array();

  // Extra fields.
  public $wid;
  // The following must explicitely defined, and not be public, to avoid errors when exporting with json_encode().
  protected $workflow = NULL;

  /**
   * Entity class functions.
   */

  public function __construct(array $values = array(), $entityType = NULL) {
    // Please be aware that $entity_type and $entityType are different things!
    $result = parent::__construct($values, $entityType = 'workflow_config_transition');

    return $result;
  }

  /**
   * Helper function for __construct. Used for all children of WorkflowTransition (aka WorkflowScheduledTransition)
   */
  public function setValues($from_sid, $to_sid) {
    $this->from_sid = $from_sid;
    $this->to_sid = $to_sid;
  }

  /**
   * {@inheritdoc}
   *
   * @return static[]
   *   An array of entity objects indexed by their IDs. Filtered by $wid.
   */
  public static function loadMultiple(array $ids = NULL, $wid = '') {
    return parent::loadMultiple($ids);
    //TODO D8-port: filter for $wid.
  }

  public function save() {
    $workflow = $this->getWorkflow();

    // To avoid double posting, check if this (new) transition already exist.
    if (empty($this->id())) {
      if ($workflow) {
        $config_transitions = $workflow->getTransitionsByStateId($this->from_sid, $this->to_sid);
        $config_transition = reset($config_transitions);
        if ($config_transition) {
          $this->set('id', $config_transition->id());
        }
      }
    }

    // Create the machine_name. This can be used to rebuild/revert the Feature in a target system.
    if (empty($this->id())) {
      $this->set('id', implode('_', [$workflow->id(), $this->from_sid, $this->to_sid]));
    }

//    dpm('TODO D8-port: test function WorkflowConfigTransition::' . __FUNCTION__ .' '. $this->id());

    $status = parent::save();

    if ($status) {
      // Save in current workflow for the remainder of this page request.
      // Keep in sync with Workflow::getTransitions() !
      if ($workflow) {
        $workflow->transitions[$this->id()] = $this;
        // $workflow->sortTransitions();
      }
    }

    return $status;
  }

  /**
   * Permanently deletes the entity.
   */
  public function delete() {
    // Notify any interested modules before we delete, in case there's data needed.
    // @todo D8: this can be replaced by a hook_entity_delete(?)
    \Drupal::moduleHandler()->invokeAll('workflow', ['transition delete', $this->id(), NULL, NULL, FALSE]);

    return parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().

    // First sort on From-State.
    $from_state_a = $a->getFromState();
    $from_state_b = $b->getFromState();
    if ($from_state_a->weight < $from_state_b->weight) return -1;
    if ($from_state_a->weight > $from_state_b->weight) return +1;

    // Then sort on To-State.
    $to_state_a = $a->getToState();
    $to_state_b = $b->getToState();
    if ($to_state_a->weight < $to_state_b->weight) return -1;
    if ($to_state_a->weight > $to_state_b->weight) return +1;

    return 0;
  }

  /**
   * Property functions.
   */

  /**
   * Returns the Workflow object of this State.
   *
   * @param Workflow $workflow
   *   An optional workflow object. Can be used as a setter.
   *
   * @return Workflow
   *   Workflow object.
   */
  public function setWorkflow($workflow) {
    $this->wid = $workflow->id();
    $this->workflow = $workflow;
  }

  public function getWorkflow() {
    if (isset($this->workflow)) {
      return $this->workflow;
    }
    return Workflow::load($this->wid);
  }

  /**
   * {@inheritdoc}
   */
  public function getFromState() {
    return WorkflowState::load($this->from_sid);
  }

  /**
   * {@inheritdoc}
   */
  public function getToState() {
    return WorkflowState::load($this->to_sid);
  }

  /**
   * {@inheritdoc}
   */
  public function getFromSid() {
    return $this->from_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getToSid() {
    return $this->to_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(array $user_roles, AccountInterface $user = NULL, $force = FALSE) {
//    dpm('TODO D8-port: test function WorkflowConfigTransition::' . __FUNCTION__ );
// TODO D8: add usage of api user_has_role().
    /*
  $role = user_role_load('Author');
  if ($role && user_has_role($role->rid)) {
   // Code if user has 'Author' role...
  }
  else {
   // Code if user doesn't have 'Author' role...
  }
    */

    if ($user_roles == 'ALL') {
      // Superuser.
      return TRUE;
    }
    elseif ($user_roles) {
      return array_intersect($user_roles, $this->roles) == TRUE;
    }
    return TRUE;
  }

}

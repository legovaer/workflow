<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowConfigTransition.
 */

/**
 * Implements a configurated Transition.
 */
class WorkflowConfigTransition extends Entity {

  // Transition data.
  public $tid = 0;
  // public $old_sid = 0;
  // public $new_sid = 0;
  public $sid = 0; // @todo D8: remove $sid, use $new_sid. (requires conversion of Views displays.)
  public $target_sid = 0;
  public $roles = array();

  // Extra fields.
  public $wid = 0;
  // The following must explicitely defined, and not be public, to avoid errors when exporting with json_encode().
  protected $workflow = NULL;

  /**
   * Entity class functions.
   */

/*
  // Implementing clone needs a list of tid-less transitions, and a conversion
  // of sids for both States and ConfigTransitions.
  // public function __clone() {}
 */

  public function __construct(array $values = array(), $entityType = NULL) {
    // Please be aware that $entity_type and $entityType are different things!
    return parent::__construct($values, $entityType = 'WorkflowConfigTransition');
  }

  /**
   * Permanently deletes the entity.
   */
  public function delete() {
    // Notify any interested modules before we delete, in case there's data needed.
    // @todo D8: this can be replaced by a hook_entity_delete(?)
    module_invoke_all('workflow', 'transition delete', $this->tid, NULL, NULL, FALSE);

    return parent::delete();
  }

  protected function defaultLabel() {
    return $this->label;
  }

  protected function defaultUri() {
    return array('path' => 'admin/config/workflow/workflow/manage/' . $this->wid . '/transitions/');
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
    $this->wid = $workflow->wid;
    $this->workflow = $workflow;
  }

  public function getWorkflow() {
    if (isset($this->workflow)) {
      return $this->workflow;
    }
    return workflow_load_single($this->wid);
  }
  public function getOldState() {
    return workflow_state_load_single($this->sid);
  }
  public function getNewState() {
    return workflow_state_load_single($this->target_sid);
  }

  /**
   * Verifies if the given transition is allowed.
   *
   * - In settings;
   * - In permissions;
   * - By permission hooks, implemented by other modules.
   *
   * @return bool
   *   TRUE if OK, else FALSE.
   */
  public function isAllowed($user_roles) {
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

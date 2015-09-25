<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowConfigTransition.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\workflow\Entity\ConfigEntityStorage;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_config_transition",
 *   label = @Translation("Workflow config transition"),
 *   module = "workflow",
 *   handlers = {
 *     "list_builder" = "Drupal\workflow\Entity\Controller\WorkflowConfigTransitionListBuilder",
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
 *     "sid",
 *     "target_sid",
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
  // public $old_sid = 0;
  // public $new_sid = 0;
  public $sid; // @todo D8: remove $sid, use $new_sid. (requires conversion of Views displays.)
  public $target_sid;
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
        $config_transitions = $workflow->getTransitionsBySidTargetSid($this->sid, $this->target_sid);
        $config_transition = reset($config_transitions);
        if ($config_transition) {
          $this->set('id', $config_transition->id());
        }
      }
    }

    // Create the machine_name. This can be used to rebuild/revert the Feature in a target system.
    if (empty($this->id())) {
      $this->set('id', implode('_', [$workflow->id(), $this->sid, $this->target_sid]));
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

    // Reset the cache for the affected workflow, to force reload upon next page_load.
    workflow_reset_cache($this->wid);

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
    $old_state_a = $a->getOldState();
    $old_state_b = $b->getOldState();
    if ($old_state_a->weight < $old_state_b->weight) return -1;
    if ($old_state_a->weight > $old_state_b->weight) return +1;

    // Then sort on To-State.
    $new_state_a = $a->getNewState();
    $new_state_b = $b->getNewState();
    if ($new_state_a->weight < $new_state_b->weight) return -1;
    if ($new_state_a->weight > $new_state_b->weight) return +1;

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

  public function getOldState() {
    return WorkflowState::load($this->sid);
  }

  public function getNewState() {
    return WorkflowState::load($this->target_sid);
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

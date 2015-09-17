<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\Workflow.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_workflow",
 *   label = @Translation("Workflow"),
 *   handlers = {
 *     "storage" = "Drupal\workflow\Entity\WorkflowStorage",
 *     "list_builder" = "Drupal\workflow\Entity\Controller\WorkflowListBuilder",
 *     "form" = {
 *        "add" = "\Drupal\workflow\Entity\WorkflowForm",
 *        "edit" = "\Drupal\workflow\Entity\WorkflowForm",
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm"
 *      }
 *   },
 *   admin_permission = "administer workflow",
 *   config_prefix = "workflow",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "module",
 *     "tab_roles",
 *     "options",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/workflow",
 *     "edit-form" = "/admin/config/workflow/workflow/{workflow_workflow}/edit",
 *     "delete-form" = "/admin/config/workflow/workflow/{workflow_workflow}/delete"
 *   }
 * )
 */
class Workflow extends ConfigEntityBase {

  /**
   * The machine name.
   *
   * @var string
   */
  public $id;

  /**
   * The human readable name.
   *
   * @var string
   */
  public $label;

// TODO D8-port: remove below variables wid , name.
//  public $wid = 0; // This is not an INT anymore, but a string
//  public $name = ''; // This is dientical to the ID

// TODO D8-port: complete below variables. (Add get()-functions).
// @see https://www.drupal.org/node/1809494
// @see https://codedrop.com.au/blog/creating-custom-config-entities-drupal-8
  public $tab_roles = array();
  public $options = array();
  protected $creation_sid = 0;

  // Attached States and Transitions.
  public $states = array();
  public $transitions = array();

  /**
   * CRUD functions.
   */

  public function __clone() {
    // TODO D8-port: test below function.

    // Clone the arrays of States and Transitions.
    foreach ($this->states as &$state) {
      $state = clone $state;
    }
    foreach ($this->transitions as &$transition) {
      $transition = clone $transition;
    }
  }

  /**
   * Given information, update or insert a new workflow.
   *
   * This also handles importing, rebuilding, reverting from Features,
   * as defined in workflow.features.inc.
   * todo: reverting does not refresh States and transitions, since no
   * machine_name was present. As of 7.x-2.3, the machine_name exists in
   * Workflow and WorkflowConfigTransition, so rebuilding is possible.
   *
   * When changing this function, test with the following situations:
   * - maintain Workflow in Admin UI;
   * - clone Workflow in Admin UI;
   * - create/revert/rebuild Workflow with Features; @see workflow.features.inc
   * - save Workflow programmatically;
   */
  public function save($create_creation_state = TRUE) {
    $return = parent::save();

    return $return;
    // TODO D8-port: add all of the below: save() function.

    // Are we saving a new Workflow?
    $is_new = !empty($this->is_new);
    // Are we rebuilding, reverting a new Workflow? @see workflow.features.inc
    $is_rebuild = !empty($this->is_rebuild);
    $is_reverted = !empty($this->is_reverted);

    // If rebuild by Features, make some conversions.
    if (!$is_rebuild && !$is_reverted) {
      // Avoid troubles with features clone/revert/..
      unset($this->module);
    }
    else {
      $role_map = isset($this->system_roles) ? $this->system_roles : array();
      if ($role_map) {
        // Remap roles. They can come from another system with shifted role IDs.
        // See also https://drupal.org/node/1702626 .
        $this->tab_roles = _workflow_rebuild_roles($this->tab_roles, $role_map);
        foreach ($this->transitions as &$transition) {
          $transition['roles'] = _workflow_rebuild_roles($transition['roles'], $role_map);
        }
      }

      // Insert the type_map when building from Features.
      if ($this->typeMap) {
        foreach ($this->typeMap as $node_type) {
          workflow_insert_workflow_type_map($node_type, $this->id());
        }
      }
    }
    // After update.php or import feature, label might be empty. @todo: remove in D8.
    if (empty($this->label)) {
      $this->label = $this->name;
    }

    $return = parent::save();

    // If a workflow is cloned in Admin UI, it contains data from original workflow.
    // Redetermine the keys.
    if (($is_new) && $this->states) {
      foreach ($this->states as $state) {
        // Can be array when cloning or with features.
        $state = is_array($state) ? new WorkflowState($state) : $state;
        // Set up a conversion table, while saving the states.
        $old_sid = $state->sid;
        $state->wid = $this->id();
        // @todo: setting sid to FALSE should be done by entity_ui_clone_entity().
        $state->sid = FALSE;
        $state->save();
        $sid_conversion[$old_sid] = $state->sid;
      }

      // Reset state cache.
      $this->getStates(TRUE, TRUE);
      foreach ($this->transitions as &$transition) {
        // Can be array when cloning or with features.
        $transition = is_array($transition) ? new WorkflowConfigTransition($transition, 'WorkflowConfigTransition') : $transition;
        // Convert the old sids of each transitions before saving.
        // @todo: is this be done in 'clone $transition'?
        // (That requires a list of transitions without tid and a wid-less conversion table.)
        if (isset($sid_conversion[$transition->sid])) {
          $transition->tid = FALSE;
          $transition->sid = $sid_conversion[$transition->sid];
          $transition->target_sid = $sid_conversion[$transition->target_sid];
          $transition->save();
        }
      }
    }

    // Make sure a Creation state exists.
    if ($is_new) {
      $state = $this->getCreationState();
    }

    workflow_reset_cache($this->id());

    return $return;
  }

  /**
   * Given a wid, delete the workflow and its data.
   */
  public function delete() {
    // TODO D8-port: test below function.
    $wid = $this->id();

    // Notify any interested modules before we delete the workflow.
    // E.g., Workflow Node deletes the {workflow_type_map} record.
    \Drupal::moduleHandler()->invokeAll('workflow', ['workflow delete', $wid, NULL, NULL, FALSE]);

    // Delete associated state (also deletes any associated transitions).
    foreach ($this->getStates($all = TRUE) as $state) {
      $state->deactivate(0);
      $state->delete();
    }

    // Delete the workflow.
    return parent::delete();
  }

  /**
   * Validate the workflow. Generate a message if not correct.
   *
   * This function is used on the settings page of:
   * - Workflow node: workflow_admin_ui_type_map_form()
   * - Workflow field: WorkflowItem->settingsForm()
   *
   * @return bool
   *   $is_valid
   */
  public function isValid() {
    // TODO D8-port: test below function.
    $is_valid = TRUE;

    // Don't allow workflows with no states. There should always be a creation state.
    $states = $this->getStates($all = FALSE);
    if (count($states) < 1) {
      // That's all, so let's remind them to create some states.
      $message = t('Workflow %workflow has no states defined, so it cannot be assigned to content yet.',
        array('%workflow' => $this->getName()));
      drupal_set_message($message, 'warning');

      // Skip allowing this workflow.
      $is_valid = FALSE;
    }

    // Also check for transitions, at least out of the creation state. Use 'ALL' role.
    $transitions = $this->getTransitionsBySid($this->getCreationSid(), $roles = 'ALL');
    if (count($transitions) < 1) {
      // That's all, so let's remind them to create some transitions.
      $message = t('Workflow %workflow has no transitions defined, so it cannot be assigned to content yet.',
        array('%workflow' => $this->getName()));
      drupal_set_message($message, 'warning');

      // Skip allowing this workflow.
      $is_valid = FALSE;
    }

    return $is_valid;
  }

  /**
   * Returns if the Workflow may be deleted.
   *
   * @return bool $is_deletable
   *   TRUE if a Workflow may safely be deleted.
   */
  public function isDeletable() {
    // TODO D8-port: test below function.

    $is_deletable = FALSE;

    // May not be deleted if a TypeMap exists.
    if ($this->getTypeMap()) {
      return $is_deletable;
    }

    // May not be deleted if assigned to a Field.
    foreach (_workflow_info_fields() as $field) {
      if ($field['settings']['wid'] == $this->id()) {
        return $is_deletable;
      }
    }

    // May not be deleted if a State is assigned to a state.
    foreach ($this->getStates(TRUE) as $state) {
      if ($state->count()) {
        return $is_deletable;
      }
    }
    $is_deletable = TRUE;
    return $is_deletable;
  }

  /**
   * Property functions.
   */

  /**
   * Create a new state for this workflow.
   *
   * @param string $name
   *   The untranslated human readable label of the state.
   * @param bool $save
   *   Indicator if the new state must be saved. Normally, the new State is
   *   saved directly in the database. This is because you can use States only
   *   with Transitions, and they rely on State IDs which are generated
   *   magically when saving the State. But you may need a temporary state.
   */
  public function createState($name, $save = TRUE) {
    // TODO D8-port: test below function.

    $wid = $this->id();
    $state = workflow_state_load_by_name($name, $wid);
    if (!$state) {
      $state = \Drupal::entityManager()->getStorage('WorkflowState')->create(array('name' => $name, 'state' => $name, 'wid' => $wid));
      if ($save) {
        $state->save();
      }
    }
    $state->setWorkflow($this);
    // Maintain the new object in the workflow.
    $this->states[$state->sid] = $state;

    return $state;
  }

  /**
   * Gets the initial state for a newly created entity.
   */
  public function getCreationState() {
    // TODO D8-port: test below function.

    $sid = $this->getCreationSid();
    return ($sid) ? $this->getState($sid) : $this->createState(WORKFLOW_CREATION_STATE_NAME);
  }

  /**
   * Gets the ID of the initial state for a newly created entity.
   */
  public function getCreationSid() {
    // TODO D8-port: test below function.

    if (!$this->creation_sid) {
      foreach ($this->getStates($all = TRUE) as $state) {
        if ($state->isCreationState()) {
          $this->creation_sid = $state->sid;
        }
      }
    }
    return $this->creation_sid;
  }

  /**
   * Gets the first valid state ID, after the creation state.
   *
   * Uses WorkflowState::getOptions(), because this does a access check.
   * The first State ID is user-dependent!
   */
  public function getFirstSid($entity_type, $entity, $field_name, $user, $force) {
    // TODO D8-port: test below function.

    $creation_state = $this->getCreationState();
    $options = $creation_state->getOptions($entity_type, $entity, $field_name, $user, $force);
    if ($options) {
      $keys = array_keys($options);
      $sid = $keys[0];
    }
    else {
      // This should never happen, but it did during testing.
      drupal_set_message(t('There are no workflow states available. Please notify your site administrator.'), 'error');
      $sid = 0;
    }
    return $sid;
  }

  /**
   * Gets all states for a given workflow.
   *
   * @param mixed $all
   *   Indicates to which states to return.
   *   - TRUE = all, including Creation and Inactive;
   *   - FALSE = only Active states, not Creation;
   *   - 'CREATION' = only Active states, including Creation.
   *
   * @return array
   *   An array of WorkflowState objects.
   */
  public function getStates($all = FALSE, $reset = FALSE) {
    // TODO D8-port: test below function.

    if ($this->states === NULL || $reset) {
      $wid = $this->id();
      $this->states = $wid ? WorkflowState::getStates($wid, $reset) : array();
    }
    // Do not unset, but add to array - you'll remove global objects otherwise.
    $states = array();
    foreach ($this->states as $state) {
      if ($all === TRUE) {
        $states[$state->sid] = $state;
      }
      elseif (($all === FALSE) && ($state->isActive() && !$state->isCreationState())) {
        $states[$state->sid] = $state;
      }
      elseif (($all == 'CREATION') && ($state->isActive() || $state->isCreationState())) {
        $states[$state->sid] = $state;
      }
    }
    return $states;
  }

  /**
   * Gets a state for a given workflow.
   *
   * @param mixed $key
   *   A state ID or state Name.
   *
   * @return WorkflowState
   *   A WorkflowState object.
   */
  public function getState($key) {
    // TODO D8-port: test below function.

    $wid = $this->id();
    if (is_numeric($key)) {
      return workflow_state_load_single($key, $wid);
    }
    else {
      return workflow_state_load_by_name($key, $wid);
    }
  }

  /**
   * Creates a Transition for this workflow.
   */
  public function createTransition($sid, $target_sid, $values = array()) {
    // TODO D8-port: test below function. Remove wid.

    $workflow = $this;
    if (is_numeric($sid) && is_numeric($target_sid)) {
      $values['sid'] = $sid;
      $values['target_sid'] = $target_sid;
    }
    else {
      $state = $workflow->getState($sid);
      $target_state = $workflow->getState($target_sid);
      $values['sid'] = $state->sid;
      $values['target_sid'] = $target_state->sid;
    }

    // First check if this transition already exists.
    if ($transitions = // @FIXME
// To reset the entity cache, use EntityStorageInterface::resetCache().
\Drupal::entityManager()->getStorage('WorkflowConfigTransition')->loadByProperties($values)) {
      $transition = reset($transitions);
    }
    else {
      $values['wid'] = $workflow->wid;
      $transition = \Drupal::entityManager()->getStorage('WorkflowConfigTransition')->create($values);
      $transition->save();
    }
    $transition->setWorkflow($this);
    // Maintain the new object in the workflow.
    $this->transitions[$transition->tid] = $transition;

    return $transition;
  }

  /**
   * Sorts all Transitions for this workflow, according to State weight.
   *
   * This is only needed for the Admin UI.
   */
  public function sortTransitions() {
    // TODO D8-port: test below function.

    // Sort the transitions on state weight.
    usort($this->transitions, '_workflow_transitions_sort_by_weight');
  }

  /**
   * Loads all allowed ConfigTransitions for this workflow.
   *
   * @param mixed $tids
   *   Array of Transitions IDs. If FALSE, show all transitions.
   * @param array $conditions
   *   $conditions['sid'] : if provided, a 'from' State ID.
   *   $conditions['target_sid'] : if provided, a 'to' state ID.
   *   $conditions['roles'] : if provided, an array of roles, or 'ALL'.
   */
  public function getTransitions($tids = FALSE, array $conditions = array(), $reset = FALSE) {
    // TODO D8-port: test below function.

    $config_transitions = array();

    // Get valid + creation states.
    $states = $this->getStates('CREATION');

    // Get filters on 'from' states, 'to' states, roles.
    $sid = isset($conditions['sid']) ? $conditions['sid'] : FALSE;
    $target_sid = isset($conditions['target_sid']) ? $conditions['target_sid'] : FALSE;
    $roles = isset($conditions['roles']) ? $conditions['roles'] : 'ALL';

    // Cache all transitions in the workflow.
    // We may have 0 transitions....
    if ($this->transitions === NULL) {
      $this->transitions = array();
      // Get all transitions. (Even from other workflows. :-( )
      $config_transitions = \Drupal::entityManager()->getStorage('WorkflowConfigTransition')->loadByProperties(array());
      foreach ($config_transitions as &$config_transition) {
        if (isset($states[$config_transition->sid])) {
          $config_transition->setWorkflow($this);
          $this->transitions[$config_transition->tid] = $config_transition;
        }
      }
      $this->sortTransitions();
    }

    $config_transitions = array();
    foreach ($this->transitions as &$config_transition) {
      if (!isset($states[$config_transition->sid])) {
        // Not a valid transition for this workflow.
      }
      elseif ($sid && $sid != $config_transition->sid) {
        // Not the requested 'from' state.
      }
      elseif ($target_sid && $target_sid != $config_transition->target_sid) {
        // Not the requested 'to' state.
      }
      elseif ($config_transition->isAllowed($roles)) {
        // Transition is allowed, permitted. Add to list.
        $config_transition->setWorkflow($this);
        $config_transitions[$config_transition->tid] = $config_transition;
      }
      else {
        // Transition is otherwise not allowed.
      }
    }

    return $config_transitions;
  }

  public function getTransitionsByTid($tid, $roles = '', $reset = FALSE) {
    // TODO D8-port: test below function.

    $conditions = array(
      'roles' => $roles,
    );
    return $this->getTransitions(array($tid), $conditions, $reset);
  }

  public function getTransitionsBySid($sid, $roles = '', $reset = FALSE) {
    // TODO D8-port: test below function.

    $conditions = array(
      'sid' => $sid,
      'roles' => $roles,
    );
    return $this->getTransitions(FALSE, $conditions, $reset);
  }

  public function getTransitionsByTargetSid($target_sid, $roles = '', $reset = FALSE) {
    // TODO D8-port: test below function.

    $conditions = array(
      'target_sid' => $target_sid,
      'roles' => $roles,
    );
    return $this->getTransitions(FALSE, $conditions, $reset);
  }

  /**
   * Get a specific transition. Therefore, use $roles = 'ALL'.
   */
  public function getTransitionsBySidTargetSid($sid, $target_sid, $roles = 'ALL', $reset = FALSE) {
    // TODO D8-port: test below function.

    $conditions = array(
      'sid' => $sid,
      'target_sid' => $target_sid,
      'roles' => $roles,
    );
    return $this->getTransitions(FALSE, $conditions, $reset);
  }

  /**
   * Gets a the type map for a given workflow.
   *
   * @param int $sid
   *   A state ID.
   *
   * @return array
   *   An array of typemaps.
   */
  public function getTypeMap() {
    // TODO D8-port: test below function.

    $result = array();

    $type_maps = \Drupal::moduleHandler()->moduleExists('workflownode') ? workflow_get_workflow_type_map_by_wid($this->id()) : array();
    foreach ($type_maps as $map) {
      $result[] = $map->type;
    }

    return $result;
  }

  /**
   * Gets a setting from the state object.
   */
  public function getSetting($key, array $field = array()) {
    // TODO D8-port: test below function.

    switch ($key) {
      case 'watchdog_log':
        if (isset($this->options['watchdog_log'])) {
          // This is set via Node API.
          return $this->options['watchdog_log'];
        }
        elseif ($field) {
          if (isset($field['settings']['watchdog_log'])) {
            // This is set via Field API.
            return $field['settings']['watchdog_log'];
          }
        }
        drupal_set_message('Setting Workflow::getSetting(' . $key . ') does not exist', 'error');
        break;

      default:
        drupal_set_message('Setting Workflow::getSetting(' . $key . ') does not exist', 'error');
    }
  }

}

function _workflow_rebuild_roles(array $roles, array $role_map) {
  // TODO D8-port: test below function.

  // See also https://drupal.org/node/1702626 .
  $new_roles = array();
  foreach ($roles as $key => $rid) {
    if ($rid == -1) {
      $new_roles[$rid] = $rid;
    }
    else {
      if ($role = user_role_load_by_name($role_map[$rid])) {
        $new_roles[$role->rid] = $role->rid;
      }
    }
  }
  return $new_roles;
}

/**
 * Helper function to sort the transitions.
 *
 * @param WorkflowConfigTransition $a
 * @param WorkflowConfigTransition $b
 */
function _workflow_transitions_sort_by_weight($a, $b) {
  // TODO D8-port: test below function.

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

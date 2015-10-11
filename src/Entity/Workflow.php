<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\Workflow.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_workflow",
 *   label = @Translation("Workflow"),
 *   module = "workflow",
 *   handlers = {
 *     "storage" = "Drupal\workflow\Entity\WorkflowStorage",
 *     "list_builder" = "Drupal\workflow_ui\Controller\WorkflowListBuilder",
 *     "form" = {
 *        "add" = "Drupal\workflow_ui\Form\WorkflowForm",
 *        "edit" = "Drupal\workflow_ui\Form\WorkflowForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
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
 *     "options",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/workflow",
 *     "edit-form" = "/admin/config/workflow/workflow/{workflow_workflow}/edit",
 *     "delete-form" = "/admin/config/workflow/workflow/{workflow_workflow}/delete",
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

// TODO D8-port Workflow: complete below variables. (Add get()-functions).
// @see https://www.drupal.org/node/1809494
// @see https://codedrop.com.au/blog/creating-custom-config-entities-drupal-8
  public $options = array();

  /**
   * The workflow-specific creation state.
   *
   */
  private $creation_state;
  private $creation_sid = 0;

  // Attached States and Transitions.
  public $states = array();
  public $transitions = array();

  /**
   * CRUD functions.
   */

  /**
   * Given information, update or insert a new workflow.
   *
   * This also handles importing, rebuilding, reverting from Features,
   * as defined in workflow.features.inc.
   * TODO D8: clean up this function, since we are config entity now.
   * todo D7: reverting does not refresh States and transitions, since no
   * machine_name was present. As of 7.x-2.3, the machine_name exists in
   * Workflow and WorkflowConfigTransition, so rebuilding is possible.
   *
   * When changing this function, test with the following situations:
   * - maintain Workflow in Admin UI;
   * - clone Workflow in Admin UI;
   * - create/revert/rebuild Workflow with Features; @see workflow.features.inc
   * - save Workflow programmatic;
   *
   * @inheritdoc
   */

  public function save() {
    $status = parent::save();
    // Are we saving a new Workflow?
    // Make sure a Creation state exists.
    if ($status == SAVED_NEW) {
      $state = $this->getCreationState();
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   *
   * @return Workflow|null
   *   The entity object or NULL if there is no entity with the given ID.
   */
  public static function load($id) {
    $entity = parent::load($id);

    if ($entity) {
      // Load the states, so they are already present on the next (cached) load.
      // N.B. commented out, since it doesn't work. Workflow is loaded 4x per
      // Node form. Use lazy loading.
      // $entity->states = $entity->getStates($all = TRUE);
      // $entity->transitions = $entity->getTransitions(NULL);
    }
    return $entity;
  }

  /**
   * Given a wid, delete the workflow and its data.
   */
  public function delete() {
    $wid = $this->id();

    if (!$this->isDeletable()) {
      // @todo: throw error if not workflow->isDeletable().
    }
    else {
      // Delete associated state (also deletes any associated transitions).
      foreach ($this->getStates($all = TRUE) as $state) {
        $state->deactivate('');
        $state->delete();
      }

      // Delete the workflow.
      parent::delete();
    }
  }

  /**
   * Validate the workflow. Generate a message if not correct.
   *
   * This function is used on the settings page of:
   * - Workflow field: WorkflowItem->settingsForm()
   *
   * @return bool
   *   $is_valid
   */
  public function isValid() {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $is_valid = TRUE;

    // Don't allow Workflow without states. There should always be a creation state.
    $states = $this->getStates($all = FALSE);
    if (count($states) < 1) {
      // That's all, so let's remind them to create some states.
      $message = t('Workflow %workflow has no states defined, so it cannot be assigned to content yet.',
        array('%workflow' => $this->label()));
      drupal_set_message($message, 'warning');

      // Skip allowing this workflow.
      $is_valid = FALSE;
    }

    // Also check for transitions, at least out of the creation state. Don't filter for roles.
    $transitions = $this->getTransitionsByStateId($this->getCreationSid(), '');
    if (count($transitions) < 1) {
      // That's all, so let's remind them to create some transitions.
      $message = t('Workflow %workflow has no transitions defined, so it cannot be assigned to content yet.',
        array('%workflow' => $this->label()));
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
    $is_deletable = FALSE;

    // May not be deleted if assigned to a Field.
    foreach ($fields = _workflow_info_fields() as $field_info) {
      if ($field_info->getSetting('workflow_type') == $this->id()) {
        return FALSE;
      }
    }

    // D8-port: This is deleted, since it is only for D7's workflow_node.
    // // May not be deleted if a State is assigned to a state.
    // foreach ($this->getStates(TRUE) as $state) {
    //   if ($state->count()) {
    //     return $is_deletable;
    //   }
    // }
    $is_deletable = TRUE;

    return $is_deletable;
  }

  /**
   * Retrieves the entity manager service.
   *
   * @return \Drupal\workflow\Entity\WorkflowManagerInterface
   *   The entity manager service.
   */
  public static function workflowManager() {
    return new WorkflowManager();
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
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The new state.
   */
  public function createState($sid, $save = TRUE) {
    $wid = $this->id();
    $state = WorkflowState::load($sid, $wid);
    if (!$state) {
      $state = WorkflowState::create($values = array('id' => $sid, 'wid' => $wid));
      if ($save) {
        $status = $state->save();
      }
    }

    // Maintain the new object in the workflow.
    $this->states[$state->id()] = $state;

    return $state;
  }

  /**
   * Gets the initial state for a newly created entity.
   */
  public function getCreationState() {
    // First, find it.
    if (!$this->creation_state) {
      foreach ($this->getStates($all = TRUE) as $state) {
        if ($state->isCreationState()) {
          $this->creation_state = $state;
          $this->creation_sid = $state->id();
        }
      }
    }

    // First, then, create it.
    if (!$this->creation_state) {
      $state = $this->createState(WORKFLOW_CREATION_STATE_NAME);
      $this->creation_state = $state;
      $this->creation_sid = $state->id();
    }

    return $this->creation_state;
  }

  /**
   * Gets the ID of the initial state for a newly created entity.
   */
  public function getCreationSid() {
    if (!$this->creation_sid) {
      $state = $this->getCreationState();
      return $state->id();
    }
    return $this->creation_sid;
  }

  /**
   * Gets the first valid state ID, after the creation state.
   *
   * Uses WorkflowState::getOptions(), because this does a access check.
   * The first State ID is user-dependent!
   */
  public function getFirstSid($entity, $field_name, AccountInterface $user, $force) {
    // TODO D8-port: getFirstSid must be called from WorkflowElement.
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    $creation_state = $this->getCreationState();
    $options = $creation_state->getOptions($entity, $field_name, $user, $force);
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
   * @return WorkflowState[]
   *   An array of WorkflowState objects.
   */
  public function getStates($all = FALSE, $reset = FALSE) {
    $wid = $this->id();

    if ($reset) {
      $this->states = $wid ? WorkflowState::loadMultiple([], $wid, $reset) : array();
    }
    elseif ($this->states === NULL) {
      $this->states = $wid ? WorkflowState::loadMultiple([], $wid, $reset) : array();
    }
    elseif ($this->states === array()) {
      $this->states = $wid ? WorkflowState::loadMultiple([], $wid, $reset) : array();
    }
    // Do not unset, but add to array - you'll remove global objects otherwise.
    $states = array();

    foreach ($this->states as $state) {
      $id = $state->id();
      if ($all === TRUE) {
        $states[$id] = $state;
      }
      elseif (($all === FALSE) && ($state->isActive() && !$state->isCreationState())) {
        $states[$id] = $state;
      }
      elseif (($all == 'CREATION') && ($state->isActive() || $state->isCreationState())) {
        $states[$id] = $state;
      }
      else {
        // Do not add state.
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
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    $wid = $this->id();
    WorkflowState::load($key, $wid);
  }

  /**
   * Creates a Transition for this workflow.
   *
   * @param string $from_sid
   * @param string $to_sid
   * @param array $values
   *
   * @return mixed|null|static
   */
  public function createTransition($from_sid, $to_sid, $values = array()) {
    $config_transition = NULL;

    $workflow = $this;

    // First check if this transition already exists.
    if ($transitions = $this->getTransitionsByStateId($from_sid, $to_sid)) {
      $config_transition = reset($transitions);
    }
    else {
      $values['wid'] = $workflow->id();
      $values['from_sid'] = $from_sid;
      $values['to_sid'] = $to_sid;
      $config_transition = WorkflowConfigTransition::create($values);
      $config_transition->save();
    }
    $config_transition->setWorkflow($this);
    // Maintain the new object in the workflow.
    $this->transitions[$config_transition->id()] = $config_transition;

    return $config_transition;
  }

  /**
   * Sorts all Transitions for this workflow, according to State weight.
   *
   * This is only needed for the Admin UI.
   */
  public function sortTransitions() {
    // Sort the transitions on state weight.
    uasort($this->transitions, ['Drupal\workflow\Entity\WorkflowConfigTransition', 'sort'] );
  }

  /**
   * Loads all allowed ConfigTransitions for this workflow.
   *
   * @param array|NULL $ids
   *   Array of Transitions IDs. If NULL, show all transitions.
   * @param array $conditions
   *   $conditions['from_sid'] : if provided, a 'from' State ID.
   *   $conditions['to_sid'] : if provided, a 'to' state ID.
   *
   * @return \Drupal\workflow\Entity\WorkflowConfigTransition[]
   */
  public function getTransitions(array $ids = NULL, array $conditions = array()) {
    $config_transitions = array();

    // Get valid states + creation state.
    $states = $this->getStates('CREATION');

    // Get filters on 'from' states, 'to' states, roles.
    $from_sid = isset($conditions['from_sid']) ? $conditions['from_sid'] : FALSE;
    $to_sid = isset($conditions['to_sid']) ? $conditions['to_sid'] : FALSE;

    // Cache all transitions in the workflow.
    // We may have 0 transitions....
    if (!$this->transitions) {
      $this->transitions = array();

      // Get all transitions. (Even from other workflows. :-( )
      /* @var $config_transitions WorkflowConfigTransition[] */
      $transitions = WorkflowConfigTransition::loadMultiple($ids, []);
      foreach ($transitions as &$config_transition) {
        if (isset($states[$config_transition->getFromSid()])) {
          $config_transition->setWorkflow($this);
          $this->transitions[$config_transition->id()] = $config_transition;
        }
      }

      $this->sortTransitions();
    }

    /* @var $config_transition WorkflowConfigTransition */
    foreach ($this->transitions as &$config_transition) {
      if (!isset($states[$config_transition->getFromSid()])) {
        // Not a valid transition for this workflow.
      }
      elseif ($from_sid && $from_sid != $config_transition->getFromSid()) {
        // Not the requested 'from' state.
      }
      elseif ($to_sid && $to_sid != $config_transition->getToSid()) {
        // Not the requested 'to' state.
      }
      else {
        // Transition is allowed, permitted. Add to list.
        $config_transition->setWorkflow($this);
        $config_transitions[$config_transition->id()] = $config_transition;
      }
    }
    return $config_transitions;
  }

  public function getTransitionsById($tid) {
    return $this->getTransitions(array($tid));
  }

  /**
   *
   * Get a specific transition.
   *
   * @param string $from_sid
   * @param string $to_sid
   *
   * @return WorkflowConfigTransition[]
   */
  public function getTransitionsByStateId($from_sid, $to_sid) {
    $conditions = array(
      'from_sid' => $from_sid,
      'to_sid' => $to_sid,
    );
    return $this->getTransitions(NULL, $conditions);
  }

  /**
   * Gets a setting from the state object.
   */
  public function getSetting($key, array $field = array()) {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

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
  workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  // See also https://drupal.org/node/1702626 .
  $new_roles = array();
  foreach ($roles as $key => $rid) {
    if ($rid == -1) {
      $new_roles[$rid] = $rid;
    }
    else {
      if ($role = user_role_load($role_map[$rid])) {
        $new_roles[$role->id()] = $role->id();
      }
    }
  }
  return $new_roles;
}


<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowState.
 */

namespace Drupal\workflow\Entity;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_state",
 *   label = @Translation("Workflow state"),
 *   module = "workflow",
 *   handlers = {
 *     "list_builder" = "Drupal\workflow_ui\Controller\WorkflowStateListBuilder",
 *     "form" = {
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *      }
 *   },
 *   admin_permission = "administer workflow",
 *   config_prefix = "workflow_state",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "module",
 *     "wid",
 *     "weight",
 *     "sysid",
 *     "status",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/workflow/{workflow_workflow}/states",
 *   }
 * )
 */
class WorkflowState extends ConfigEntityBase {

  // TODO D8-port WorkflowState: rename variable $sid, $name to $id.
  // TODO D8-port WorkflowState: rename variable $state to $label.
  // TODO D8-port WorkflowState: remove static variable $states, cached by D8(??).

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

  /**
   * The machine_name of the attached Workflow.
   *
   * @var string
   */
  public $wid;

  /**
   * The weight of this Workflow state.
   *
   * @var int
   */
  public $weight;

  public $sysid = 0;
  public $status = 1;

  // Since workflows do not change, it is implemented as a singleton.
  protected static $states = array();

  /**
   * The attached Workflow.
   *
   * @var Drupal\workflow\Entity\Workflow
   */
  protected $workflow;

  /**
   * CRUD functions.
   */

  /**
   * Constructor.
   *
   * @param int $wid
   *   The Workflow ID for which a new State is created.
   * @param string $name
   *   The name of the new State. If '(creation)', a CreationState is generated.
   */
  public function __construct(array $values = array(), $entityType = 'workflow_state') {
    // Please be aware that $entity_type and $entityType are different things!

    $id = isset($values['id']) ? $values['id'] : '';

    // Keep official name and external name equal. Both are required.
    // @todo: stil needed? test import, manual creation, programmatic creation, etc.
    if (!isset($values['label']) && $id) {
      $values['label'] = $id;
    }

    // Set default values for '(creation)' state.
    if ($id == WORKFLOW_CREATION_STATE_NAME) {
      $values['id'] = ''; // Clear ID; will be set in save().
      $values['sysid'] = WORKFLOW_CREATION_STATE;
      $values['weight'] = WORKFLOW_CREATION_DEFAULT_WEIGHT;
      $values['label'] = '(creation)'; // machine_name;
    }
    parent::__construct($values, $entityType);

    // Reset cache.
    self::$states = array();
  }

  /**
   * Alternative constructor, loading objects from table {workflow_states}.
   *
   * @param int $id
   *   The requested State ID
   * @param int $wid
   *   An optional Workflow ID, to check if the requested State is valid for the Workflow.
   *
   * @return WorkflowState|NULL $state
   *   WorkflowState if state is successfully loaded,
   *   NULL if not loaded,
   *   FALSE if state does not belong to requested Workflow.
   */
  public static function load($id, $wid = '') {
    foreach ($states = WorkflowState::loadMultiple([], $wid) as $state) {
      if ($id == $state->id()) {
        return $state;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function save($create_creation_state = TRUE) {
    // Create the machine_name for new states.
    // N.B.: Keep machine_name in WorkflowState and ~ListBuillder aligned.
    $sid = $this->id();
    $wid = $this->wid;

//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ .' '. $wid.'>'.$sid);

    if (empty($sid) || $sid == WORKFLOW_CREATION_STATE_NAME) {
      if ($label = $this->label()) {
        $sid = str_replace(' ', '_', strtolower($label));
      }
      else {
        $sid = 'state_' . $entity->id();
      }
      $this->set('id', implode('_', [$wid, $sid]));
    }

    return parent::save();
  }

  /**
   * Get all states in the system, with options to filter, only where a workflow exists.
   *
   * {@inheritdoc}
   * @param $wid
   *   The requested Workflow ID.
   * @param bool $reset
   *   An option to refresh all caches.
   *
   * @return array $states
   *   An array of cached states.
   */
  public static function loadMultiple(array $ids = NULL, $wid = '', $reset = FALSE) {
    if ($reset) {
      self::$states = array();
    }

    if (empty(self::$states)) {
      self::$states = parent::loadMultiple();
      usort(self::$states, ['Drupal\workflow\Entity\WorkflowState', 'sort'] );
    }

    if (!$wid) {
      // All states are requested and cached: return them.
      $result = self::$states;
    }
    else {
      // All states of only 1 Workflow is requested: return this one.
      // E.g., when called by Workflow->getStates().
      $result = array();
      foreach (self::$states as $state) {
        if ($state->wid == $wid) {
          $result[$state->id()] = $state;
        }
      }
    }
    return $result;

  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    $a_wid = $a->wid;
    $b_wid = $b->wid;
    if ($a_wid == $b_wid) {
      $a_weight = $a->getWeight();
      $b_weight = $b->getWeight();
      return ($a_weight < $b_weight) ? -1 : 1;
    }
    return ($a_wid < $b_wid) ? -1 : 1;
  }

  /**
   * Deactivate a Workflow State, moving existing content to a given State.
   *
   * @param int $new_sid
   *   The state ID, to which all affected entities must be moved.
   */
  public function deactivate($new_sid) {
//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ );

    $user = \Drupal::currentUser(); // We can use global, since deactivate() is a UI-only function.

    $current_sid = $this->id();
    $force = TRUE;

    // Notify interested modules. We notify first to allow access to data before we zap it.
    // E.g., Node API (@todo Field API):
    // - re-parents any entity that we don't want to orphan, whilst deactivating a State.
    // - delete any lingering entity to state values.
    \Drupal::moduleHandler()->invokeAll('workflow', ['state delete', $current_sid, $new_sid, NULL, $force]);

    // TODO D8-port: re-implement below code.
//    dpm('TODO D8-port: re-implement re-assign states when deactivating state in function WorkflowState::' . deactivate );
    // Re-parent any entity that we don't want to orphan, whilst deactivating a State.
    // This is called in WorkflowState::deactivate().
    // @todo: reparent Workflow Field, whilst deactivating a state.
    // TODO D8- State should not know about Transition: move this to Workflow->DeactivateState.
//    if ($new_sid) {
//      // A candidate for the batch API.
//      // @TODO: Future updates should seriously consider setting this with batch.
//
//      $comment = t('Previous state deleted');
//      foreach (workflow_get_workflow_node_by_sid($current_sid) as $workflow_node) {
//        // @todo: add Field support in 'state delete', by using workflow_transition_history or reading current field.
//        $entity = Node::load($workflow_node->nid);
//        $field_name = '';
//        $transition = WorkflowTransition::create();
//        $transition->setValues($entity, $field_name, $current_sid, $new_sid, $user->id(), REQUEST_TIME, $comment);
//        $transition->force($force);
//        // Execute Transition, invoke 'pre' and 'post' events, save new state in Field-table, save also in workflow_transition_history.
//        // For Workflow Node, only {workflow_node} and {workflow_transition_history} are updated. For Field, also the Entity itself.
//        $new_sid = workflow_execute_transition($entity, $field_name, $transition, $force);
//      }
//    }
//
//    // Delete any lingering entity to state values.
//    workflow_delete_workflow_node_by_sid($current_sid);

    // Delete the transitions this state is involved in.
    $workflow = Workflow::load($this->wid);
    foreach ($workflow->getTransitionsByStateId($current_sid, '', 'ALL') as $transition) {
      $transition->delete();
    }
    foreach ($workflow->getTransitionsByStateId('', $current_sid, 'ALL') as $transition) {
      $transition->delete();
    }

    // Delete the state. -- We don't actually delete, just deactivate.
    // This is a matter up for some debate, to delete or not to delete, since this
    // causes name conflicts for states. In the meantime, we just stick with what we know.
    // If you really want to delete the states, use workflow_cleanup module, or delete().
    $this->status = FALSE;
    $this->save();

    // Clear the cache.
    self::loadMultiple([], 0, TRUE);
  }

  /**
   * Property functions.
   */

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * Returns the Workflow ID of this State.
   *
   * @return string
   *   Workflow Id.
   */
  public function getWorkflowId() {
    return $this->wid;
  }

  /**
   * Returns the Workflow object of this State.
   *
   * @return Workflow
   *   Workflow object.
   */
  public function getWorkflow() {
    if (!isset($this->workflow)) {
      $this->workflow = Workflow::load($this->wid);
    }
    return $this->workflow;
  }

  public function setWorkflow($workflow) {
    $this->wid = $workflow->id();
    $this->workflow = $workflow;
  }

  /**
   * Returns the Workflow object of this State.
   *
   * @return bool
   *   TRUE if state is active, else FALSE.
   */
  public function isActive() {
    return (bool) $this->status;
  }

  public function isCreationState() {
    return $this->sysid == WORKFLOW_CREATION_STATE;
  }

  /**
   * Determines if the Workflow Form must be shown.
   *
   * If not, a formatter must be shown, since there are no valid options.
   *
   * @return bool $show_widget
   *   TRUE = a form (a.k.a. widget) must be shown; FALSE = no form, a formatter must be shown instead.
   */
  public function showWidget($entity, $field_name, AccountInterface $user, $force) {
    $options = $this->getOptions($entity, $field_name, $user, $force);
    $count = count($options);
    // The easiest case first: more then one option: always show form.
    if ($count > 1) {
      return TRUE;
    }
    // #2226451: Even in Creation state, we must have 2 visible states to show the widget.
    // // Only when in creation phase, one option is sufficient,
    // // since the '(creation)' option is not included in $options.
    // // When in creation state,
    // if ($this->isCreationState()) {
    // return TRUE;
    // }
    return FALSE;
  }

  /**
   * Returns the allowed transitions for the current state.
   *
   * @param EntityInterface $entity
   *   The entity at hand. May be NULL (E.g., on a Field settings page).
   * @param string $field_name
   * @param AccountInterface $user
   *
   * @return array
   *   An array of id=>transition pairs with allowed transitions for State.
   */
  public function getTransitions($entity = NULL, $field_name = '', AccountInterface $user = NULL, $force = FALSE) {
    $transitions = array();

    if (!$workflow = $this->getWorkflow()) {
      // No workflow, no options ;-)
      return $transitions;
    }

    $current_sid = $this->id();
    $current_state = $this;
    // Get the role IDs of the user, to get the proper permissions.
    $roles = $user ? $user->getRoles() : array();

    // TODO D8-port: get the Author from the entity in WorkflowState->getTransitions().
    // Some entities (e.g., taxonomy_term) do not have a uid.
    $entity_uid = $entity->get('uid')->getEntity();// ; isset($entity->uid) ? $entity->uid : 0;
    $entity_uid = 0;

    // Fetch entity_id from entity for _newness_ check
    $entity_id = ($entity) ? $entity->id() : '';

    if ($force) {
      // $force allows Rules to cause transition.
      $roles = 'ALL';
    }
//      dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__.'/'.__LINE__ . 'Make user 1 special' (seveal locationss);
//    elseif($user && $user->id() == 1) {
//      // Superuser is special. And $force allows Rules to cause transition.
//      $roles = 'ALL';
//    }
    elseif ($entity && (!empty($entity->is_new) || empty($entity_id))) {
//      dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__.'/'.__LINE__ );
      // Add 'author' role to user, if this is a new entity.
      // - $entity can be NULL (E.g., on a Field settings page).
      // - on display of new entity, $entity_id and $is_new are not set.
      // - on submit of new entity, $entity_id and $is_new are both set.
      $roles = array_merge(array(WORKFLOW_ROLE_AUTHOR_RID), $roles);
    }
    elseif (($entity_uid > 0) && ($user->id() > 0) && ($entity_uid == $user->id())) {
//      dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__.'/'.__LINE__ );
      // Add 'author' role to user, if user is author of this entity.
      // - Some entities (e.g, taxonomy_term) do not have a uid.
      // - If 'anonymous' is the author, don't allow access to History Tab,
      //   since anyone can access it, and it will be published in Search engines.
      $roles = array_merge(array(WORKFLOW_ROLE_AUTHOR_RID), $roles);
    }
    else {
//      dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__.'/'.__LINE__ );
    }

    // Set up an array with states - they are already properly sorted.
    // Unfortunately, the config_transitions are not sorted.
    // Also, $transitions does not contain the 'stay on current state' transition.
    // The allowed objects will be replaced with names.
    $transitions = $workflow->getTransitionsByStateId($current_sid, '', $roles);

    // Let custom code add/remove/alter the available transitions.
    // Using the new drupal_alter.
    // Modules may veto a choice by removing a transition from the list.
    $context = array(
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity,
      'field_name' => $field_name,
      'force' => $force,
      'workflow' => $workflow,
      'state' => $current_state,
      'user' => $user,
      'user_roles' => $roles, // @todo: can be removed in D8, since $user is in.
    );
    // @todo D8: rename to 'workflow_permitted_transitions'. / Remove redundant context.
    \Drupal::moduleHandler()->alter('workflow_permitted_state_transitions', $transitions, $context);

    // Let custom code change the options, using old_style hook.
    // @todo D8: delete below foreach/hook for better performance and flexibility.
    // Above drupal_alter() calls hook_workflow_permitted_state_transitions_alter() only once.
    foreach ($transitions as $transition) {
      $to_sid = $transition->to_sid;
      $permitted = array();

      // We now have a list of config_transitions. Check each against the Entity.
      // Invoke a callback indicating that we are collecting state choices.
      // Modules may veto a choice by returning FALSE.
      // In this case, the choice is never presented to the user.
      if ($roles != 'ALL') {
        // TODO: D8-port: simplify interface for workflow_hook. Remove redundant context.
        $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition permitted', $current_sid, $to_sid, $entity, $force, $entity_type = '', $field_name, $transition, $user]);
      }

      // If vetoed by a module, remove from list.
      if (in_array(FALSE, $permitted, TRUE)) {
        unset($transitions[$transition->id()]);
      }
    }

    return $transitions;
  }

  /**
   * Returns the allowed values for the current state.
   *
   * @param object $entity
   *   The entity at hand. May be NULL (E.g., on a Field settings page).
   *
   * @return array
   *   An array of sid=>label pairs.
   *   If $this->id() is set, returns the allowed transitions from this state.
   *   If $this->id() is 0 or FALSE, then labels of ALL states of the State's
   *   Workflow are returned.
   */
  public function getOptions($entity, $field_name, AccountInterface $user = NULL, $force = FALSE) {
    $options = array();

    // Define an Entity-specific cache per page load.
    static $cache = array();

    $entity_id = ($entity) ? $entity->id() : '';
    $entity_type = ($entity) ? $entity->getEntityTypeId() : '';
    $current_sid = $this->id();

    // Get options from page cache, using a non-empty index (just to be sure).
    $entity_index = (!$entity) ? 'x' : $entity_id;
    if (isset($cache[$entity_type][$entity_index][$force][$current_sid])) {
      $options = $cache[$entity_type][$entity_index][$force][$current_sid];
      return $options;
    }

    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // No workflow, no options ;-)
      $options = array();
    }
    elseif (!$current_sid) {
      // If no State ID is given, we return all states.
      // We cannot use getTransitions, since there are no ConfigTransitions
      // from State with ID 0, and we do not want to repeat States.
      foreach ($workflow->getStates() as $state) {
        $options[$state->id()] = SafeMarkup::checkPlain(t($state->label()));
      }
    }
    else {
      $transitions = $this->getTransitions($entity, $field_name, $user, $force);
      foreach ($transitions as $transition) {
        // Get the label of the transition, and if empty of the target state.
        // Beware: the target state may not exist, since it can be invented
        // by custom code in the above drupal_alter() hook.
        if (!$label = $transition->label()) {
          $to_state = $transition->getToState();
          $label = $to_state ? $to_state->label() : '';
        }
        $to_sid = $transition->to_sid;
        $options[$to_sid] = SafeMarkup::checkPlain(t($label));
      }

      // Include current state for same-state transitions, except when $sid = 0.
      // Caveat: this unnecessary since 7.x-2.3 (where stay-on-state transitions are saved, too.)
      // but only if the transitions have been saved at least one time.
      if ($current_sid && ($current_sid != $workflow->getCreationSid())) {
        if (!isset($options[$current_sid])) {
          $options[$current_sid] = SafeMarkup::checkPlain(t($this->label()));
        }
      }

      // Save to entity-specific cache.
      $cache[$entity_type][$entity_index][$force][$current_sid] = $options;
    }

    return $options;
  }

  /**
   * Returns the number of entities with this state.
   *
   * @return int
   *   Counted number.
   *
   * @todo: add $options to select on entity type, etc.
   */
  public function count() {
    $count = 0;
    $sid = $this->id();

//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ );
    return $count;

    foreach ($fields = _workflow_info_fields() as $field_name => $field_info) {
      $query = new EntityFieldQuery();
      $query
        ->fieldCondition($field_name, 'value', $sid, '=')
        // ->entityCondition('bundle', 'article')
        // ->addMetaData('account', user_load(1)) // Run the query as user 1.
        ->count(); // We only need the count.

      $result = $query->execute();
      $count += $result;
    }

    return $count;
  }
}

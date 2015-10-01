<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowTransition.
 *
 * Implements (scheduled/executed) state transitions on entities.
 */

namespace Drupal\workflow\Entity;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Implements an actual, executed, Transition.
 *
 * If a transition is executed, the new state is saved in the Field or {workflow_node}.
 * If a transition is saved, it is saved in table {workflow_history_node}
 *
 * @ContentEntityType(
 *   id = "workflow_transition",
 *   label = @Translation("Workflow executed transition"),
 *   bundle_label = @Translation("Workflow type"),
 *   module = "workflow",
 *   base_table = "workflow_transition_history",
 *   translatable = FALSE,
 *   list_cache_contexts_TODO = { "user.node_grants:view" },
 *   entity_keys = {
 *     "id" = "hid",
 *   },
 *   links = {
 *     "canonical" = "/workflow_transition/{workflow_transition}",
 *     "delete-form" = "/workflow_transition/{workflow_transition}/delete",
 *     "edit-form" = "/workflow_transition/{workflow_transition}/edit",
 *   }
 * )
 */
class WorkflowTransition extends ContentEntityBase implements WorkflowTransitionInterface {

  /*
   * Entity data: Use WorkflowTransition->getEntity() to fetch this.
   */
//  public $entity_type;
//  public $bundle;
//  private $entity_id; // Use WorkflowTransition->getEntity() to fetch this.
//  private $revision_id; // Use WorkflowTransition->getEntity() to fetch this.
//  public $field_name = '';
//  private $langcode = Language::LANGCODE_NOT_SPECIFIED;
//  public $delta = 0;

  /*
   * Transition data: are provided via baseFieldDefinitions().
   */
//  private $hid = 0;
//  public $from_sid;
//  public $to_sid;
//  public $uid; // baseFieldProperty. Use WorkflowTransition->getUser() to fetch this.
//  public $timestamp;  // baseFieldProperty. use getTimestamp() to fetch this.
//  public $comment; // baseFieldProperty. use getComment() to fetch this.

  /*
   * Cache data.
   */
  protected $wid; // Use WorkflowTransition->getWorkflow() to fetch this.
  protected $entity = NULL; // Use WorkflowTransition->getEntity() to fetch this.
  protected $user = NULL; // Use WorkflowTransition->getUser() to fetch this.

  /*
   * Extra data: describe the state of the transition.
   */
  protected $is_scheduled = FAlSE;
  protected $is_executed = FALSE;
  protected $is_forced = FALSE;

  /**
   * Entity class functions.
   */

  /**
   * Creates a new entity.
   *
   * @param string $entity_type
   *   The entity type of the attached $entity.
   * @param string $entityType
   *   The entity type of this Entity subclass.
   *
   * @see entity_create()
   *
   * No arguments passed, when loading from DB.
   * All arguments must be passed, when creating an object programmatically.
   * One argument $entity may be passed, only to directly call delete() afterwards.
   */
  public function __construct(array $values = array(), $entityType = 'WorkflowTransition') {
    // Please be aware that $entity_type and $entityType are different things!
    parent::__construct($values, $entityType);

    // This transition is not scheduled.
    $this->is_scheduled = FALSE;
    // This transition is not executed, if it has no hid, yet, upon load.
    $hid = $this->id();
    $this->is_executed = ($hid > 0);
  }

  /**
   * Helper function for __construct. Used for all children of WorkflowTransition (aka WorkflowScheduledTransition)
   */
  public function setValues($entity, $field_name, $from_sid, $to_sid, $uid = NULL, $timestamp = REQUEST_TIME, $comment = '') {
    // Normally, the values are passed in an array, and set in parent::__construct, but we do it ourselves.

    $user = \Drupal::currentUser();

    $this->field_name = $field_name;
    $uid = ($uid === NULL) ? $user->id() : $uid;

    // If constructor is called with new() and arguments.
    // Load the supplied entity.
    $this->setEntity($entity);

    if (!$entity && !$from_sid && !$to_sid) {
      dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__);
      // If constructor is called without arguments, e.g., loading from db.
    }
    elseif ($entity && $from_sid) {
      // Caveat: upon entity_delete, $to_sid is '0'.
      // If constructor is called with new() and arguments.
      $this->set('from_sid', $from_sid);
      $this->set('to_sid', $to_sid);

      $this->setUserId($uid);
      $this->setTimestamp($timestamp);
      $this->setComment($comment);
    }
    elseif (!$from_sid) {
      dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__);
      // Not all parameters are passed programmatically.
      drupal_set_message(
        t('Wrong call to constructor Workflow*Transition(@from_sid to @to_sid)',
          array('@from_sid' => $from_sid, '@to_sid' => $to_sid)),
        'error');
    }
  }

  /**
   * CRUD functions.
   */

  /**
   * {@inheritdoc}
   */
  public function save() {
    // return parent::save();

    // Avoid custom actions for subclass WorkflowScheduledTransition.
    if ($this->entityTypeId != 'workflow_transition') {
      return parent::save();
    }

    // Check for no transition.
    if ($this->getFromSid() == $this->getToSid()) {
      if (!$this->getComment()) {
        // Write comment into history though.
        return;
      }
    }

    $hid = $this->id();
    if (!$hid) {
      // Insert the transition. Make sure it hasn't already been inserted.
      $found_transition = self::loadByProperties(
        $this->getEntity()->getEntityTypeId(),
        $this->getEntity()->id(),
        array(),
        $this->getFieldName(),
        $this->getLangcode());
      if ($found_transition &&
        $found_transition->getTimestamp() == REQUEST_TIME &&
        $found_transition->getToSid() == $this->getToSid()) {
        dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__);
        return SAVED_UPDATED;
      }
      else {
        // $this->setTimestamp(REQUEST_TIME);
        return parent::save();
      }
    }
    else {
      dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__.': ' . $from_sid .'> ' .$to_sid);
      // Update the transition.
      return parent::save();
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultiple(array $ids = NULL) {
    return parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByProperties($entity_type, $entity_id, array $revision_ids, $field_name = '', $langcode = '', $transition_type = 'workflow_transition') {
    $limit = 1;
    if ($transitions = self::loadMultipleByProperties($entity_type, [$entity_id], $revision_ids, $field_name, $limit, $langcode, $transition_type)) {
      $transition = reset($transitions);
      return $transition;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type, array $entity_ids, array $revision_ids, $field_name, $limit = NULL, $langcode = '', $transition_type = 'workflow_transition') {

    /* @var $query \Drupal\Core\Entity\Query\QueryInterface */
    $query = \Drupal::entityQuery($transition_type)
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_ids, 'IN')
      ->sort('timestamp', 'ASC')
      ->addTag($transition_type);
    if ($field_name != '') {
      $query->condition('field_name', $field_name, '=');
    }
    if ($langcode != '') {
      $query->condition('langcode', $langcode, '=');
    }
    if ($limit) {
      $query->range(0, $limit);
    }
    if ($transition_type == 'workflow_transition') {
      // The timestamp is only granular to the second; on a busy site, we need the id.
      // $query->orderBy('h.timestamp', 'DESC');
      $query->sort('hid', 'DESC');
    }
    $ids = $query->execute();
    $transitions = self::loadMultiple($ids);
    return $transitions;
  }

  /**
   * Property functions.
   */

  /**
   * {@inheritdoc}
   */
  public function isAllowed(array $roles, AccountInterface $user = NULL, $force = FALSE) {

    if ($force) {
      // $force allows Rules to cause transition.
      return TRUE;
    }
    elseif($user && $user->id() == 1) {
//      dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__.'/'.__LINE__ . 'Make user 1 special' (seveal locationss);
//      // Superuser is special. And $force allows Rules to cause transition.
//      return TRUE;
    }

    // Check allow-ability of state change if user is not superuser (might be cron).
    // Get the WorkflowConfigTransition.
    // @todo: some day, WorkflowConfigTransition can be a parent of WorkflowTransition.
    // @todo: There is a watchdog error, but no UI-error. Is this ok?
    $workflow = $this->getWorkflow();
    $config_transitions = $workflow->getTransitionsByStateId($this->getFromSid(), $this->getToSid());
    $config_transition = reset($config_transitions);
    if (!$config_transition || !$config_transition->isAllowed($roles)) {
      $message = t('Attempt to go to nonexistent transition (from %from_sid to %to_sid)');
      $t_args = array(
        '%from_sid' => $this->getFromSid(),
        '%to_sid' => $this->getToSid(),
        'link' =>  $this->getEntity()->link(t('View')),
      );
      \Drupal::logger('workflow')->error($message, $t_args);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($force = FALSE) {
    /* @var $user \Drupal\Core\Session\AccountInterface */
    $user = $this->getUser();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $field_name = $this->getFieldName();

    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    $entity = $this->getEntity();
    if ($entity) {
      // Only after getEntity(), the following are surely set.
      $entity_type = $entity->getEntityTypeId();
      $entity_id = $entity->id();
    }
    else {
      $message = 'User tried to execute a Transition without an entity.';
      \Drupal::logger('workflow')->error($message, []);
      return $from_sid;  // <--- exit !!!
    }

    static $static_last_tid;
    static $static_last_sid = '';
    if ($this->id() != $static_last_tid) {
      // OK. Prepare for next round. Do not set last_sid!!
      $static_last_tid = $this->id();
    }
    else {
      // Error: this Transition is already executed.
      // On the development machine, execute() is called twice, when
      // on an Edit Page, the entity has a scheduled transition, and
      // user changes it to 'immediately'.
      // Why does this happen?? ( BTW. This happens with every submit.)
      // Remedies:
      // - search root cause of second call.
      // - try adapting code of transtion->save() to avoid second record.
      // - avoid executing twice.
      $message = 'Transition is executed twice in a call. The second call is
        not executed.';
      \Drupal::logger('workflow')->error($message, []);
      // Return the result of the last call.
      return ($static_last_sid) ? $static_last_sid : $from_sid;  // <--- exit !!!
    }

    // Make sure $force is set in the transition, too.
    if ($force) {
      $this->force($force);
    }

    // TODO D8-port: figure out usage of $entity->workflow_transitions[$field_name]
    /*
        // Store the transition, so it can be easily fetched later on.
        // Store in an array, to prepare for multiple workflow_fields per entity.
        // This is a.o. used in hook_entity_update to trigger 'transition post'.
        $entity->workflow_transitions[$field_name] = $this;
    */
    // Prepare an array of arguments for error messages.
    $args = array(
      '%user' => ($user) ? $user->getUsername() : '',
      '%old' => $from_sid,
      '%new' => $to_sid,
      '%label' => $entity->label(),
//      'link' =>  ($entity_id) ? $entity->link(t('View')) : '', // TODO
    );

    if (!$this->getFromState()) {
      // TODO: the page is not correctly refreshed after this error.
      drupal_set_message($message = t('You tried to set a Workflow State, but
        the entity is not relevant. Please contact your system administrator.'),
        'error');
      $message = 'Setting a non-relevant Entity from state %old to %new';
      \Drupal::logger('workflow')->error($message, $args);

      return $from_sid;
    }

    // Check if the state has changed.
    // If so, check the permissions.
    $state_changed = ($from_sid != $to_sid);
    $comment = $this->getComment();
    if ($state_changed) {
      // State has changed. Do some checks upfront.

      if (!$force) {
        // Make sure this transition is allowed by workflow module Admin UI.
        $roles = array_keys($user->getRoles());
        $roles = array_merge(array(WORKFLOW_ROLE_AUTHOR_RID), $roles);
        if (!$this->isAllowed($roles, $user, $force)) {
          $message = 'User %user not allowed to go from state %old to %new';
          \Drupal::logger('workflow')->error($message, $args);
          // If incorrect, quit.
          return $from_sid;
        }
      }
      else {
        // OK. All state changes allowed.
      }

      if (!$force) {
        // Make sure this transition is allowed by custom module.
        // @todo D8: remove, or replace by 'transition pre'. See WorkflowState::getOptions().
        // @todo D8: replace all parameters that are inlcuded in $transition.
        // @todo: in case of error, ther is a log, but no UI error.
        $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition permitted', $from_sid, $to_sid, $entity, $force, $entity_type, $field_name, $this, $user]);
        // Stop if a module says so.
        if (in_array(FALSE, $permitted, TRUE)) {
          \Drupal::logger('workflow')->notice('Transition vetoed by module.', []);
          return $from_sid;
        }
      }
      else {
        // OK. All state changes allowed.
      }

      // Make sure this transition is valid and allowed for the current user.
      // Invoke a callback indicating a transition is about to occur.
      // Modules may veto the transition by returning FALSE.
      // (Even if $force is TRUE, but they shouldn't do that.)
      $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition pre', $from_sid, $to_sid, $entity, $force, $entity_type, $field_name, $this]);
      // Stop if a module says so.
      if (in_array(FALSE, $permitted, TRUE)) {
        dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__.': ' . $from_sid .'> ' .$to_sid);
        \Drupal::logger('workflow')->notice('Transition vetoed by module.', []);
        return $from_sid;
      }

    }
    elseif ($comment) {
      // No need to ask permission for adding comments.
      // Since you should not add actions to a 'transition pre' event, there is
      // no need to invoke the event.
    }
    else {
      // There is no state change, and no comment.
      // We may need to clean up something.
    }

    // The transition is allowed. Let other modules modify the comment. The transition (in context) contains all relevant data.
    $context = array('transition' => $this);
    \Drupal::moduleHandler()->alter('workflow_comment', $comment, $context);
    $this->setComment($comment);

    $this->is_executed = TRUE;

    if ($state_changed || $comment) {

      /*
       * Log the transition in {workflow_transition_history}.
       */
      $this->save();

      // Register state change with watchdog.
      if ($state_changed) {
        $workflow = $this->getWorkflow();
        if (($new_state = $this->getToState()) && !empty($workflow->options['watchdog_log'])) {
          $entity_type_info = \Drupal::entityManager()->getDefinition($entity_type);
          $message = ($this->isScheduled()) ? 'Scheduled state change of @type %label to %state_name executed' : 'State of @type %label set to %state_name';
          $args = array(
            '@type' => $entity_type_info->getLabel(),
            '%label' => $entity->label(),
            '%state_name' => SafeMarkup::checkPlain(t($new_state->label())),
            '%user' => isset($user->name) ? $user->name : '',
            '%old' => $from_sid,
            '%new' => $to_sid,
            '%label' => $this->entity->label(),
            // 'link' =>  $this->entity->link(t('View')), // TODO
          );
          \Drupal::logger('workflow')->notice($message, $args);
        }
      }

      // Remove any scheduled state transitions.
      foreach (WorkflowScheduledTransition::loadMultipleByProperties($entity_type, [$entity_id], [], $field_name) as $scheduled_transition) {
        $scheduled_transition->delete();
      }

      // Notify modules that transition has occurred.
      // Action triggers should take place in response to this callback, not the 'transaction pre'.
      if (!$field_name) {
        dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__.'/'.__LINE__.': ' . $from_sid .'> ' .$to_sid);
        // Now that workflow data is saved, reset stuff to avoid problems
        // when Rules etc want to resave the data.
        // Remember, this is only for nodes, and node_save() is not necessarily performed.
        unset($entity->workflow_comment);
        \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $from_sid, $to_sid, $entity, $force, $entity_type, $field_name, $this]);
        entity_get_controller('node')->resetCache(array($entity->id())); // from entity_load(), node_save();
      }
      else {
        // module_invoke_all('workflow', 'transition post', $from_sid, $to_sid, $entity, $force, $entity_type, $field_name, $this);
        // We have a problem here with Rules, Trigger, etc. when invoking
        // 'transition post': the entity has not been saved, yet. we are still
        // IN the transition, not AFTER. Alternatives:
        // 1. Save the field here explicitely, using field_attach_save;
        // 2. Move the invoke to another place: hook_entity_insert(), hook_entity_update();
        // 3. Rely on the entity hooks. This works for Rules, not for Trigger.
        // --> We choose option 2:
        // TODO D8-port: figure out usage of $entity->workflow_transitions[$field_name]
        // - First, $entity->workflow_transitions[] is set for easy re-fetching.
        // - Then, post_execute() is invoked via workflowfield_entity_insert(), _update().
      }
    }

    // Save value in static from top of this function.
    $static_last_sid = $to_sid;
    return $to_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function post_execute($force = FALSE) {
    dpm('TODO D8-port: test function WorkflowTransition::' . __FUNCTION__);

    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $entity = $this->getEntity(); // Entity may not be loaded, yet.
    $entity_type = $entity->getEntityTypeId();
    // $entity_id = $this->entity_id;
    $field_name = $this->getFieldName();

    $state_changed = ($from_sid != $to_sid);
    if ($state_changed || $this->comment) {
      \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $from_sid, $to_sid, $entity, $force, $entity_type, $field_name, $this]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow() {
    $workflow = NULL;

    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();

    if (!$this->wid) {
      $state = WorkflowState::load($to_sid ? $to_sid : $from_sid);
      $this->wid = $state->getWorkflowId();
    }
    if ($this->wid) {
      $workflow = Workflow::load($this->wid);
    }
    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    if (!$this->entity) {
      $entity_type = $this->get('entity_type')->target_id;
      $entity_id = $this->get('entity_id')->value;
      $this->entity = \Drupal::entityManager()->getStorage($entity_type)->load($entity_id);
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity($entity) {
    $this->entity = $entity;
    if ($entity) {
      /* @var $entity \Drupal\Core\Entity\EntityInterface */
      $this->entity_type = $entity->getEntityTypeId();
      $this->entity_id = $entity->id();
      $this->revision_id = $entity->getRevisionId();
      $this->delta = 0; // Only single value is supported.
      $this->langcode = $entity->language()->getId();
    }
    else {
      $this->entity_type = '';
      $this->entity_id = '';
      $this->revision_id = '';
      $this->delta = 0; // Only single value is supported.
      $this->langcode = Language::LANGCODE_NOT_SPECIFIED;
    }

    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName() {
    return $this->get('field_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->getEntity()->language()->getId();

  }

  /**
   * {@inheritdoc}
   */
  public function getFromState() {
    return WorkflowState::load($this->getFromSid());
  }

  /**
   * {@inheritdoc}
   */
  public function getToState() {
    return WorkflowState::load($this->getToSid());
  }

  /**
   * {@inheritdoc}
   */
  public function getFromSid() {
    $sid = $this->{'from_sid'}->value;
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getToSid() {
    $sid = $this->{'to_sid'}->value;
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(AccountInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    $uid = $this->{'uid'}->target_id;
    $user = \Drupal::entityManager()->getStorage('user')->load($uid);
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment() {
    return $this->get('comment')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setComment($value) {
    $this->set('comment', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTimestamp($value) {
    $this->set('timestamp', $value);
    return $this;
  }

  /**
   * Returns if this is a Scheduled Transition.
   */
  public function isScheduled() {
    return $this->is_scheduled;
  }

  public function schedule($schedule = TRUE) {
    return $this->is_scheduled = $schedule;
  }

  public function isExecuted() {
    return $this->is_executed;
  }

  /**
   * {@inheritdoc}
   */
  public function isForced() {
    return (bool) $this->is_forced;
  }
  public function force($force = TRUE) {
    return $this->is_forced = $force;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = array();

    $fields['hid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Transition ID'))
      ->setDescription(t('The transition ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['wid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Workflow ID'))
      ->setDescription(t('The name of the Workflow the transition relates to.'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The transition UUID.'))
      ->setReadOnly(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The Entity type this transition belongs to.'))
      ->setSetting('target_type', 'node_type')
      ->setReadOnly(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The Entity ID this record is for.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The current version identifier.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field name'))
      ->setDescription(t('The name of the field the transition relates to.'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setSetting('max_length', 32)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The entity language code.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', array(
        'type' => 'hidden',
      ))
      ->setDisplayOptions('form', array(
        'type' => 'language_select',
        'weight' => 2,
      ));

    $fields['delta'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Delta'))
      ->setDescription(t('The sequence number for this data item, used for multi-value fields.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['from_sid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The {workflow_states}.sid this transition started as.'))
//      ->setSetting('target_type', 'workflow_transition')
      ->setReadOnly(TRUE);

    $fields['to_sid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The {workflow_states}.sid this transition transitioned to.'))
//      ->setSetting('target_type', 'workflow_transition')
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Transition user ID'))
      ->setDescription(t('The user ID of the author of this transition.'))
      ->setSetting('target_type', 'user')
      ->setQueryable(FALSE)
//      ->setSetting('handler', 'default')
//      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
//      ->setTranslatable(TRUE)
//      ->setDisplayOptions('view', array(
//        'label' => 'hidden',
//        'type' => 'author',
//        'weight' => 0,
//      ))
//      ->setDisplayOptions('form', array(
//        'type' => 'entity_reference_autocomplete',
//        'weight' => 5,
//        'settings' => array(
//          'match_operator' => 'CONTAINS',
//          'size' => '60',
//          'placeholder' => '',
//        ),
//      ))
//      ->setDisplayConfigurable('form', TRUE),
      ->setRevisionable(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('The time that the current transition was executed.'))
      ->setQueryable(FALSE)
//      ->setTranslatable(TRUE)
//      ->setDisplayOptions('view', array(
//        'label' => 'hidden',
//        'type' => 'timestamp',
//        'weight' => 0,
//      ))
      ->setDisplayOptions('form', array(
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ))
//      ->setDisplayConfigurable('form', TRUE);
      ->setRevisionable(TRUE);

//D8-port    $fields['revision_log'] = BaseFieldDefinition::create('string_long')
    $fields['comment'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Log message'))
      ->setDescription(t('The comment explaining this transition.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'string_textarea',
        'weight' => 25,
        'settings' => array(
          'rows' => 4,
        ),
      ));

    return $fields;
  }

}

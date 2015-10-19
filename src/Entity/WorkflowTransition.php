<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowTransition.
 *
 * Implements (scheduled/executed) state transitions on entities.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\user\UserInterface;

/**
 * Implements an actual, executed, Transition.
 *
 * If a transition is executed, the new state is saved in the Field.
 * If a transition is saved, it is saved in table {workflow_transition_history}.
 *
 * @ContentEntityType(
 *   id = "workflow_transition",
 *   label = @Translation("Workflow executed transition"),
 *   bundle_label = @Translation("Workflow type"),
 *   module = "workflow",
 *   handlers = {
 *     "access" = "Drupal\workflow\WorkflowAccessControlHandler",
 *     "list_builder" = "Drupal\workflow\Controller\WorkflowTransitionListBuilder",
 *     "form" = {
 *        "add" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "edit" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "revert" = "Drupal\workflow_operations\Form\WorkflowTransitionRevertForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *      },
 *     "views_data" = "Drupal\workflow\WorkflowTransitionViewsData",
 *   },
 *   base_table = "workflow_transition_history",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "hid",
 *   },
 *   links = {
 *     "canonical" = "/workflow/transition/{workflow_transition}",
 *     "edit-form" = "/workflow/transition/{workflow_transition}/edit",
 *     "revert-form" = "/workflow/transition/{workflow_transition}/revert",
 *     "delete-form" = "/workflow/transition/{workflow_transition}/delete",
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
//  public $uid; // baseFieldProperty. Use WorkflowTransition->getOwnerId() to fetch this.
//  public $timestamp;  // baseFieldProperty. use getTimestamp() to fetch this.
//  public $comment; // baseFieldProperty. use getComment() to fetch this.

  /*
   * Cache data.
   */
//  protected $wid; // Use WorkflowTransition->getWorkflowId() to fetch this.
  protected $workflow; // Use WorkflowTransition->getWorkflow() to fetch this.
  protected $entity = NULL; // Use WorkflowTransition->getEntity() to fetch this.
  protected $user = NULL; // Use WorkflowTransition->getOwner() to fetch this.

  /*
   * Extra data: describe the state of the transition.
   */
  protected $is_scheduled;
  protected $is_executed;
  protected $is_forced = FALSE;

  /**
   * Entity class functions.
   */

  /**
   * Creates a new entity.
   *
   * @param array $values
   * @param string $entityType
   *   The entity type of this Entity subclass.
   *
   * @internal param string $entity_type The entity type of the attached $entity.*   The entity type of the attached $entity.
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
    $this->is_executed = ($this->id() > 0);
    // Initialize wid property.
    $this->getWorkflowId();
  }

  /**
   * {@inheritdoc}
   */
  public function setValues(EntityInterface $entity = NULL, $field_name, $from_sid, $to_sid, $uid = NULL, $timestamp = REQUEST_TIME, $comment = '', $force_create = FALSE) {
    // Normally, the values are passed in an array, and set in parent::__construct, but we do it ourselves.

    $user = \Drupal::currentUser();

    $this->set('field_name', $field_name);
    $uid = ($uid === NULL) ? $user->id() : $uid;

    // If constructor is called with new() and arguments.
    // Load the supplied entity.
    $this->setEntity($entity);

    if (!$entity && !$from_sid && !$to_sid) {
      $this->setOwnerId($uid);
      $this->setTimestamp($timestamp);
      $this->setComment($comment);
      // If constructor is called without arguments, e.g., loading from db.
    }
    elseif ($entity && $from_sid) {
      // Caveat: upon entity_delete, $to_sid is '0'.
      // If constructor is called with new() and arguments.
      $this->set('from_sid', $from_sid);
      $this->set('to_sid', $to_sid);
      $this->setOwnerId($uid);
      $this->setTimestamp($timestamp);
      $this->setComment($comment);
    }
    elseif (!$from_sid) {
      // Not all parameters are passed programmatically.
      if ($force_create) {
        //
        $this->set('from_sid', $from_sid);
        $this->set('to_sid', $to_sid);
        $this->setOwnerId($uid);
        $this->setTimestamp($timestamp);
        $this->setComment($comment);
      }
      else {
        drupal_set_message(
          t('Wrong call to constructor Workflow*Transition(@from_sid to @to_sid)',
            array('@from_sid' => $from_sid, '@to_sid' => $to_sid)),
          'error');
      }
    }

    // Initialize wid property.
    $this->getWorkflowId();
  }

  /**
   * CRUD functions.
   */

  /**
   * Saves the entity.
   * Mostly, you'd better use WorkflowTransitionInterface::execute();
   *
   * {@inheritdoc}
   */
  public function save() {
    // return parent::save();

    // Avoid custom actions for subclass WorkflowScheduledTransition.
    if ($this->isScheduled()) {
      return parent::save();
    }
    if ($this->getEntityTypeId() != 'workflow_transition') {
      return parent::save();
    }

    $transition = $this;
    $field_name = $transition->getFieldName();
    // getEntity() also sets properties.
    $entity = $transition->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();

    // Remove any scheduled state transitions.
    foreach (WorkflowScheduledTransition::loadMultipleByProperties($entity_type, [$entity_id], [], $field_name) as $scheduled_transition) {
      /* @var WorkflowTransitionInterface $scheduled_transition */
      $scheduled_transition->delete();
    }

    // Check for no transition.
    if ($this->getFromSid() == $this->getToSid()) {
      if (!$this->getComment()) {
        // Write comment into history though.
        return SAVED_UPDATED;
      }
    }

    $hid = $this->id();
    if (!$hid) {
      // Insert the transition. Make sure it hasn't already been inserted.
      $entity = $this->getEntity();
      // @todo: Allow a scheduled transition per revision.
      // @todo: Allow a state per language version (langcode).
      $found_transition = self::loadByProperties($entity->getEntityTypeId(), $entity->id(), [], $this->getFieldName());
      if ($found_transition &&
        $found_transition->getTimestamp() == REQUEST_TIME &&
        $found_transition->getToSid() == $this->getToSid()) {
        return SAVED_UPDATED;
      }
      else {
        return parent::save();
      }
    }
    else {
      // Update the transition.
      return parent::save();
    }

  }

  /**
   * {@inheritdoc}
   */
//  public static function loadMultiple(array $ids = NULL) {
//    return parent::loadMultiple($ids);
//  }

  /**
   * {@inheritdoc}
   */
  public static function loadByProperties($entity_type, $entity_id, array $revision_ids = [], $field_name = '', $langcode = '', $sort = 'ASC', $transition_type = 'workflow_transition') {
    $limit = 1;
    if ($transitions = self::loadMultipleByProperties($entity_type, [$entity_id], $revision_ids, $field_name, $langcode, $limit, $sort, $transition_type)) {
      $transition = reset($transitions);
      return $transition;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type, array $entity_ids, array $revision_ids = [], $field_name = '', $langcode = '', $limit = NULL, $sort = 'ASC', $transition_type = 'workflow_transition') {

    /* @var $query \Drupal\Core\Entity\Query\QueryInterface */
    $query = \Drupal::entityQuery($transition_type)
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_ids, 'IN')
// @todo     ->condition('revision_id', $revision_ids, 'IN')
      ->sort('timestamp', $sort) // 'DESC' || 'ASC'
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
   * Implementing interface WorkflowTransitionInterface - properties.
   */

  /**
   * Determines if the Transition is valid and can be executed.
   * @todo: add to isAllowed() ?
   * @todo: add checks to WorkflowTransitionElement ?
   *
   * @return bool
   */
  public function isValid() {
    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    $entity = $this->getEntity();
    $entity_type = ($entity) ? $entity->getEntityTypeId() : '';
    /* @var $user \Drupal\user\UserInterface */
    $user = $this->getOwner();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $field_name = $this->getFieldName();
    $force = $this->isForced();

    // Prepare an array of arguments for error messages.
    $args = array(
      '%user' => ($user) ? $user->getUsername() : '',
      '%old' => $from_sid,
      '%new' => $to_sid,
      '%label' => $entity->label(),
      'link' => ($this->getEntity()->id()) ? $this->getEntity()->link(t('View')) : '',
    );

    if (!$entity) {
      $message = 'User tried to execute a Transition without an entity.';
      \Drupal::logger('workflow')->error($message, $args);
      return FALSE;  // <-- exit !!!
    }
    if (!$this->getFromState()) {
      // TODO: the page is not correctly refreshed after this error.
      drupal_set_message($message = t('You tried to set a Workflow State, but
        the entity is not relevant. Please contact your system administrator.'),
        'error');
      $message = 'Setting a non-relevant Entity from state %old to %new';
      \Drupal::logger('workflow')->error($message, $args);
      return FALSE;  // <-- exit !!!
    }

    // The transition is OK.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(UserInterface $user, $force = FALSE) {

    /**
     * Get early permissions of user, and bail out to avoid extra hook-calls.
     */
    // Check allow-ability of state change if user is not superuser (might be cron).
    $type_id = $this->getWorkflowId();
    if ($user->hasPermission("bypass $type_id workflow_transition access")) {
      // Superuser is special. And $force allows Rules to cause transition.
      return TRUE;
    }
    if ($force) {
      // $force allows Rules to cause transition.
      return TRUE;
    }

    // @todo: Keep below code aligned between WorkflowState, ~Transition, ~TransitionListController
    /**
     * Get the object and its permissions.
     */
    $config_transitions = $this->getWorkflow()->getTransitionsByStateId($this->getFromSid(), $this->getToSid());

    /**
     * Determine if user has Access.
     */
    $result = FALSE;
    foreach ($config_transitions as $config_transition) {
      $result = $result || $config_transition->isAllowed($user, $force);
    }

    if ($result == FALSE) {
      // @todo: There is a watchdog error, but no UI-error. Is this ok?
      $message = t('Attempt to go to nonexistent transition (from %from_sid to %to_sid)');
      $t_args = array(
        '%from_sid' => $this->getFromSid(),
        '%to_sid' => $this->getToSid(),
        'link' => ($this->getEntity()->id()) ? $this->getEntity()->link(t('View')) : '',
      );
      \Drupal::logger('workflow')->error($message, $t_args);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntity() {
    $transition = $this;
    $field_name = $transition->getFieldName();
    $entity = $transition->getEntity();

    $to_sid = ( $transition->isScheduled() && !$transition->isExecuted() ) ? $transition->getFromSid() : $transition->getToSid();
    // @todo: Update Entity only if field is changed??
    $entity->{$field_name}->setValue($to_sid);
    return $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($force = FALSE) {
    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    $entity = $this->getEntity();
    $entity_type = ($entity) ? $entity->getEntityTypeId() : '';
    // Load explicit User object (not via $transition) for adding Role later.
    /* @var $user \Drupal\user\UserInterface */
    $user = $this->getOwner();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $field_name = $this->getFieldName();
    $comment = $this->getComment();

    static $static_last_eid = -1;
    static $static_last_tid = -1;
    static $static_last_sid = '';
    if ($this->id() !== $static_last_tid
      || $entity->id() !== $static_last_eid) {
      // OK. Prepare for next round. Do not set last_sid!!
      $static_last_tid = $this->id();
      $static_last_eid = $entity->id();
    }
    else {
      // workflow_debug( __FILE__ , __FUNCTION__, __LINE__, '2nd time');  // @todo D8-port: still test this snippet.
      // workflow_debug( $this->id(), $entity->id(), '','', $static_last_sid);  // @todo D8-port: still test this snippet.

      // Error: this Transition is already executed.
      // On the development machine, execute() is called twice, when
      // on an Edit Page, the entity has a scheduled transition, and
      // user changes it to 'immediately'.
      // Why does this happen?? ( BTW. This happens with every submit.)
      // Remedies:
      // - search root cause of second call.
      // - try adapting code of transition->save() to avoid second record.
      // - avoid executing twice.
      $message = 'Transition is executed twice in a call. The second call for ' .
       $static_last_eid . ' is not executed.';
      \Drupal::logger('workflow')->error($message, []);
      // Return the result of the last call.
      return ($static_last_sid) ? $static_last_sid : $from_sid;  // <-- exit !!!
    }

    // Make sure $force is set in the transition, too.
    if ($force) {
      $this->force($force);
    }
    $force = $this->isForced();

    // TODO D8-port: figure out usage of $entity->workflow_transitions[$field_name]
    /*
        // Store the transition, so it can be easily fetched later on.
        // Store in an array, to prepare for multiple workflow_fields per entity.
        // This is a.o. used in hook_entity_update to trigger 'transition post'.
        $entity->workflow_transitions[$field_name] = $this;
    */

    if (!$this->isValid()) {
      return $from_sid;  // <-- exit !!!
    }

    // @todo: move below code to $this->isAllowed().
    // Prepare an array of arguments for error messages.
    $args = array(
      '%user' => $user->getUsername(),
      '%old' => $from_sid,
      '%new' => $to_sid,
      '%label' => $entity->label(),
      'link' => ($this->getEntity()->id()) ? $this->getEntity()->link(t('View')) : '',
    );
    // Check if the state has changed.
    // If so, check the permissions.
    $state_changed = ($from_sid != $to_sid);
    if ($state_changed) {
      // State has changed. Do some checks upfront.

      if (!$force) {
        // Make sure this transition is allowed by workflow module Admin UI.
        $user->addRole(WORKFLOW_ROLE_AUTHOR_RID);
        if (!$this->isAllowed($user, $force)) {
          $message = 'User %user not allowed to go from state %old to %new';
          \Drupal::logger('workflow')->error($message, $args);
          // If incorrect, quit.
          return FALSE;  // <-- exit !!!
        }
      }
      else {
        // OK. All state changes allowed.
      }

      // As of 8.x-1.x, below hook() is removed, in favour of below hook 'transition pre'.
//      if (!$force) {
//        // Make sure this transition is allowed by custom module.
//        // @todo D8: replace all parameters that are included in $transition.
//        // @todo: in case of error, there is a log, but no UI error.
//        $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition permitted', $this, $user]);
//        // Stop if a module says so.
//        if (in_array(FALSE, $permitted, TRUE)) {
//          \Drupal::logger('workflow')->notice('Transition vetoed by module.', $args);
//          return FALSE;  // <-- exit !!!
//        }
//      }
//      else {
//        // OK. All state changes allowed.
//      }

      // Make sure this transition is valid and allowed for the current user.
      // Invoke a callback indicating a transition is about to occur.
      // Modules may veto the transition by returning FALSE.
      // (Even if $force is TRUE, but they shouldn't do that.)
      $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition pre', $this, $user]);
      // Stop if a module says so.
      if (in_array(FALSE, $permitted, TRUE)) {
        \Drupal::logger('workflow')->notice('Transition vetoed by module.', $args);
        return FALSE;  // <-- exit !!!
      }

    }
    elseif ($this->getComment()) {
      // No need to ask permission for adding comments.
      // Since you should not add actions to a 'transition pre' event, there is
      // no need to invoke the event.
    }
    else {
      // There is no state change, and no comment.
      // We may need to clean up something.
    }


    /**
     * Output: process the transition.
     */
    if ($this->isScheduled()) {
      /*
       * Log the transition in {workflow_transition_scheduled}.
       */
      $this->save();
    }
    else {
      // The transition is allowed, but not scheduled.
      // Let other modules modify the comment. The transition (in context) contains all relevant data.
      $context = array('transition' => $this);
      \Drupal::moduleHandler()->alter('workflow_comment', $comment, $context);
      $this->setComment($comment);

      $this->is_executed = TRUE;

      $state_changed = ($from_sid != $to_sid);
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
            if ($this->getEntityTypeId() == 'workflow_scheduled_transition') {
              $message = 'Scheduled state change of @type %label to %state_name executed';
            }
            else {
              $message = 'State of @type %label set to %state_name';
            }
            $args = array(
              '@type' => $entity_type_info->getLabel(),
              '%label' => $entity->label(),
              '%state_name' => t($new_state->label()), // @todo check_plain()?
              'link' => ($this->getEntity()->id()) ? $this->getEntity()->link(t('View')) : '',
            );
            \Drupal::logger('workflow')->notice($message, $args);
          }
        }

        // Notify modules that transition has occurred.
        // Action triggers should take place in response to this callback, not the 'transaction pre'.

        //\Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $this, $user]);
        // We have a problem here with Rules, Trigger, etc. when invoking
        // 'transition post': the entity has not been saved, yet. we are still
        // IN the transition, not AFTER. Alternatives:
        // 1. Save the field here explicitly, using field_attach_save;
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
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    $state_changed = ($from_sid != $to_sid);
    if ($state_changed || $this->getComment()) {
      $user = $this->getOwner();
      \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $this, $user]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow() {
    if (!$this->workflow && $wid = $this->getWorkflowId()) {
      $this->workflow = Workflow::load($wid);
    }
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId() {

    if (!$this->wid->value && $from_sid = $this->getFromSid()) {
      $state = WorkflowState::load($from_sid);
      // Fallback
      $to_sid = $this->getToSid();
      $state = ($state) ? $state : WorkflowState::load($to_sid);

      $this->wid->value = ($state) ? $state->getWorkflowId() : '';
    }
    return $this->wid->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    if (!$this->entity) {
      $entity_type = $this->get('entity_type')->target_id;
      $entity_id = $this->get('entity_id')->value;
      $this->entity = ($entity_type) ? \Drupal::entityManager()->getStorage($entity_type)->load($entity_id) : NULL;
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
    $sid = $this->getFromSid();
    return ($sid) ? WorkflowState::load($sid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getToState() {
    $sid = $this->getToSid();
    return ($sid) ? WorkflowState::load($sid) : NULL;
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
  public function getTimestampFormatted() {
    $timestamp = $this->getTimestamp();
    return \Drupal::service('date.formatter')->format($timestamp);
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
//    // We do a tricky thing here. The id of the entity is altered, so
//    // all functions of another subclass are called.
//    $this->entityTypeId = ($schedule) ? 'workflow_scheduled_transition' : 'workflow_transition';

    return $this->is_scheduled = $schedule;
  }

  /**
   * {@inheritdoc}
   */
  public function isExecuted() {
    return $this->is_executed;
  }

  /**
   * {@inheritdoc}
   */
  public function isForced() {
    return (bool) $this->is_forced;
  }

  /**
   * {@inheritdoc}
   */
  public function force($force = TRUE) {
    return $this->is_forced = $force;
  }

  /**
   * Implementing interface EntityOwnerInterface. Copied from Comment.php.
   */

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $user = $this->get('uid')->entity;
    if (!$user || $user->isAnonymous()) {
      $user = User::getAnonymousUser();
      $user->name = $this->getAuthorName();
      $user->homepage = $this->getHomepage();
    }
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * Implementing interface FieldableEntityInterface extends EntityInterface.
   */

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

    $fields['entity_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity type'))
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
      ->setLabel(t('From state'))
      ->setDescription(t('The {workflow_states}.sid this transition started as.'))
//      ->setSetting('target_type', 'workflow_transition')
      ->setReadOnly(TRUE);

    $fields['to_sid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('To state'))
      ->setDescription(t('The {workflow_states}.sid this transition transitioned to.'))
//      ->setSetting('target_type', 'workflow_transition')
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
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

  public function dpm(){
    $transition = $this;
    $entity = $transition->getEntity();
    $time = \Drupal::service('date.formatter')->format($transition->getTimestamp());
    // Do this extensive $user_name lines, for some troubles with Action.
    $user = $transition->getOwner();
    $user_name = ($user) ? $user->getUsername() : 'unknown username';
    $t_string = $this->getEntityTypeId() . ' ' . $this->id();
    $output[] = 'Entity  = ' . ((!$entity) ? 'NULL' : ($entity->getEntityTypeId() . '/' . $entity->bundle() . '/' . $entity->id()));
    $output[] = 'Field   = ' . $transition->getFieldName();
    $output[] = 'From/To = ' . $transition->getFromSid() . ' > ' . $transition->getToSid() . ' @ ' . $time;
    $output[] = 'Comment = ' . $user_name . ' says: ' . $transition->getComment();
    $output[] = 'Forced  = ' . ($transition->isForced() ? 'yes' : 'no');
    if (function_exists('dpm')) { dpm($output, $t_string); }
  }

}

<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowScheduledTransition.
 *
 * Implements (scheduled/executed) state transitions on entities.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Implements a scheduled transition, as shown on Workflow form.
 *
 * @ContentEntityType(
 *   id = "workflow_scheduled_transition",
 *   label = @Translation("Workflow scheduled transition"),
 *   bundle_label = @Translation("Workflow type"),
 *   module = "workflow",
 *   base_table = "workflow_transition_schedule",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "tid",
 *   },
 *   links = {
 *     "canonical" = "/workflow_transition/{workflow_transition}",
 *     "delete-form" = "/workflow_transition/{workflow_transition}/delete",
 *     "edit-form" = "/workflow_transition/{workflow_transition}/edit",
 *   }
 * )
 */
class WorkflowScheduledTransition extends WorkflowTransition {

  /**
   * Constructor.
   */
  public function __construct(array $values = array(), $entityType = 'WorkflowScheduledTransition') {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

    // Please be aware that $entity_type and $entityType are different things!
    parent::__construct($values, $entityType);

    $this->is_scheduled = TRUE;
    $this->is_executed = FALSE;
  }

  public function setValues($entity, $field_name, $from_sid, $to_sid, $uid = NULL, $scheduled = REQUEST_TIME, $comment = '') {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

   // A scheduled transition does not have a timestamp, yet.
    parent::setValues($entity, $field_name, $from_sid, $to_sid, $uid, $timestamp, $comment);
  }

  /**
   * Given a node, get all scheduled transitions for it.
   *
   * @param string $entity_type
   * @param int $entity_id
   * @param string $field_name
   *   Optional.
   *
   * @return array
   *   An array of WorkflowScheduledTransitions.
   */
  public static function load($id) {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);
    return parent::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type, array $entity_ids, $field_name = '', $limit = NULL, $langcode = '') {
//    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);
    return array();

    if (!$entity_ids) {
      return array();
    }

    $query = db_select('workflow_transition_schedule', 'wst');
    $query->fields('wst');
    $query->condition('entity_type', $entity_type, '=');
    $query->condition('entity_id', $entity_id, '=');
    if ($field_name !== NULL) {
      $query->condition('field_name', $field_name, '=');
    }
    $query->orderBy('timestamp', 'ASC');
    $query->addTag('workflow_scheduled_transition');
    if ($limit) {
      $query->range(0, $limit);
    }
//    $result = $query->execute()->fetchAll(PDO::FETCH_CLASS, 'WorkflowScheduledTransition');
//    $result = $query->execute()->fetchAll(\PDO::FETCH_CLASS, 'WorkflowScheduledTransition');
    $result = $query->execute()->fetchAll(\PDO::FETCH_CLASS, NULL, ['workflow_scheduled_transition']);

    return $result;
  }

  /**
   * Given a timeframe, get all scheduled transitions.
   *
   * @param int $start
   * @param int $end
   *
   * @return WorkflowScheduledTransition[] $transitions
   *   An array of transitions.
   */
  public static function loadBetween($start = 0, $end = 0) {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

    $query = db_select('workflow_scheduled_transition', 'wst');
    $query->fields('wst');
    $query->orderBy('timestamp', 'ASC');
    $query->addTag('workflow_scheduled_transition');

    if ($start) {
      $query->condition('timestamp', $start, '>');
    }
    if ($end) {
      $query->condition('timestamp', $end, '<');
    }

    $result = $query->execute()->fetchAll(PDO::FETCH_CLASS, 'WorkflowScheduledTransition');
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * Save a scheduled transition. If the transition is executed, save in history.
   */
  public function save() {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

    // If executed, save in history.
    if ($this->is_executed) {
      // Be careful, we are not a WorkflowScheduleTransition anymore!
      $this->entityType = 'WorkflowTransition';
      $this->setUp();

      return parent::save(); // <--- exit !!
    }

    // Since we do not have an entity_id here, we cannot use entity_delete.
    // @todo: Add an 'entity id' to WorkflowScheduledTransition entity class.
    // $result = parent::save();

    // Avoid duplicate entries.
    $clone = clone $this;
    $clone->delete();
    // Save (insert or update) a record to the database based upon the schema.
    \Drupal::database()->insert('workflow_scheduled_transition')->fields($this)->execute();

    // Create user message.
    if ($state = $this->getNewState()) {
      $entity_type = $this->entity_type;
      $entity = $this->getEntity();
      $message = '%entity_title scheduled for state change to %state_name on %scheduled_date';
      $args = array(
        '@entity_type' => $entity_type,
        '%entity_title' => $entity->label(),
        '%state_name' => $state->label(),
        '%scheduled_date' => format_date($this->getTimestamp()),
      );
      $uri = entity_uri($entity_type, $entity);
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// watchdog('workflow', $message, $args, WATCHDOG_NOTICE, l('view', $uri['path'] . '/workflow'));

      drupal_set_message(t($message, $args));
    }
  }

  /**
   * Given a node, delete transitions for it.
   */
  public function delete() {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

    // Support translated Workflow Field workflows by including the langcode.
    db_delete($this->entityInfo['base table'])
        ->condition('entity_type', $this->entity_type)
        ->condition('entity_id', $this->entity_id)
        ->condition('field_name', $this->field_name)
        ->condition('langcode', $this->langcode)
        ->execute();
  }

  /**
   * Property functions.
   */

  /**
   * If a scheduled transition has no comment, a default comment is added before executing it.
   */
  public function addDefaultComment() {
    dpm('TODO D8-port: test function WorkflowScheduledTransition::' . __FUNCTION__);

    $this->setComment(t('Scheduled by user @uid.', array('@uid' => $this->getUser()->id())));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = array();

    $fields['tid'] = BaseFieldDefinition::create('integer')
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
//    $fields['from_sid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The {workflow_states}.sid this transition started as.'))
      ->setSetting('target_type', 'workflow_transition')
      ->setReadOnly(TRUE);

    $fields['to_sid'] = BaseFieldDefinition::create('string')
//    $fields['to_sid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The {workflow_states}.sid this transition transitioned to.'))
      ->setSetting('target_type', 'workflow_transition')
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
      ->setLabel(t('Scheduled'))
      ->setDescription(t('The date+time this transition is scheduled for.'))
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

<?php

/**
 * @file
 * Contains \Drupal\workflow\Controller\WorkflowTransitionListBuilder.
 */

namespace Drupal\workflow\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

define('WORKFLOW_MARK_STATE_IS_DELETED', '*');

/**
 * Defines a class to build a draggable listing of Workflow State entities.
 *
 * @see \Drupal\workflow\Entity\WorkflowState
 */
class WorkflowTransitionListBuilder extends EntityListBuilder implements FormInterface {

  public $workflow_entity;

  /**
   * {@inheritdoc}
   */
  protected $limit = 50;

  /**
   * Indicates if a footer must be generated.
   *
   * @var bool
   */
  protected $footer_needed = FALSE;

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = array();

    // TODO: D8-port: get entity from proper core methods.
    /* @var $entity EntityInterface */
    $entity = $this->workflow_entity; // This is a custom variable.
    // Get the field name. It is yet unknown. N.B. This does not work with multiple workflows per entity!
    $field_name = workflow_get_field_name($entity);
    if (!$field_name) {
      // @todo D8-port: if no workflow_field found, then no history_tab -> error log?
    }
    else {
      $entity_type = $entity->getEntityTypeId();
      $entity_id = $entity->id();

      // @todo D8-port: document $limit.
      // @todo d8-port: $limit should be used in pager, not in load().
      $this->limit = \Drupal::config('workflow.settings')
        ->get('workflow_states_per_page');
      $limit = $this->limit;
      // Get Transitions with higest timestamp first.
      $entities = WorkflowTransition::loadMultipleByProperties($entity_type, array($entity_id), [], $field_name, '', $limit, 'DESC');
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_transiton_history_form';
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['timestamp'] = $this->t('Date');
    $header['from_state'] = $this->t('From State');
    $header['to_state'] = $this->t('To State');
    $header['user_name'] = $this->t('By');
    $header['comment'] = $this->t('Comment');

    // column 'Operations' is now added by core.
    //$header['operations'] = $this->t('Operations');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @todo D8-port: add D7-theming to TransitionListBuilder.
   */
  public function buildRow(EntityInterface $transition) {

    // Show the history table.
    $current_themed = FALSE;
    /* @var $transition WorkflowTransitionInterface */
    $entity = $transition->getEntity();
    $field_name = $transition->getFieldName();
    $current_sid = workflow_node_current_state($entity, $field_name);

    $to_state = $transition->getToState();
    if (!$to_state) {
      // This is an invalid/deleted state.
      $to_label = WORKFLOW_MARK_STATE_IS_DELETED;
      // Add a footer to explain the addition.
      $this->footer_needed = TRUE;
    }
    else {
      $label = Html::escape($this->t($to_state->label()));
      if ($transition->getToSid() == $current_sid && $to_state->isActive() && !$current_themed) {
        $to_label = $label;

        if (!$current_themed) {
          // Make a note that we have themed the current state; other times in the history
          // of this entity where the entity was in this state do not need to be specially themed.
          $current_themed = TRUE;
        }
      }
      elseif (!$to_state->isActive()) {
        $to_label = $label . WORKFLOW_MARK_STATE_IS_DELETED;
        // Add a footer to explain the addition.
        $this->footer_needed = TRUE;
      }
      else {
        // Regular state.
        $to_label = $label;
      }
    }
    unset($to_state); // Not needed anymore.

    $from_state = $transition->getFromState();
    if (!$from_state) {
      // This is an invalid/deleted state.
      $from_label = WORKFLOW_MARK_STATE_IS_DELETED;
      // Add a footer to explain the addition.
      $this->footer_needed = TRUE;
    }
    else {
      $label = Html::escape($this->t($from_state->label()));
      if (!$from_state->isActive()) {
        $from_label = $label . WORKFLOW_MARK_STATE_IS_DELETED;
        // Add a footer to explain the addition.
        $this->footer_needed = TRUE;
      }
      else {
        // Regular state.
        $from_label = $label;
      }
    }
    unset($from_state); // Not needed anymore.

    $variables = array(
      'transition' => $transition,
      'extra' => '',
    );
    // Allow other modules to modify the row.
    \Drupal::moduleHandler()->alter('workflow_history', $variables);

//     'class' => array('workflow_history_row'), // TODO D8-port
    $row['timestamp']['data'] = $transition->getTimestampFormatted(); // 'class' => array('timestamp')
    $row['from_state']['data'] = $from_label; // 'class' => array('previous-state-name'))
    $row['to_state']['data'] = $to_label; // 'class' => array('state-name'))
    $row['user_name']['data'] = $transition->getOwner()->getUsername(); // 'class' => array('user-name')
    $row['comment']['data'] = Html::escape($transition->getComment()); // 'class' => array('log-comment')
//    $row['comment'] = array(
//      '#type' => 'textarea',
//      '#default_value' => $transition->getComment(),
//    );

    // column 'Operations' is now added by core.
//    $row['operations']['data'] = $this->buildOperations($entity);
    // @TODO D8-port: add operations column.
    return $row; // + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   *
   * @todo Add a link to add a new item to the #empty text.
   */
  public function render() {
    $build = array();

    // @todo d8-port: get pager working.
    $this->limit = \Drupal::config('workflow.settings')->get('workflow_states_per_page');  // TODO D8-port
    // $output .= theme('pager', array('tags' => $limit)); // TODO D8-port

    // @todo d8-port: get core title working: $build['table']['#title'] by getTitle() is not working.
    $build['workflow_list_title'] = array(
      '#markup' => $this->getTitle(),
    );

    $build += parent::render();

    // Add a footer. This is not yet added in EntityListBilder::render()
    if ($this->footer_needed) {  // TODO D8-port: test this.
      // Two variants. First variant is official, but I like 2nd better.
      /*
      $build['table']['#footer'] = array(
        array(
          'class' => array('footer-class'),
          'data' => array(
            array(
              'data' => WORKFLOW_MARK_STATE_IS_DELETED . ' ' . t('State is no longer available.'),
              'colspan' => count($build['table']['#header']),
            ),
          ),
        ),
      );
    */
      $build['workflow_footer'] = array(
        '#markup' => WORKFLOW_MARK_STATE_IS_DELETED . ' ' . t('State is no longer available.'),
        '#weight' => 500, // @todo Make this better.
      );
    }

    return $build;
  }

    /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    // TODO D8-port: convert workflow-operations to core-style.
    return $operations;

    /* @var WorkflowTransitionInterface $transition */
    $transition = $entity;

    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // TODO D8-port: test invokeAll('workflow_operations',).
    // Allow modules to insert operations per state.
    $workflow = $transition->getWorkflow();
    $links = \Drupal::moduleHandler()->invokeAll('workflow_operations', ['state', $workflow, $state]);
    /*
        if ($entity->hasLinkTemplate('edit-form')) {
          $operations['edit'] = array(
            'title' => t('Edit ball'),
            'weight' => 20,
            'url' => $entity->urlInfo('edit-form'),
          );
        }
    */
    return $operations;
  }

  /**
   * Gets the title of the page.
   *
   * @return string
   *   A string title of the page.
   */
  protected function getTitle() {
    return $this->t('Workflow history');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides DraggableListBuilder::submitForm().
   * The WorkflowState entities are always saved.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}

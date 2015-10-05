<?php

/**
 * @file
 * Contains \Drupal\workflow_ui\Controller\WorkflowStateListBuilder.
 */

namespace Drupal\workflow_ui\Controller;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Defines a class to build a draggable listing of Workflow State entities.
 *
 * @see \Drupal\workflow\Entity\WorkflowState
 */
class WorkflowStateListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */

// TODO D8-port: filter entities for correct $wid with array_filter in $this::load().
//  public function load() {
//    ('TODO D8-port: implement function WorkflowStateListBuilder::' . __FUNCTION__ );
//
//    $entities = parent::load();
//    dpm($entities, __FUNCTION__);
//
//  // Get the Workflow from the page.
//  /* @var $workflow \Drupal\workflow\Entity\Workflow */
//  if ($workflow = workflow_ui_url_get_workflow()) {
//  }
//    $entities = array_filter($entities, ($entity->wid == $wid ) )
//    dpm($entities, __FUNCTION__);
//
//    return $entities;
//  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_state_form';
//    return parent::getFormId();
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
//  The column 'weight' is added magically in the draggable EntityList.
//    $header['weight'] = $this->t('Weight');
//  Some columns are not welcome in the list.
//    $header['module'] = $this->t('Module');
//    $header['wid'] = $this->t('Workflow');
//    $header['sysid'] = $this->t('Sysid');
//  For some reason, you cannot make the 'label' column editable. So, we use 'label_new'.
//    $header['label'] = $this->t('Label');
    $header['label_new'] = $this->t('Label');
    $header['id'] = $this->t('ID');
    $header['sysid'] = $this->t('');
    $header['status'] = $this->t('Active');
    $header['reassign'] = $this->t('Reassign');
    $header['count'] = $this->t('Count');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\workflow\Entity\WorkflowState */
    $state = $entity;
    $sid = $state->id();
    $label = $state->label();

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return;
    }
    $wid = $url_wid = $workflow->id();

    // TODO D8-port: filter entities for correct $wid with array_filter in $this::load().
    // This function lists ALL states for ALL workfows. We only need for ONE workflow.
    // For backwards code-compatibility, @see D7.x-2.x, file workflow_admin_ui.page.states.inc
    if ($entity->wid != $wid) {
      return;
    }

    // Build select options for reassigning states.
    // We put a blank state first for validation.
    $state_options = array('' => ' ');
    $state_options += workflow_get_workflow_state_names($wid, $grouped = FALSE, $all = FALSE);
    // Is this the last state available?
    $form['#last_mohican'] = count($state_options) == 2;

    // Make it impossible to reassign to the same state that is disabled.
    if ($state->isCreationState() || !$sid || !$state->isActive()) {
      $current_state_options = array();
    } else {
      $current_state = array($sid => $state_options[$sid]);
      $current_state_options = array_diff($state_options, $current_state);
    }

    /*
     *  Build the Row.
     */
//  The column 'weight' is added magically in the draggable EntityList.
//      $row['weight'] = $state->weight;
//  Some columns are not welcome in the list.
//      $row['module'] = $state->getModule();
//      $row['wid'] = $state->getWorkflow();
//    For some reason, you cannot make the 'label' column editable. So, we use 'label_new'.
//    $row['label'] = $state->label();
    $row['label_new'] = [
      '#markup' => $label,
      '#type' => 'textfield',
      '#size' => 30,
      '#maxlength' => 255,
      '#default_value' => $label,
//    '#description' => t('The human-readable label of the workflow. This will be used as a label when
//         the workflow status is shown during editing of content.'),
      '#title' => NULL,  // Thes hides the red 'required' asterisk.
//      '#required' => TRUE,
    ];
    $row['id'] = [
      '#type' => 'machine_name',
      '#title' => NULL,  // Thes hides the red 'required' asterisk.
      '#size' => 30,
//       '#description' => t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#description' => NULL,
      '#disabled' => !$state->isNew(),
      '#default_value' => $state->id(),
      // N.B.: Keep machine_name in WorkflowState and ~ListBuillder aligned.
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [$this, 'exists'],  // Local helper function, at the bottom of this class.
// TODO D8-port: machine_name->source of new WorkflowState must follow the label."
        'source' => array('states', $state->id(), 'label_new'),
//        'source' => array('label_new'),
//        'replace_pattern' =>'([^a-z0-9_]+)|(^custom$)',
        'replace_pattern' => '[^a-z0-9_()]+', // Added '()' characters from exclusion list since creation state has it.
        'error' => $this->t('The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores.'),
      ],
    ];
    $row['sysid'] = [
      '#type' => 'value',
      '#value' => $state->sysid,
    ];
    $row['status'] = [
      '#type' => 'checkbox',
      '#default_value' => $state->isActive(),
    ];
    // The new value of states that are inactivated.
    $row['reassign'] = [
      '#type' => 'select',
      '#options' => $current_state_options,
    ];
    $row['count'] = [
      '#type' => 'value',
      '#value' => $state->count(),
      '#markup' => $state->count(),
    ];

    // Don't let the creation state change weight or status or name.
    if ($state->isCreationState()) {
//      $row['weight']['#value'] = $minweight;
//      $row['sysid']['#value'] = 1;
      $row['label_new']['#disabled'] = TRUE;
      $row['status']['#disabled'] = TRUE;
      $row['reassign']['#type'] = 'hidden';
      $row['reassign']['#disabled'] = TRUE;
    }
    // New state and disabled states cannot be reassigned.
    if (!$sid || !$state->isActive()) {
      $row['reassign']['#type'] = 'hidden';
      $row['reassign']['#disabled'] = TRUE;
    }
    // Disabled states cannot be renamed (and is a visual clue, too.).
    if (!$state->isActive()) {
      $row['label_new']['#disabled'] = TRUE;
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return $form;
    }
    $wid = $url_wid = $workflow->id();

    // Create a placeholder WorkflowState (It must NOT be saved to DB). Add it to the item list.
    $sid = '';
    $placeholder = $workflow->createState($sid, FALSE);
    $placeholder->set('label', '');
    $this->entities['placeholder'] = $placeholder;
    $form['entities']['placeholder'] = $this->buildRow($placeholder);

    // Rename 'submit' button.
    $form['actions']['submit']['#value'] = t('Save');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // dpm('TODO D8-port: test function WorkflowStateListBuilder::' . __FUNCTION__ );
    // TODO D8-port: test invokeAll('workflow_operations',).
    // Allow modules to insert operations per state.
    $state = $entity;
    $workflow = $state->getWorkflow();
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return ;
    }
    $wid = $url_wid = $workflow->id();

    foreach ($form_state->getValue($this->entitiesKey) as $sid => $value) {
      if (isset($this->entities[$sid])) {
        /* @var $state WorkflowState */
        $state = $this->entities[$sid];

        // Skip states of other workflows.
        // TODO D8-port: filter entities for correct $wid with array_filter in $this::load().
        // This function lists ALL states for ALL workfows. We only need for ONE workflow.
        // For backwards code-compatibility, @see D7.x-2.x, file workflow_admin_ui.page.states.inc
        if ($state->getWorkflowId() != $url_wid) {
          continue;
        }

        // Does user want to deactivate the state (reassign current content)?
        if ($sid && $value['status'] == 0 && $state->isActive()) {
          $args = array('%state' => $state->label()); // check_plain() is run by t().
          // Does that state have content in it?
          if ($value['count'] > 0 && empty($value['reassign'])) {
            if ($form['#last_mohican']) {
              $message = 'Since you are deleting the last available workflow state
            in this workflow, all content items which are in that state will have their
            workflow state removed.';
              drupal_set_message(t($message, $args), 'warning');
            } else {
              $message = 'The %state state has content; you must reassign the content to another state.';
              form_set_error("states'][$sid]['reassign'", t($message, $args));
            }
          }

          // @todo: Reassign states for Workflow Field.
          $message = 'Deactivating state %state for does not reassign Fields of type Workflow with this status.';
          drupal_set_message(t($message, $args), 'warning');
        }

        // Create the machine_name for new states.
        // N.B.: Keep machine_name in WorkflowState and ~ListBuillder aligned.
        if ($value['label_new'] && !$value['id']) {
//          $message = 'Machine name is required.';
//          $form_state->setErrorByName('machine_name', $this->t($message));
        }

      }
    }
    return;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides DraggableListBuilder::submitForm().
   * The WorkflowState entities are always saved.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //  parent::submitForm($form, $form_state);

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return ;
    }
    $wid = $url_wid = $workflow->id();

    $maxweight = $minweight = -9;
    foreach ($form_state->getValue($this->entitiesKey) as $sid => $value) {
      if (isset($this->entities[$sid])) {
        /* @var $state WorkflowState */
        $state = $this->entities[$sid];

        // Skip states of other workflows.
        // TODO D8-port: filter entities for correct $wid with array_filter in $this::load().
        // This function lists ALL states for ALL workfows. We only need for ONE workflow.
        // For backwards code-compatibility, @see D7.x-2.x, file workflow_admin_ui.page.states.inc
        if ($state->getWorkflowId() != $url_wid) {
          continue;
        }

        // Is the new state name empty?
        if (empty($value['label_new'])) {
          // No new state entered, so skip it.
          continue;
        }

        // Does user want to deactivate the state (reassign current content)?
        if ($sid && $value['status'] == 0 && $state->isActive()) {
          $new_sid = $value['reassign'];
          $new_state = WorkflowState::load($new_sid);

          $args = [
            '%workflow' => $workflow->label(), // check_plain() is run by t().
            '%old_state' => $state->label(),
            '%new_state' => isset($new_state) ? $new_state->label() : '',
          ];

          if ($value['count'] > 0) {
            if ($form['#last_mohican']) {
              $new_sid = NULL; // Do not reassign to new state.
              $message = 'Removing workflow states from content in the %workflow.';
              drupal_set_message(t($message, $args));
            } else {
              // Prepare the state delete function.
              $message = 'Reassigning content from %old_state to %new_state.';
              drupal_set_message(t($message, $args));
            }
          }
          // Delete the old state without orphaning content, move them to the new state.
          $state->deactivate($new_sid);

          $message = 'Deactivated workflow state %old_state in %workflow.';
          \Drupal::logger('workflow')->notice($message, []);
          drupal_set_message(t($message, $args));
        }

        // Set a proper weight to the new state.
        $maxweight = max($maxweight, $state->get($this->weightKey));

        // Is this a new state?
        if ($sid == 'placeholder' && empty(!$value['label_new'])) {
          // New state, add it.
          $state->set('id', $value['id']);
          // Set a proper weight to the new state.
          $state->set($this->weightKey, $maxweight + 1);
        }
        elseif ($value['sysid'] == WORKFLOW_CREATION_STATE) {
          // Set a proper weight to the creation state.
          $state->set($this->weightKey, $minweight - 1);
        }
        else {
          $state->set($this->weightKey, $value['weight']);
        }
        $state->set('label', $value['label_new']);
        $state->set('status', $value['status']);

        $state->save();
      }

    }
    drupal_set_message(t('The Workflow states have been updated.'));

    return;
  }

  /**
   * Validate duplicate machine names. Function registered in 'machine_name' form element.
   */
  function exists($name, $element, $form_state) {
    $state_names = array();
    foreach ($form_state->getValue($this->entitiesKey) as $sid => $value) {
      $state_names[] = $value['id'];
    }

    $state_names = array_map('strtolower', $state_names);
    $result = array_unique(array_diff_assoc($state_names, array_unique($state_names)));

    if (in_array($name, $result)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the form builder.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder.
   */
  protected function formBuilder() {
    if (!$this->formBuilder) {
      $this->formBuilder = \Drupal::formBuilder();
    }
    return $this->formBuilder;
  }

}

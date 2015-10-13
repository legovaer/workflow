<?php

/**
 * @file
 * Defines a Workflow field, widget and formatter. (copied from list field).
 */

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Changes the hook_field_settings_form.
 * Fixes some Field settings and Field Instance settings, and makes sure users cannot change it.
 *
 * @todo: perhaps this is core functionality, but these values are only saved
 *        when the site builder explicitly save the instance settings. :-(
 */
function workflowfield_form_field_storage_config_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $field_name = $form_state->getFormObject()->getEntity()->getType();

  if ($field_name == 'workflow') {
    // Make sure only 1 value can be entered in the Workflow field.
    $form['cardinality_container']['cardinality']['#default_value'] = 'number';
    $form['cardinality_container']['cardinality']['#disabled'] = TRUE;
    $form['cardinality_container']['cardinality_number']['#default_value'] = 1;
    $form['cardinality_container']['cardinality_number']['#disabled'] = TRUE;
    $form['cardinality_container']['cardinality_number']['#states'] = [];
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function workflowfield_form_field_config_edit_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $field_name = $form_state->getFormObject()->getEntity()->getType();

  if ($field_name == 'workflow') {
    // The Workflow field must have a value, so set to required.
    $form['required']['#default_value'] = 1;
    $form['required']['#disabled'] = TRUE;

    // User may not set a default value (this is done by the Workflow module).
    // @see WorkflowState::getOptions()
    $form['default_value']['#description'] .= ' ' . t('It can\'t be set here, since it may be different per user.');
    $form['default_value']['widget']['#default_value'] = '';
    $form['default_value']['widget']['#disabled'] = TRUE;
    unset($form['default_value']['widget']);
  }
}

/**
 * We will be using some default formatters and widgets from the List and Options modules.
 */

/**
 * Implements hook_field_formatter_info_alter().
 *
 * The module reuses the formatters defined in list.module.
 */
function workflowfield_field_formatter_info_alter(&$info) {
  $info['list_default']['field_types'][] = 'workflow';
  $info['basic_string']['field_types'][] = 'workflow';
}

/**
 * Implements hook_field_widget_info_alter().
 *
 * The module does not implement widgets of its own, but reuses the
 * widgets defined in options.module.
 *
 * @see workflowfield_options_list()
 */
function workflowfield_field_widget_info_alter(&$info) {
  $info['options_select']['field_types'][] = 'workflow';
  $info['options_buttons']['field_types'][] = 'workflow';
}

/**
 * {@inheritdoc}
 *
 * @see callback_allowed_values_function()
 *
 * deprecated: workflowfield_options_list --> workflow_state_allowed_values
 */
function workflow_state_allowed_values(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, &$cacheable = TRUE, AccountInterface $user = NULL) {
//function callback_allowed_values_function(FieldStorageDefinitionInterface $definition, FieldableEntityInterface $entity = NULL, &$cacheable = TRUE) {
// N.B. the parameter $user is added by Workflow module.
  $allowed_options = array();

  $user = workflow_current_user($user); // @todo #2287057: OK?
  $field_name = $definition->getName();

  if ($entity) {
    // Get the allowed new states for the entity's current state.
    // TODO D8-port: overwrite getValue().
    // $sid = $value = $this->getValue();
    $sid = workflow_node_current_state($entity, $field_name);
    $state = WorkflowState::load($sid);
    $allowed_options = ($state) ? $state->getOptions($entity, $field_name, $user, FALSE) : [];
  }
  else {
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    // Create an empty State. This triggers to show all possible states for the Workflow.
    $wid = $definition->getSetting('workflow_type');
    if ($workflow = Workflow::load($wid)) {
      // There is no entity, E.g., on the Rules action "Set a data value".
      /* @var $state Drupal\workflow\Entity\WorkflowState */
      $state = WorkflowState::create(['wid' => $wid, 'id' => '']);
      $allowed_options = $state->getOptions(NULL, '', $user, FALSE);
    }
  }

  return $allowed_options;
}

<?php

/**
 * @file
 * Defines a Workflow formatter.
 * You won't find a DefaultFormatter, because:
 * - The 'default' formatter provided by the List module;
 * - The 'workflow' formatter is only representing the WorkflowDefault Widget.
 *
 * All hooks are wrapper functions for a D8-style WorkflowDefaultWidget object.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;

/**
 * Creates a form element to show the current value of a Workflow state.
 *
 * @param EntityInterface $entity
 *   The entity this field is on.
 * @param string $field_name
 *   The field_name
 * @param string $current_sid
 *   The current State Id.
 *
 * @return array
 *   Form element, resembling the formatter of List module.
 *   If state 0 is given, return an empty form element.
 */
function workflow_state_formatter($entity, $field_name, $current_sid = '') {
  $element = array();

  if (!$current_sid) {
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $current_sid = workflow_node_current_state($entity, $field_name);
 }

    // Add the 'current value' formatter for this field.
    // $list_display = $instance['display']['default'];
    $list_display['type'] = 'list_default';
    // Generate a renderable array for the field. Use default language determination ($langcode = NULL).
   $element = $entity->$field_name->view($list_display);

  // Below: the old-school formatter, just for reference.
/*
    $current_state = WorkflowState::load($current_sid);
    $element = array(
      '#type' => 'item',
      '#title' => t('Current state'),
      '#markup' => $current_state ? $current_state->label() : 'unknown state',
      '#value' => $current_sid,
      '#default_value' => $current_sid,
    );
*/

  // TODO D8: make weight better
    // Make sure the current value is before the form. (which has weight = 0.005)
//    $element['#weight'] = 0;

  return $element;
}

/**
 * Implements hook_field_formatter_view().
 *
 * Shows current State and Widget on an Entity View page or a Workflow History tab.
 */
function workflowfield_field_formatter_view($entity_type, $entity, $field, $instance, $langcode = Language::LANGCODE_NOT_SPECIFIED, $items = array(), $display = array()) {
  workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  $user = \Drupal::currentUser(); // @todo #2287057: OK?
  // @todo: Perhaps global user is not always the correct user.
  // E.g., on ScheduledTransition->execute()? But this function is mostly used in UI.

  $field_name = isset($field['field_name']) ? $field['field_name'] : '';
  $field_id = isset($field['id']) ? $field['id'] : 0;

  $current_sid = workflow_node_current_state($entity, $field_name);
  $current_state = workflow_state_load_single($current_sid);
  $wid = $current_state->getWorkflow()->id();

  // First compose the current value with the normal formatter from list.module.
  $list_element = workflow_state_formatter($entity, $field_name, $current_sid);

  // Check permission, so that even with state change rights,
  // the form can be suppressed from the entity view (#1893724).
  if (!\Drupal::currentUser()->hasPermission('show ' . $wid . ' transition form')) {
    return $list_element;
  }
  if ($entity_type == 'comment') {
    // No Workflow form allowed on a comment display.
    // (Also, this avoids a lot of error messages.)
    return $list_element;
  }
  // Only build form if user has possible target state(s).
  if (!$current_state->showWidget($entity_type, $entity, $field_name, $user, FALSE)) {
    return $list_element;
  }

  // Add the form/widget to the formatter, and include the nid in the form id,
  // to allow multiple forms per page (in listings, with hook_forms() ).
  // Ultimately, this is a wrapper for WorkflowDefaultWidget.
  $entity_id = entity_id($entity_type, $entity);
  $form_id = implode('_', array('workflow_transition_form', $entity_type, $entity_id, $field_id));
  $element = \Drupal::formBuilder()->getForm($form_id, $field, $instance, $entity_type, $entity);

  return $element;
}

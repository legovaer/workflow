<?php
/**
 * @file
 * Provide user interface for changing workflow state.
 *
 * @todo D8: remove this in favour of View 'Workflow history per entity'.
 */

define('MARK_STATE_IS_DELETED', '*');

/**
 * Menu callback. Display workflow summary of an entity.
 *
 * N.B. When having multiple workflows per bundle, use Views display
 *      'Workflow history per entity' instead!
 */
function workflow_tab_page($entity_type, $entity = NULL) {
  // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
// 
// 
// @see https://www.drupal.org/node/2067859
// drupal_set_title(entity_label($entity_type, $entity));


  $form = array();
  $field_name = NULL;
  $workflow = NULL;

  // Figure out the $entity's bundle and id.
  list($entity_id, , $entity_bundle) = entity_extract_ids($entity_type, $entity);
  $entity_id = entity_id($entity_type, $entity);

  // Get the current sid. $field_name is updated with relevant value.
  $current_sid = workflow_node_current_state($entity, $field_name);
  $current_state = workflow_state_load_single($current_sid);
  $workflow = $current_state->getWorkflow();

  // Show the current state and the Workflow form to allow state changing.
  // N.B. This part is replicated in hook_entity_view, workflow_tab_page, workflow_vbo, transition_edit.
  // @todo: support multiple workflows per entity.
  // For workflow_tab_page with multiple workflows, use a separate view. See [#2217291].
  $field = _workflow_info_field($field_name, $workflow);
  $field_id = $field['id'];
  $instance = field_info_instance($entity_type, $field_name, $entity_bundle);
  if (!$field_id) {
    // This is a Workflow Node workflow. Set widget options as in v7.x-1.2
    $field['settings']['widget']['comment'] = isset($workflow->options['comment_log_tab']) ? $workflow->options['comment_log_tab'] : 1; // vs. ['comment_log_node'];
    $field['settings']['widget']['current_status'] = TRUE;
  }

  $form_id = implode('_', array('workflow_transition_form', $entity_type, $entity_id, $field_id));
  $form += \Drupal::formBuilder()->getForm($form_id, $field, $instance, $entity_type, $entity);

  $output = \Drupal::service("renderer")->render($form);


  // Show the history table.
  $rows = array();
  $current_themed = FALSE;
  $limit = \Drupal::config('workflow.settings')->get('workflow_states_per_page');
  foreach (workflow_transition_load_multiple($entity_type, array($entity_id), $field_name, $limit) as $history) {
    // Build row is alrady ported to D8.
  }

  // Mark the first and last rows.
  $rows[0]['class'][] = 'first';
  $last = count($rows) - 1;
  $rows[$last]['class'][] = 'last';

  $header = array(t('Date'), t('Old State'), t('New State'), t('By'), t('Comment'));
  $header[] = array('data' => t('Operations'));

  // Only display the table if there's anything in it.
  if ($rows) {
    // Already ported to D8
  }
  return $output;
}

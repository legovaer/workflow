<?php
/**
 * @file
 * Contains helper functons for WorkflowTransitionForm.
 */

/**
 * Getter/Setter to tell if the action buttons are used.
 *
 * @param bool $new_value
 *   Options. If TRUE/FALSE, the value is set. If NULL, value is only 'getted'.
 *
 * @return bool
 *   Previous value. If TRUE, action buttons must be created.
 *
 * @see workflow_form_alter()
 * @see WorkflowDefaultWidget::formElement()
 *
 * Used to save some expensive operations on every form.
 */
function _workflow_use_action_buttons($new_value = NULL) {
//  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  global $_workflow_use_actions_buttons;

  $old_value = $_workflow_use_actions_buttons ? TRUE : FALSE;
  // Reset value if requested.
  if ($new_value !== NULL) {
    $_workflow_use_actions_buttons = $new_value;
  }
  return $old_value;
}

/**
 * Form builder. Move action buttons next to the 'Save'/'Delete' buttons.
 *
 * This is only used if the set the 'options widget' to 'action buttons'.
 * Do not use with multiple workflows per entity: confusing UX.
 * ATM this works for:
 * - Workflow Field: create, edit, view, workflow tab, comment;
 * - Workflow Node: view, workflow tab;
 * (For forms with Workflow Node, the form_alter() is AFTER formElement(). )
 *
 * @todo: move this to WorkflowTransitionForm::_addActionButtons();
 */
function workflow_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
  // Use a fast, custom way to check if we need to do this.
  // @todo: Adapt to use with multiple workflows per entity.
  if (!_workflow_use_action_buttons()) {
    return;
  }

  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  // Find the first workflow.
  // (So this won't work with multiple workflows per entity.)
  $workflow_form = array();

  $field_name = '';
  foreach (\Drupal\Core\Render\Element::children($form) as $key) {
    if (isset($form[$key]['#language'])) {
      $langcode = $form[$key]['#language'];
      if (isset($form[$key][$langcode][0]['workflow'])) {
        // Reference the Workflow Form, we'll remove the buttons later.
        $field_name = $key;
        $langcode = $form[$field_name]['#language'];
        $workflow_form = &$form[$key][$langcode][0]['workflow'];
        // Stop looping if found.
        break;
      }
    }
  }

  // Quit if there is no Workflow on this page.
  if (!$workflow_form ) {
    return;
  }

  // Quit if there are no Workflow Action buttons.
  // (If user has only 1 workflow option, there are no Action buttons.)
  if (count($workflow_form['workflow_sid']['#options']) <= 1) {
    return;
  }

  if (isset($form['actions']['submit'])) {
    // Find the default submit button and add our action buttons before it.
    // @todo: we may loose properties from 'submit' button.
    $submit_action = $form['actions']['submit'];

    $current_sid = $workflow_form['workflow_sid']['#default_value'];

    // The options widget is no longer valid, so hide it.
    $element['workflow']['workflow_sid']['#type'] = 'hidden';

    // Get the min weight for our buttons.
    $option_weight = isset($submit_action['#weight']) ? $submit_action['#weight'] : 0;
    $option_weight = $option_weight - count($workflow_form['workflow_sid']['#options']);
    $min_weight = $option_weight;
    foreach ($workflow_form['workflow_sid']['#options'] as $sid => $option_name) {
      // Make the workflow button act exactly like the original submit button.

      $workflow_submit_button = array(
        '#type' => 'submit',
//        '#access' => TRUE,
        '#value' => $option_name,
        // Keep option order. Put current state first.
        '#weight' => ($sid == $current_sid) ? $min_weight : ++$option_weight,
        '#attributes' => ($sid == $current_sid) ? array('class' => array('form-save-default-button')) : array(),
        // Add target State ID and Field name, to set correct value in validate_buttons callback.
        '#workflow_sid' => $sid,
        '#workflow_field_name' => $field_name,
        // Add validate and submit functions. The will be adapted a bit below.
        '#validate' => array('_workflow_transition_form_validate_buttons'),
        // '#executes_submit_callback' => TRUE,
      );

      // Append the form's #validate function, or it won't be called upon submit,
      // because the workflow buttons have its own #validate.
      if (isset($submit_action['#validate'])) {
        $workflow_submit_button['#validate'] = $submit_action['#validate'];
      }
      elseif (isset($form['#validate'])) {
        $workflow_submit_button['#validate'] = $form['#validate'];
      }
      $workflow_submit_button['#validate'][] = '_workflow_transition_form_validate_buttons';

      // Append the submit-buttons's #submit function, or it won't be called upon submit.
      if (isset($submit_action['#submit'])) {
        $workflow_submit_button['#submit'] = $submit_action['#submit'];
      }
      elseif (isset($form['#submit'])) {
        $workflow_submit_button['#submit'] = $form['#submit'];
      }

      // Place the button with the other action buttons.
      $form['actions']['workflow_' . $sid] = $workflow_submit_button;
    }

    // Remove the default submit button from the form.
    unset($form['actions']['submit']);
    // Remove the submit button from the workflow form.
    unset($workflow_form['actions']);
    // Remove the options element.
    unset($workflow_form['workflow_sid']);
  }
}

/**
 * Submit callback function for the Workflow Form / DefaultWidget.
 *
 * Validate target state and either save a transition immediately or schedule
 * a transition to be executed later by cron.
 */
function workflow_transition_form_validate($form, &$form_state) {
  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  // Empty. But required.
  // $transition_form = WorkflowTransitionForm::create($field, $instance, $entity_type, $entity);
  // return $transition_form->submitForm($form, $form_state, $items);
}

/**
 * Submit callback function for the Workflow Form / DefaultWidget.
 *
 * Validate target state and either save a transition immediately or schedule
 * a transition to be executed later by cron.
 */
function workflow_transition_form_submit($form, &$form_state) {
  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  // Retrieve the data from the form.
  if (isset($form_state['values']['workflow_field'])) {
    // If $entity filled: We are on a Entity View page or Workflow History Tab page.
    // If $entity empty: We are on an Advanced Action page.
    $field = $form_state['values']['workflow_field'];
    $instance = $form_state['values']['workflow_instance'];
    $entity_type = $form_state['values']['workflow_entity_type'];
    $entity = $form_state['values']['workflow_entity'];

    $items = array();
    $transition_form = WorkflowTransitionForm::create($field, $instance, $entity_type, $entity);
    return $transition_form->submitForm($form, $form_state, $items);
  }
  else {
    \Drupal::logger('workflow')->notice('workflow_transition_form_submit() is called with error.', []);
    // We are on an Entity/Comment Form page.
    // We should not be here.
  }

}

/**
 * Submit callback function for the Workflow Form / DefaultWidget.
 *
 * Validate form data for 'time' element.
 */
function _workflow_transition_form_element_validate_time($element, &$form_state, $form) {
//  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
  if (!strtotime($element['#value'])) {
    form_error($element, t('Please enter a valid value for time.'));
  }
}

/**
 * Submit callback function for the Workflow Form / DefaultWidget.
 *
 * This is only used when using action buttons in Workflow form.
 * It sets the new state to proper
 * element and sets a submit function if needed, making sure the action is
 * executed, influencing function core/includes/form.inc/form_execute_handlers().
 * (While constructing the Workflow form, we were not yet aware of the submit
 * buttons of the complete form. We try to correct this here, without adding
 * another hook_form_alter. We guess the first button is the Save button.
 */
function _workflow_transition_form_validate_buttons($form, &$form_state) {
  workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

  $field_name = $form_state['triggering_element']['#workflow_field_name'];
  $new_sid = $form_state['triggering_element']['#workflow_sid'];

  $langcode = Language::LANGCODE_NOT_SPECIFIED;
  // Retrieve the data from the form.
  if (isset($form_state['values']['workflow_field'])) {
    // We are on a Entity View page or Workflow History Tab page.
    $entity_type = $form_state['values']['workflow_entity_type'];
    $entity = $form_state['values']['workflow_entity'];

    $langcode = _workflow_metadata_workflow_get_properties($entity, array(), 'langcode', $entity_type, $field_name);
  }

  /*
    if (isset($form_state['triggering_element']['#submit'])) {
      // We are on a View page or History tab. Try to fix the form_state.
    else {
      // We are on a Entity/Comment form. Try to fix the form_state.
    }
   */

  if ($field_name) {
    $form_state['input']['workflow_sid'] = $new_sid;
    $form_state['values'][$field_name][$langcode][0]['workflow']['workflow_sid'] = $new_sid;
  }
  else {
    $form_state['input']['workflow_sid'] = $new_sid;
    $form_state['values']['workflow_sid'] = $new_sid;
  }
}

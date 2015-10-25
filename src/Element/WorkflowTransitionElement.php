<?php

/**
 * @file
 * Contains Drupal\workflow\Element\WorkflowTransitionElement.
 */

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\workflow\Entity\WorkflowScheduledTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a form element for the WorkflowTransitionForm and ~Widget.
 *
 * Properties:
 * - #return_value: The value to return when the checkbox is checked.
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 *
 * @FormElement("workflow_transition")
 */
class WorkflowTransitionElement extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#return_value' => 1,
      '#process' => array(
        array($class, 'processTransition'),
        array($class, 'processAjaxForm'),
//        array($class, 'processGroup'),
      ),
      '#element_validate' => array(
        array($class, 'validateTransition'),
      ),
      '#pre_render' => array(
        array($class, 'preRenderTransition'),
//        array($class, 'preRenderGroup'),
      ),
//      '#theme' => 'input__checkbox',
//      '#theme' => 'input__textfield',
      '#theme_wrappers' => array('form_element'),
//      '#title_display' => 'after',
    );
  }

  /**
   * Form element validation handler.
   *
   * Note that #maxlength is validated by _form_validate() already.
   *
   * This checks that the submitted value:
   * - Does not contain the replacement character only.
   * - Does not contain disallowed characters.
   * - Is unique; i.e., does not already exist.
   * - Does not exceed the maximum length (via #maxlength).
   * - Cannot be changed after creation (via #disabled).
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $complete_form
   */
  public static function validateTransition(&$element, FormStateInterface $form_state, &$complete_form) {
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
  }

  /**
   * Generate an element.
   *
   * This function is referenced in the Annoteation for this class.
   */
  public static function processTransition(&$element, FormStateInterface $form_state, &$complete_form) {
    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    return self::transitionElement($element, $form_state, $complete_form);
  }

  /**
   * Generate an element.
   *
   * This function is an internal function, to be reused in:
   * - TransitionElement
   * - TranstitionDefaultWidget
   *
   * Usage:
   *  @example $element['#default_value'] = $transition;
   *  @example $element += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
   */
  public static function transitionElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // $element = [];

    /*
     * Input.
     */
    // A Transition object must have been set explicitly.
    /* @var $transition WorkflowTransitionInterface */
    $transition = $element['#default_value'];
    /* @var $user \Drupal\Core\Session\AccountInterface */
    $user = \Drupal::currentUser();
    $force = FALSE;

    /*
     * Derived input.
     */
    $workflow = $transition->getWorkflow();
    $wid = ($workflow) ? $workflow->id() : '';
    $entity = $transition->getEntity();
    $entity_type = ($entity) ? $transition->getEntity()->getEntityTypeId() : '';
    $entity_id = ($entity) ? $transition->getEntity()->id() : '';
    $field_name = $transition->getFieldName();
    if ($transition->isExecuted()) {
      // We are editing an existing/executed/not-scheduled transition.
      // Only the comments may be changed!

      // You may not schedule an existing Transition.
      $scheduled = FALSE;

      $current_sid = $from_sid = $transition->getFromSid();
      $from_state = $transition->getFromState();
      // The states may not be changed anymore.
      $options = array($transition->getToSid() => $transition->getToState()->label());
      // We do need to see the comment section of the widget.
      $show_widget = TRUE;
      $default_value = $transition->getToSid();
    }
    elseif ($entity) {
      // E.g., on VBO-page, no entity may be given.

      // TODO D8-port: load Scheduled transitions, only for existing entities.
      // Get the scheduling info. This may change the $default_value on the Form.
      // Read scheduled information, only if an entity exists.
      // Technically you could have more than one scheduled, but this will only add the soonest one.
      // @todo?: Read the history with an explicit langcode.
      $langcode = ''; // $entity->language()->getId();
      if ($entity_id && $scheduled_transition = WorkflowScheduledTransition::loadByProperties($entity_type, $entity_id, [], $field_name, $langcode)) {
        $transition = $scheduled_transition;
      }

      $current_sid = $from_sid = $transition->getFromSid();
      $from_state = $transition->getFromState();
      // The states may not be changed anymore.
      $options = (!$transition->isExecuted() && $from_state) ? $from_state->getOptions($entity, $field_name, $user, $force) : [];
      $show_widget = ($from_state) ? $from_state->showWidget($entity, $field_name, $user, $force) : [];
      // Determine the default value. If we are in CreationState, use a fast alternative for $workflow->getFirstSid().
      $default_value = ($from_state && $from_state->isCreationState()) ? key($options) : $current_sid;
      $default_value = $transition->isScheduled() ? $transition->getToSid() : $default_value;
    }
    elseif (!$entity) {
      // Sometimes, no entity is given. We encountered the following cases:
      // - D7: the Field settings page,
      // - D7: the VBO action form;
      // - D7/D8: the Advance Action form on admin/config/system/actions;
      // If so, show all options for the given workflow(s).
      if(!$temp_state = $transition->getFromState()) {
        $temp_state = $transition->getToState();
      }
      $options = ($temp_state)
        ? $temp_state->getOptions($entity, $field_name, $user, $force)
        : workflow_get_workflow_state_names($wid, $grouped = TRUE, $all = FALSE);
      $show_widget = TRUE;
      $current_sid = $transition->getToSid(); // TODO
      $default_value = $transition->getToSid();
    }
    else {
      // We are in trouble! A message is already set in workflow_node_current_state().
      $options = array();
      $show_widget = FALSE;
      $default_value = $current_sid;
    }

// Fetch the form ID. This is unique for each entity, to allow multiple form per page (Views, etc.).
// Make it uniquer by adding the field name, or else the scheduling of
// multiple workflow_fields is not independent of eachother.
// IF we are truely on a Transition form (so, not a Node Form with widget)
// then change the form id, too.
    $form_id = 'workflow_transition_form'; // TODO D8-port: add $form_id for widget and History tab.
//    $form_id = $this->getFormId();
//    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    /*
        if (!isset($form_state->getValue('build_info')['base_form_id'])) {
          // Strange: on node form, the base_form_id is node_form,
          // but on term form, it is not set.
          // In both cases, it is OK.
        }
        else {
          workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
          if ($form_state['build_info']['base_form_id'] == 'workflow_transition_wrapper_form') {
            $form_state['build_info']['base_form_id'] = 'workflow_transition_form';
          }
          if ($form_state['build_info']['base_form_id'] == 'workflow_transition_form') {
            $form_state['build_info']['form_id'] = $form_id;
          }
        }
    */

    /*
     * Output: generate the element.
     */
    // Get settings from workflow. @todo : implement default_settings.
    if ($workflow) {
      $workflow_settings = $workflow->options;
      $workflow_label = ($workflow) ? SafeMarkup::checkPlain(t($workflow->label())) : '';
    }
    else {
      // @TODO D8-port: now only tested with Action.
      $workflow_settings = [
        'name_as_title' => 0,
        'options' => "radios",
        'schedule' => 1,
        'schedule_timezone' => 1,
        'comment_log_node' => "1",
        'comment_log_tab' => "1",
        'watchdog_log' => TRUE,
      ];
    }
    // Current sid and default value may differ in a scheduled transition.
    // Set 'grouped' option. Only valid for select list and undefined/multiple workflows.
    $settings_options_type = $workflow_settings['options'];
    $grouped = ($settings_options_type == 'select');

//    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $workflow_settings['comment'] = $workflow_settings['comment_log_node']; // vs. ['comment_log_tab'];
// TODO D8-port: remove following code.
    /*
        // Change settings locally.
        if (!$field_name) {
          // This is a Workflow Node workflow. Set widget options as in v7.x-1.2
          if ($form_state['build_info']['base_form_id'] == 'node_form') {
            $workflow_settings['comment'] = isset($workflow_settings['comment_log_node']) ? $workflow_settings['comment_log_node'] : 1; // vs. ['comment_log_tab'];
            $workflow_settings['current_status'] = TRUE;
          }
          else {
            $workflow_settings['comment'] = isset($workflow_settings['comment_log_tab']) ? $workflow_settings['comment_log_tab'] : 1; // vs. ['comment_log_node'];
            $workflow_settings['current_status'] = TRUE;
          }
        }
    */

    // Capture settings to format the form/widget.
    $settings_title_as_name = !empty($workflow_settings['name_as_title']);
    $settings_options_type = $workflow_settings['options'];
    // The schedule can be hidden via field settings, ...
    $settings_schedule = !empty($workflow_settings['schedule']);
    if ($settings_schedule) {
      // TODO D8-port: check below code: form on VBO.
      $step = $form_state->getValue('step');
      if (isset($step) && ($form_state->getValue('step') == 'views_bulk_operations_config_form')) {
        // On VBO 'modify entity values' form, leave field settings.
        $settings_schedule = TRUE;
      }
      else {
        // ... and cannot be shown on a Content add page (no $entity_id),
        // ...but can be shown on a VBO 'set workflow state to..'page (no entity).
        $settings_schedule = !($entity && !$entity_id);
      }
    }

    $settings_schedule_timezone = !empty($workflow_settings['schedule_timezone']);
    // Show comment, when both Field and Instance allow this.
    $settings_comment = $workflow_settings['comment'];

    $transition_is_scheduled = $transition->isScheduled();
    // Save the current value of the entity in the form, for later Workflow-module specific references.
    // We add prefix, since #tree == FALSE.
    $element['workflow']['workflow_field_name'] = array(
      '#type' => 'value',
      '#value' => $field_name,
    );
    $element['workflow']['workflow_transition'] = array(
      '#type' => 'value',
      '#value' => $transition,
    );
//    // Save the form_id, so the form values can be retrieved in submit function.
//    $element['workflow']['form_id'] = array(
//      '#type' => 'value',
//      '#value' => $form_id,
//    );

//    // Add the default value in the place where normal fields
//    // have it. This is to cater for 'preview' of the entity.
//    $element['#default_value'] = $default_value;

// Decide if we show a widget or a formatter.
// There is no need for a widget when the only option is the current sid.

    // Add a state formatter before the rest of the form,
    // when transition is scheduled or widget is hidden.
    if ( (!$show_widget) || $transition_is_scheduled || $transition->isExecuted()) {
      $element['workflow_current_state'] = workflow_state_formatter($entity, $field_name, $current_sid);
      // Set a proper weight, which works for Workflow Options in select list AND action buttons.
      $element['workflow_current_state']['#weight'] = -0.005;
    }

    $element['workflow']['#tree'] = TRUE;
    $element['workflow']['#attributes'] = array('class' => array('workflow-form-container'));
    if (!$show_widget) {
      // Show no widget.
      $element['workflow']['workflow_to_sid']['#type'] = 'value';
      $element['workflow']['workflow_to_sid']['#value'] = $default_value;
      $element['workflow']['workflow_comment']['#type'] = 'value';
      $element['workflow']['workflow_comment']['#value'] = '';

      return $element; // <---- exit.
    }
    else {
      // TODO: repair the usage of $settings_title_as_name: no container if no details (schedule/comment).
      // Prepare a UI wrapper. This might be a fieldset.
      $element['workflow']['#type'] = 'container'; // 'details';
//      $element['workflow']['#type'] = 'details';
//      $element['workflow']['#description'] = $settings_title_as_name ? t('Change !name state', array('!name' => $workflow_label)) : t('Target state');
//      $element['workflow']['#open'] = TRUE; // Controls the HTML5 'open' attribute. Defaults to FALSE.

      // The 'options' widget. May be removed later if 'Action buttons' are chosen.
      // The help text is not available for container. Let's add it to the
      // State box.
      $help_text = $element['#description'];
      $element['workflow']['workflow_to_sid'] = array(
        '#type' => ($wid) ? $settings_options_type : 'select', // Avoid error with grouped options.
        '#title' => ($settings_title_as_name && !$transition->isExecuted()) ? t('Change !name state', array('!name' => $workflow_label)) : t('Target state'),
        '#options' => $options,
        // '#name' => $workflow_label,
        // '#parents' => array('workflow'),
        '#default_value' => $default_value,
        '#description' => $help_text,
      );
    }

    // Display scheduling form, but only if new entity is being edited and user
    // has permission. State change cannot be scheduled at entity creation
    // because that leaves the entity in the (creation) state.
    $type_id = ($workflow) ? $workflow->id() : ''; // Might be empty on Action configuration.
    if ($settings_schedule == TRUE && !$transition->isExecuted() && $user->hasPermission("schedule $type_id workflow_transition")) {
      $timezone = $user->getTimeZone();

      $timezone_options = array_combine(timezone_identifiers_list(), timezone_identifiers_list());
      $timestamp = $transition ? $transition->getTimestamp() : REQUEST_TIME;
      $hours = (!$transition_is_scheduled) ? '00:00' : format_date($timestamp, 'custom', 'H:i', $timezone);
      $element['workflow']['workflow_scheduled'] = array(
        '#type' => 'radios',
        '#title' => t('Schedule'),
        '#options' => array(
          '0' => t('Immediately'),
          '1' => t('Schedule for state change'),
        ),
        '#default_value' => $transition_is_scheduled ? '1' : '0',
        '#attributes' => array(
          'id' => 'scheduled_' . $form_id,
        ),
      );
      $element['workflow']['workflow_scheduled_date_time'] = array(
        '#type' => 'details', // 'container',
        '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
        '#attributes' => array('class' => array('container-inline')),
        '#prefix' => '<div style="margin-left: 1em;">',
        '#suffix' => '</div>',
        '#states' => array(
          'visible' => array(':input[id="' . 'scheduled_' . $form_id . '"]' => array('value' => '1')),
        ),
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date'] = array(
        '#type' => 'date',
        '#prefix' => t('At'),
        '#default_value' => implode( '-', array(
            'year' => date('Y', $timestamp),
            'month' => date('m', $timestamp),
            'day' => date('d', $timestamp),
          )
        )
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_hour'] = array(
        '#type' => 'textfield',
        '#title' => t('Time'),
        '#maxlength' => 7,
        '#size' => 6,
        '#default_value' => $hours,
        '#element_validate' => array('_workflow_transition_form_element_validate_time'), // @todo D8-port: this is not called.
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_timezone'] = array(
        '#type' => $settings_schedule_timezone ? 'select' : 'hidden',
        '#title' => t('Time zone'),
        '#options' => $timezone_options,
        '#default_value' => array($timezone => $timezone),
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_help'] = array(
        '#type' => 'item',
        '#prefix' => '<br />',
        '#description' => t('Please enter a time.
          If no time is included, the default will be midnight on the specified date.
          The current time is: @time.', array('@time' => format_date(REQUEST_TIME, 'custom', 'H:i', $timezone))
        ),
      );
    }

    $element['workflow']['workflow_comment'] = array(
      '#type' => $settings_comment == '0' ? 'hidden' : 'textarea',
      '#required' => $settings_comment == '2',
      '#title' => t('Workflow comment'),
      '#description' => t('A comment to put in the workflow log.'),
      '#default_value' => $transition ? $transition->getComment() : '',
      '#rows' => 2,
    );

    // TODO D8: make transition fieldable.
    // Add the fields from the WorkflowTransition.
    // field_attach_form('WorkflowTransition', $transition, $element['workflow'], $form_state);

    // TODO D8-port: test ActionButtons.
    // Finally, add Submit buttons/Action buttons.
    // Either a default 'Submit' button is added, or a button per permitted state.
    if ($settings_options_type == 'buttons') {
      // How do action buttons work? See also d.o. issue #2187151.
      // Create 'action buttons' per state option. Set $sid property on each button.
      // 1. Admin sets ['widget']['options']['#type'] = 'buttons'.
      // 2. This function formElelent() creates 'action buttons' per state option;
      //    sets $sid property on each button.
      // 3. User clicks button.
      // 4. Callback _workflow_transition_form_validate_buttons() sets proper State.
      // 5. Callback _workflow_transition_form_validate_buttons() sets Submit function.
      // @todo: this does not work yet for the Add Comment form.

      // Performance: inform workflow_form_alter() to do its job.
      _workflow_use_action_buttons(TRUE);
    }

    $submit_functions = empty($instance['widget']['settings']['submit_function']) ? array() : array($instance['widget']['settings']['submit_function']);
    if ($settings_options_type == 'buttons' || $submit_functions) {
      workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
      $element['workflow']['actions']['#type'] = 'actions';
      $element['workflow']['actions']['submit'] = array(
        '#type' => 'submit',
//        '#access' => TRUE,
        '#value' => t('Update workflow'),
        '#weight' => -5,
//        '#submit' => array( isset($instance['widget']['settings']['submit_function']) ? $instance['widget']['settings']['submit_function'] : NULL),
        // '#executes_submit_callback' => TRUE,
        '#attributes' => array('class' => array('form-save-default-button')),
      );

      // The 'add submit' can explicitely set by workflowfield_field_formatter_view(),
      // to add the submit button on the Content view page and the Workflow history tab.
      // Add a submit button, but only on Entity View and History page.
      // Add the submit function only if one provided. Set the submit_callback accordingly.
      if ($submit_functions) {
        $element['workflow']['actions']['submit']['#submit'] = $submit_functions;
      }
      else {
        // '#submit' Must be empty, or else the submit function is not called.
        // $element['workflow']['actions']['submit']['#submit'] = array();
      }
    }
    else {
      // In some cases, no submit callback function is specified. This is
      // explicitly done on e.g., the entity edit form, because the workflow form
      // is 'just a field'.
      // So, no Submit button is to be shown.
    }

    return $element;
  }

  /**
   * Implements ContentEntityForm::copyFormValuesToEntity(), and is called from:
   * - WorkflowTransitionForm::buildEntity()
   * - WorkflowDefaultWidget
   *
   * N.B. in contrary to ContentEntityForm::copyFormValuesToEntity(),
   * - parameter 1 is returned as result, to be able to create a new Transition object.
   * - parameter 3 is not $form_state (from Form), but an $item array (from Widget).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param array $form
   * @param array $item
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   */
  static public function copyFormItemValuesToEntity(EntityInterface $entity, array $form, array $item) {

    /**
     * Input
     */
    $user = \Drupal::currentUser(); // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.
    /* @var $transition WorkflowTransitionInterface */
    $transition = $entity;

    /**
     * Derived input
     */
    // Make sure we have a subset ['workflow'] with subset ['workflow']['workflow_scheduled_date_time']
    if (isset($item['workflow']['workflow_to_sid'])) {
      // In WorkflowTransitionForm, we receive the complete $form_state.
      $transition_values = $item['workflow'];
      // Remember, the workflow_scheduled element is not set on 'add' page.
      if ($scheduled = !empty($transition_values['workflow_scheduled'])) {
        $schedule_values = $item['workflow']['workflow_scheduled_date_time'];
      }
    }
    else {
      $entity_id = $transition->getEntity()->id();
      drupal_set_message(t('Error: content !id has no workflow attached. The data is not saved.', array('!id' => $entity_id)), 'error');
      // The new state is still the previous state.
      return $transition;
    }

    // Get user input from element.
    $to_sid = $transition_values['workflow_to_sid'];
    $comment = $transition_values['workflow_comment'];
    $force = FALSE;

// @todo D8: add the VBO use case.
//    // Determine if the transition is forced.
//    // This can be set by a 'workflow_vbo action' in an additional form element.
//     $force = isset($form_state['input']['workflow_force']) ? $form_state['input']['workflow_force'] : FALSE;
//    if (!$entity) {
//      // E.g., on VBO form.
//    }

    // @todo D8-port: add below exception.
/*
    // Extract the data from $items, depending on the type of widget.
    // @todo D8: use MassageFormValues($transition_values, $form, $form_state).
    $old_sid = workflow_node_previous_state($entity, $entity_type, $field_name);
    if (!$old_sid) {
      // At this moment, $old_sid should have a value. If the content does not
      // have a state yet, old_sid contains '(creation)' state. But if the
      // content is not associated to a workflow, old_sid is now 0. This may
      // happen in workflow_vbo, if you assign a state to non-relevant nodes.
      $entity_id = entity_id($entity_type, $entity);
      drupal_set_message(t('Error: content !id has no workflow attached. The data is not saved.', array('!id' => $entity_id)), 'error');
      // The new state is still the previous state.
      $new_sid = $old_sid;
      return $new_sid;
    }
*/

    $timestamp = REQUEST_TIME;
    if ($scheduled) {
      // Fetch the (scheduled) timestamp to change the state.
      // Override $timestamp.
      $scheduled_date_time = implode(' ', array(
          $schedule_values['workflow_scheduled_date'],
          $schedule_values['workflow_scheduled_hour'],
          // $schedule_values['workflow_scheduled_timezone'],
        ));
      $timezone = $schedule_values['workflow_scheduled_timezone'];
      $old_timezone = date_default_timezone_get();
      date_default_timezone_set($timezone);
      $timestamp = strtotime($scheduled_date_time);
      date_default_timezone_set($old_timezone);
      if (!$timestamp) {
        // Time should have been validated in form/widget.
        $timestamp = REQUEST_TIME;
      }
    }

    /**
     * Process
     */

    /*
     * Create a new ScheduledTransition.
     */
    if ($scheduled) {
      $transition_entity = $transition->getEntity();
      $field_name = $transition->getFieldName();
      $from_sid = $transition->getFromSid();
      /* @var $transition WorkflowTransitionInterface */
      $transition = WorkflowScheduledTransition::create(['entity' => $transition_entity, 'field_name' => $field_name, 'from_sid' => $from_sid]);
      $transition->setValues($transition_entity, $field_name, $from_sid, $to_sid, $user->id(), $timestamp, $comment);
    }
    if (!$transition->isExecuted()) {
      // Set new values.
      // When editing an existing Transition, only comments may change.
      $transition->set('to_sid', $to_sid);
      $transition->setOwner($user);
      $transition->setTimestamp($timestamp);
      $transition->schedule($scheduled);
      $transition->force($force);
    }
    $transition->setComment($comment);

    // Explicitely set $entity in case of ScheduleTransition. It is now returned as parameter, not result.
    $entity = $transition;

    return $transition;
  }

}

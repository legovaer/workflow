<?php

/**
 * @file
 * Contains Drupal\workflow\Element\WorkflowTransitionElement.
 */

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\SafeMarkup;
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
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
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
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    return 0;
  }

  /**
   * Prepares a #type 'checkbox' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #return_value, #description, #required,
   *   #attributes, #checked.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderTransition($element) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    return $element;
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
   */
  public static function validateTransition(&$element, FormStateInterface $form_state, &$complete_form) {
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet
  }

  /**
   * Generate an element.
   * Todo D8-port: Sets the ... property of an element.
   */
  public static function processTransition(&$element, FormStateInterface $form_state, &$complete_form) {

    /*
     * Get data from parameters.
     */
    $transition = $element['#default_value'];
    /* @var $user \Drupal\Core\Session\AccountProxyInterface */
    $user = \Drupal::currentUser();
    $force = FALSE;

    /*
     * Get derived data from parameters.
     */
    $entity = $transition->getEntity();
    $field_name = $transition->getFieldName();
    if ($entity) {
      // E.g., on VBO-page, no entity may be given.
      $entity_type = $transition->getEntity()->getEntityTypeId();
      $entity_id = $transition->getEntity()->id();
      $langcode = $entity->language()->getId();

      // TODO D8-port: load Scheduled transitions, only for existing entities.
      // Get the scheduling info. This may change the $default_value on the Form.
      // Read scheduled information, only if an entity exists.
      // Technically you could have more than one scheduled, but this will only add the soonest one.
      if ($entity_id && $scheduled_transition = WorkflowScheduledTransition::loadByProperties($entity_type, $entity_id, [], $field_name, $langcode)) {
        $transition = $scheduled_transition;
      }

      $to_sid = $transition->getToSid();
      $current_sid = $from_sid = $transition->getFromSid();
      $current_state = $from_state = $transition->getFromState();
      // The states may not be changed anymore.
      $options = ($transition->isExecuted()) ? array() : $from_state->getOptions($entity, $field_name, $user, $force);
      $show_widget = $from_state->showWidget($entity, $field_name, $user, $force);
      // Determine the default value. If we are in CreationState, use a fast alternative for $workflow->getFirstSid().
      $default_value = $from_state->isCreationState() ? key($options) : $current_sid;
    }
    elseif (!$entity) {
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

      $to_sid = $transition->getToSid();
      $current_sid = $from_sid = $transition->getFromSid();
      $current_state = $from_state = $transition->getFromState();
      // Sometimes, no entity is given. We encountered the following cases:
      // - the Field settings page,
      // - the VBO action form;
      // - the Advance Action form on admin/config/system/actions;
      // If so, show all options for the given workflow(s).
      // TODO D8-port: deprecate ..._get_names().
      $options = workflow_get_workflow_state_names($wid, $grouped, $all = FALSE);
      $show_widget = TRUE;
      $default_value = $current_sid = isset($items[$delta]->value) ? $items[$delta]->value : '0';
    }
    else {
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
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
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    /*
        if (!isset($form_state->getValue('build_info')['base_form_id'])) {
          // Strange: on node form, the base_form_id is node_form,
          // but on term form, it is not set.
          // In both cases, it is OK.
        }
        else {
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
          if ($form_state['build_info']['base_form_id'] == 'workflow_transition_wrapper_form') {
            $form_state['build_info']['base_form_id'] = 'workflow_transition_form';
          }
          if ($form_state['build_info']['base_form_id'] == 'workflow_transition_form') {
            $form_state['build_info']['form_id'] = $form_id;
          }
        }
    */

    /*
     * Generate the element.
     */
    // Get settings from workflow.
    $workflow = $transition->getWorkflow();
    $workflow_settings = $workflow->options;
    $workflow_label = SafeMarkup::checkPlain(t($workflow->label()));
    // Current sid and default value may differ in a scheduled transition.
    // Set 'grouped' option. Only valid for select list and undefined/multiple workflows.
    $settings_options_type = $workflow_settings['options'];
    $grouped = ($settings_options_type == 'select');

//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
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

    $transition_is_scheduled = ($transition && $transition->isScheduled());
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
    if ( (!$show_widget) || $transition_is_scheduled ) {
      $element['workflow_current_state'] = workflow_state_formatter($entity, $field_name, $current_sid);
      // Set a proper weight, which works for Workflow Options in select list AND action buttons.
      $element['workflow_current_state']['#weight'] = -0.005;
    }

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
      $element['workflow']['#attributes'] = array('class' => array('workflow-form-container'));

      // The 'options' widget. May be removed later if 'Action buttons' are chosen.
      $element['workflow']['workflow_to_sid'] = array(
        '#type' => $settings_options_type,
        '#title' => $settings_title_as_name ? t('Change !name state', array('!name' => $workflow_label)) : t('Target state'),
        '#options' => $options,
        // '#name' => $workflow_label,
        // '#parents' => array('workflow'),
        '#default_value' => $default_value,
      );
    }

    // Display scheduling form, but only if entity is being edited and user has
    // permission. State change cannot be scheduled at entity creation because
    // that leaves the entity in the (creation) state.
    if ($settings_schedule == TRUE && $user->hasPermission('schedule workflow transitions')) {
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// if (variable_get('configurable_timezones', 1) && $user->id() && drupal_strlen($user->timezone)) {
//         $timezone = $user->timezone;
//       }
//       else {
//         $timezone = variable_get('date_default_timezone', 0);
//       }
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
        '#element_validate' => array('_workflow_transition_form_element_validate_time'),
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
//    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
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

}

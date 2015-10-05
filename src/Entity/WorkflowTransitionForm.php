<?php

/**
 * @file
 * Contains \Drupal\workflow\Entity\WorkflowTransitionForm.
 */

namespace Drupal\workflow\Entity;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;

/**
 * Provides a Transition Form to be used in the Workflow Widget.
 */
class WorkflowTransitionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $form_id = '';

    // Compose Form Id from string + Entity Id + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form Id is unique, to allow for multiple forms per page.
    $entity_type = $this->entity->getEntity()->getEntityTypeId();
    $entity_bundle = $this->entity->getEntity()->bundle();
    $entity_id = $this->entity->getEntity()->id();
    $field_name = $this->entity->getFieldName();

    $form_id = implode('_', array('workflow_transition_form', $entity_type, $entity_bundle, $field_name, $entity_id));

    workflow_debug(get_class($this), __FUNCTION__, __LINE__, $form_id);  // @todo D8-port: still test this snippet.

    return $form_id;
  }


  /**
   * {@inheritdoc}
   *
   * This function is called by buildForm().
   */
  public function form(array $form, FormStateInterface $form_state) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // $form = parent::form($form, $form_state);

    /*
     * Get data from parameters.
     */
    $transition = $this->entity;

    /*
     * Generate result.
     */
    // $form = $this->element($form, $form_state, $transition);

    // @TODO D8-port: use a proper WorkflowTransitionElement call.
    $element = [];
    $element['#default_value'] = $transition;
    $form = WorkflowTransitionElement::processTransition($element, $form_state, $form);
    /*
      $form['workflow_transition'] = array(
      '#type' => 'workflow_transition',
//      '#title' => t('Workflow transition TODO D8-port'),
      '#default_value' => $transition,
    );
*/
    return $form;
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
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    /*
     * Get data from parameters.
     */
    // @todo: clean this code up. It is the result of glueing code together.
    global $user; // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.

    $entity = $this->entity;
    $entity_type = $this->entity_type;
    $entity_id = ($entity) ? $entity->id() : 0;

    $field = $this->field;
    $field_name = $field['field_name'];
    $field_id = $field['id'];
    $instance = $this->instance;

    dpm($entity, __FUNCTION__);

    /*
     * Generate the output.
     */
    parent::submitForm($form, $form_state);
    // // Remove button and internal Form API values from submitted values.
    // $form_state->cleanValues();
    // $this->entity = $this->buildEntity($form, $form_state);

    return;  // TODO D8-port

    // Retrieve the data from the form.
    if (isset($form_state['values']['workflow_field'])) {
      // If $entity filled: We are on a Entity View page or Workflow History Tab page.
      // If $entity empty: We are on an Advanced Action page.
//      $field = $form_state['values']['workflow_field'];
//      $instance = $form_state['values']['workflow_instance'];
//      $entity_type = $form_state['values']['workflow_entity_type'];
//      $entity = $form_state['values']['workflow_entity'];
//      $field_name = $field['field_name'];
    }
    elseif (isset($form_state['triggering_element'])) {
      // We are on an Entity/Node/Comment Form page.
      $field_name = $form_state['triggering_element']['#workflow_field_name'];
    }
    else {
      // We are on a Comment Form page.
    }

    // Determine if the transition is forced.
    // This can be set by a 'workflow_vbo action' in an additional form element.
    $force = isset($form_state['input']['workflow_force']) ? $form_state['input']['workflow_force'] : FALSE;

    // Set language. Multi-language is not supported for Workflow Node.
    $langcode = _workflow_metadata_workflow_get_properties($entity, array(), 'langcode', $entity_type, $field_name);

    if (!$entity) {
      // E.g., on VBO form.
    }
    elseif ($field_name) {
      // Save the entity, but only if we were not in edit mode.
      // Perhaps there is a better way, but for now we use 'changed' property.
      // Also test for 'is_new'. When Migrating content, the 'changed' property may be set externally.
      // Caveat: Some entities do not have 'changed' property set.
      if ((!empty($entity->is_new)) || (isset($entity->changed) && $entity->changed == REQUEST_TIME)) {
        // We are in edit mode. No need to save the entity explicitly.

//        // Add the $form_state to the $items, so we can do a getTransition() later on.
//        $items[0]['workflow'] = $form_state['input'];
//        // Create a Transition. The Widget knows if it is scheduled.
//        $widget = new WorkflowDefaultWidget($field, $instance, $entity_type, $entity);
//        $new_sid = $widget->submit($form, $form_state, $items, $force);
      }
      elseif (isset($form_state['input'])) {
        // Save $entity, but only if sid has changed.
        // Use field_attach_update for this? Save always?
        $entity->{$field_name}[$langcode][0]['workflow'] = $form_state['input'];
        entity_save($entity_type, $entity);

        return; // <---- exit!
      }
      else {
        // We are saving a node from a comment.
        $entity->{$field_name}[$langcode] = $items;
        entity_save($entity_type, $entity);

        return; // <---- exit!
      }
    }
    else {
      // For a Node API form, only contrib fields need to be filled.
      // No updating of the node itself.
      // (Unless we need to record the timestamp.)

      // Add the $form_state to the $items, so we can do a getTransition() later on.
      $items[0]['workflow'] = $form_state['input'];
//      // Create a Transition. The Widget knows if it is scheduled.
//      $widget = new WorkflowDefaultWidget($field, $instance, $entity_type, $entity);
//      $new_sid = $widget->submit($form, $form_state, $items, $force);
    }

    // Extract the data from $items, depending on the type of widget.
    // @todo D8: use MassageFormValues($values, $form, $form_state).
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

    // Now, save/execute the transition.
    $transition = $this->getTransition($old_sid, $items, $field_name, $user);
    $force = $force || $transition->isForced();

    // Try to execute the transition. Return $old_sid when error.
    if (!$transition) {
      // This should only happen when testing/developing.
      drupal_set_message(t('Error: the transition from %old_sid to %new_sid could not be generated.'), 'error');
      // The current value is still the previous state.
      $new_sid = $old_sid;
    }
    elseif (!$transition->isScheduled()) {
      // Now the data is captured in the Transition, and before calling the
      // Execution, restore the default values for Workflow Field.
      // For instance, workflow_rules evaluates this.
      if ($field_name) {
        // $items = array();
        // $items[0]['value'] = $old_sid;
        // $entity->{$field_name}[$transition->language] = $items;
      }

      // It's an immediate change. Do the transition.
      // - validate option; add hook to let other modules change comment.
      // - add to history; add to watchdog
      // Return the new State ID. (Execution may fail and return the old Sid.)
      $new_sid = $transition->execute($force);
    }
    else {
      // A scheduled transition must only be saved to the database.
      // The entity is not changed.
      $transition->save();

      // The current value is still the previous state.
      $new_sid = $old_sid;
    }

    // The entity is still to be saved, so set to a 'normal' value.
    if ($field_name) {
      $items = array();
      $items[0]['value'] = $new_sid;
      $entity->{$field_name}[$transition->language] = $items;
    }

    return $new_sid;
  }

  /**
   * Extract WorkflowTransition or WorkflowScheduledTransition from the form.
   *
   * This merely extracts the transition from the form/widget. No validation.
   */
  public function getTransition($old_sid, array $items, $field_name, stdClass $user) {
    workflow_debug(get_class($this), __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $entity_type = $this->entity_type;
    $entity = $this->entity;
    // $entity_id = entity_id($entity_type, $entity);
    $field_name = !empty($this->field) ? $this->field['field_name'] : '';

    if (isset($items[0]['transition'])) {
      // a complete transition was already passed on.
      $transition = $items[0]['transition'];
    }
    else {
      // Get the new Transition properties. First the new State ID.
      if (isset($items[0]['workflow']['workflow_sid'])) {
        // We have shown a workflow form.
        $new_sid = $items[0]['workflow']['workflow_sid'];
      }
      elseif (isset($items[0]['value'])) {
        // We have shown a core options widget (radios, select).
        $new_sid = $items[0]['value'];
      }
      else {
        // This may happen if only 1 option is left, and a formatter is shown.
        $state = workflow_state_load_single($old_sid);
        if (!$state->isCreationState()) {
          $new_sid = $old_sid;
        }
        else {
          // This only happens on workflows, when only one transition from
          // '(creation)' to another state is allowed.
          $workflow = $state->getWorkflow();
          $new_sid = $workflow->getFirstSid($this->entity_type, $this->entity, $field_name, $user, FALSE);
        }
      }
      // If an existing Transition has been edited, $hid is set.
      $hid = isset($items[0]['workflow']['workflow_hid']) ? $items[0]['workflow']['workflow_hid'] : '';
      // Get the comment.
      $comment = isset($items[0]['workflow']['workflow_comment']) ? $items[0]['workflow']['workflow_comment'] : '';
      // Remember, the workflow_scheduled element is not set on 'add' page.
      $scheduled = !empty($items[0]['workflow']['workflow_scheduled']);
      if ($hid) {
        // We are editing an existing transition. Only comment may be changed.
        $transition = workflow_transition_load($hid);
        $transition->comment = $comment;
      }
      elseif (!$scheduled) {
        $transition = new WorkflowTransition();
        $transition->setValues($entity_type, $entity, $field_name, $old_sid, $new_sid, $user->uid, REQUEST_TIME, $comment);
      }
      else {
        // Schedule the time to change the state.
        // If Field Form is used, use plain values;
        // If Node Form is used, use fieldset 'workflow_scheduled_date_time'.
        $schedule = isset($items[0]['workflow']['workflow_scheduled_date_time']) ? $items[0]['workflow']['workflow_scheduled_date_time'] : $items[0]['workflow'];
        if (!isset($schedule['workflow_scheduled_hour'])) {
          $schedule['workflow_scheduled_hour'] = '00:00';
        }

        $scheduled_date_time
          = $schedule['workflow_scheduled_date']['year']
          . substr('0' . $schedule['workflow_scheduled_date']['month'], -2, 2)
          . substr('0' . $schedule['workflow_scheduled_date']['day'], -2, 2)
          . ' '
          . $schedule['workflow_scheduled_hour']
          . ' '
          . $schedule['workflow_scheduled_timezone'];

        if ($timestamp = strtotime($scheduled_date_time)) {
          $transition = new WorkflowScheduledTransition();
          $transition->setValues($entity_type, $entity, $field_name, $old_sid, $new_sid, $user->uid, $timestamp, $comment);
        }
        else {
          $transition = NULL;
        }
      }
    }
    return $transition;
  }

}

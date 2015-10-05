<?php

/**
 * @file
 * Contains \Drupal\workflowfield\Plugin\Field\FieldWidget\WorkflowDefaultWidget.
 */

namespace Drupal\workflowfield\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowScheduledTransition;
use Drupal\workflow\Entity\WorkflowTransitionForm;

/**
 * Plugin implementation of the 'workflow_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_default",
 *   label = @Translation("Workflow transition form"),
 *   field_types = {
 *     "workflow"
 *   },
 * )
 */
class WorkflowDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
//      'workflow_default' => array(
//        'label' => t('Workflow'),
//        'field types' => array('workflow'),
//        'settings' => array(
//          'name_as_title' => 1,
//          'comment' => 1,
//        ),
//      ),
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = array();
    // There are no settings. All is done at Workflow level.
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    // There are no settings. All is done at Workflow level.
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // Field ID contains entity_type, bundle, field_name.
    $field_id = $this->fieldDefinition->id();
    $entity_id = '';  // TODO D8-port

    $form_id = implode('_', array('workflow_transition_form', $entity_id, $field_id));
    return $form_id;
  }

  /**
   * {@inheritdoc}
   *
   * Be careful: Widget may be shown in very different places. Test carefully!!
   *  - On a entity add/edit page
   *  - On a entity preview page
   *  - On a entity view page
   *  - On a entity 'workflow history' tab
   *  - On a comment display, in the comment history
   *  - On a comment form, below the comment history
   *
   * @todo D8: change "array $items" to "FieldInterface $items"
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $wid = $this->getFieldSetting('workflow_type');
    $workflow = Workflow::load($wid);
    if (!$workflow){
      // @todo: add error message.
      return $element;
    }

    /* @var $items \Drupal\workflowfield\Plugin\Field\FieldType\WorkflowItem[] */
    /* @var $item \Drupal\workflowfield\Plugin\Field\FieldType\WorkflowItem */
    $item = $items[$delta];
    /* @var $field_config \Drupal\field\Entity\FieldConfig */
    $field_config = $items[$delta]->getFieldDefinition();
    /* @var $field_storage \Drupal\field\Entity\FieldStorageConfig */
    $field_storage = $field_config->getFieldStorageDefinition();
    // $field = $field_storage->get('field');
    $field_name = $field_storage->get('field_name');
    /* @var $entity_type \Drupal\Core\Entity\EntityTypeInterface */
    $entity_type = $field_storage->get('entity_type');
    $entity = $item->getEntity();
    $entity_id = $entity->id();
    /* @var \Drupal\Core\Session\AccountProxyInterface */
    $user = \Drupal::currentUser();

    $force = FALSE;

    /* @var $transition WorkflowTransition */
    $transition = NULL;

    // TODO D8-port: below part of code: $transition = $form_state['WorkflowTransition']
    /*
        $transition = $form_state->getValue('WorkflowTransition');
        if (isset($transition)) {
          dpm('TODO D8-port (with transition: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__);
          // If provided, get data from WorkflowTransition.
          // This happens when calling entity_ui_get_form(), like in the
          // WorkflowTransition Comment Edit form.
          $transition = $form_state['WorkflowTransition'];

    //      $field_name = $transition->field_name;
    //      $workflow = $transition->getWorkflow();
    //      $wid = $transition->wid;

    //      $entity = $this->entity = $transition->getEntity();
    //      $entity_type = $this->entity_type = $transition->entity_type;
    //      $entity_id = entity_id($entity_type, $entity);

          // Show the current state and the Workflow form to allow state changing.
          // N.B. This part is replicated in hook_node_view, workflow_tab_page, workflow_vbo, transition_edit.
          // @todo: support multiple workflows per entity.
          // For workflow_tab_page with multiple workflows, use a separate view. See [#2217291].
          $field = _workflow_info_field($field_name, $workflow); // TODO D8-port: replace by _workflow_info_fields();
    //      $field_id = $field['id'];
          $instance = field_info_instance($entity_type, $field_name, $entity_bundle);
        }
        else {
          // OK. We have all data.
        }
    */
    if ($transition) {

      dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
      /*
            // If a Transition is passed as parameter, use this.
            $current_state = $transition->getOldState();
            if ($transition->isExecuted()) {
              // The states may not be changed anymore.
              $options = array();
            }
            else {
              $options = $current_state->getOptions($entity, $field_name, $user, $force);
            }
            $show_widget = $current_state->showWidget($entity, $field_name, $user, $force);
            $current_sid = $transition->getFromSid();
            $default_value = $transition->getToSid();
            // You may not schedule an existing Transition.
            if ($transition->isExecuted()) {
              $workflow_settings['schedule'] = FALSE;
            }
      */
    }
    elseif (!$entity) {
      dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
      // Sometimes, no entity is given. We encountered the following cases:
      // - the Field settings page,
      // - the VBO action form;
      // - the Advance Action form on admin/config/system/actions;
      // If so, show all options for the given workflow(s).
      // TODO D8-port: deprecate ..._get_names().
      $options = workflow_get_workflow_state_names($wid, $grouped, $all = FALSE);
      $show_widget = TRUE;
      $default_value = $current_sid = $from_sid = isset($items[$delta]->value) ? $items[$delta]->value : '0';
    }
    else {
      /*
       * This happens:
       * - on the Field settings page
       * - ...
       */
      // dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
      $current_sid = $from_sid = workflow_node_current_state($entity, $field_name);
      if ($current_state = WorkflowState::load($current_sid)) {
        $options = $current_state->getOptions($entity, $field_name, $user, $force);
        // Determine the default value. If we are in CreationState, use a fast alternative for $workflow->getFirstSid().
        $default_value = $current_state->isCreationState() ? key($options) : $current_sid;
      }
      else {
        // We are in trouble! A message is already set in workflow_node_current_state().
        $options = array();
        $default_value = $current_sid;
      }
    }

    // Prepare a new transition, if still not provided.
    if (!$transition) {
      $transition = WorkflowTransition::create();
      $transition->setValues($entity, $field_name,
        $from_sid,
        $to_sid = $default_value = '',
        $user->id(),
        REQUEST_TIME,
        $comment = ''
      );
    }

    //@todo D8-port: use proper form calling.
//  $element += $this->entityFormBuilder()->getForm($transition, 'add');
    $element  += WorkflowTransitionForm::element($form, $form_state, $transition);

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Implements workflow_transition() -> WorkflowDefaultWidget::submit().
   *
   * Overrides submit(array $form, array &$form_state).
   * Contains 2 extra parameters for D7
   *
   * @param array $form
   * @param array $form_state
   * @param array $items
   *   The value of the field.
   * @param bool $force
   *   TRUE if all access must be overridden, e.g., for Rules.
   *
   * @return int
   *   If update succeeded, the new State Id. Else, the old Id is returned.
   *
   * This is called from function _workflowfield_form_submit($form, &$form_state)
   * It is a replacement of function workflow_transition($entity, $to_sid, $force, $field)
   * It performs the following actions;
   * - save a scheduled action
   * - update history
   * - restore the normal $items for the field.
   * @todo: remove update of {node_form} table. (separate task, because it has features, too)
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
//    public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser(); // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.

    // Set the new value.
    // Beware: We presume cardinality = 1 !!
    // The widget form element type has transformed the value to a
    // WorkflowTransition object at this point. We need to convert it
    // back to the regular 'value' string format.
    foreach ($values as &$item) {
      if (!empty($item ['workflow']) ) { // } && $item ['value'] instanceof DrupalDateTime) {

        /* @var $field_name string */
        // TODO D8-port: get this from the transition, once all use cases are tested.
        $field_name = $item['workflow']['workflow_field_name'];
        $from_sid = FALSE;
        /* @var $transition \Drupal\workflow\Entity\WorkflowTransitionInterface */
        $transition = $this->getTransition($item, $field_name, $from_sid, $user);

        $force = FALSE;

        // The following can also be retrieved from the WorkflowTransition.
        /* @var $entity Drupal\Core\Entity\EntityInterface */
        $entity = $form_state->getFormObject()->getEntity();
        // Set language.
        $langcode = $entity->language()->getId();

        $wid = $this->getFieldSetting('workflow_type');
        $workflow = Workflow::load($wid);

//    dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__);
        /*
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
        */

        // TODO D8-port:     // Determine if the transition is forced (on the form).
//    dpm('TODO D8-port: test (if transition is forced) function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__);
        /*
            // Determine if the transition is forced (on the form).
            // This can be set by a 'workflow_vbo action' in an additional form element.
            $force = isset($form_state['input']['workflow_force']) ? $form_state['input']['workflow_force'] : FALSE;
        */

        // TODO D8-port: Save entity when not in edit mode.
        /*
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
        //        $item['workflow'] = $form_state['input'];
        //        // Create a Transition. The Widget knows if it is scheduled.
        //        $widget = WorkflowDefaultWidget::create($field, $instance, $entity_type, $entity);
        //        $to_sid = $widget->submit($form, $form_state, $items, $force);
              }
              elseif (isset($form_state['input'])) {
                // Save $entity, but only if sid has changed.
                // Use field_attach_update for this? Save always?
                $entity->{$field_name}[$langcode][0]['workflow'] = $form_state['input'];
                $entity->save();

                return; // <-- exit!
              }
              else {
                // We are saving an entity from a comment.
                $entity->{$field_name}[$langcode] = $items;
                $entity->save();

                return; // <-- exit!
              }
            }
        */

        // Now, save/execute the transition.
        if (!$transition) {
          dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid() .' = '.$to_sid );

          // D8-port: for some reason, after Sumbit, this function is called twice.
          // First time with Transition.
          // Second time without Transition, but correct state value.
          continu;

          dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid() .' = '.$to_sid );

          /*
                    // Extract the data from $items, depending on the type of widget.
                    $from_sid = workflow_node_previous_state($entity, $field_name);
                    if (!$from_sid) {
                      dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__);
                      // At this moment, $from_sid should have a value. If the content does not
                      // have a state yet, from_sid contains '(creation)' state. But if the
                      // content is not associated to a workflow, from_sid is now 0. This may
                      // happen in workflow_vbo, if you assign a state to non-relevant entities.
                      $entity_id = entity_id($entity_type, $entity);
                      drupal_set_message(t('Error: content !id has no workflow attached. The data is not saved.', array('!id' => $entity_id)), 'error');
                      // The new state is still the previous state.
                      $to_sid = $from_sid;
                    }
          */
        }
        else {
          $from_sid = $transition->getFromSid();
          $to_sid = $transition->getToSid();
          $force = $force || $transition->isForced();
        }

        // Try to execute the transition. Return $from_sid when error.
        if (!$transition) {
          dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid() .' = '.$to_sid );
          // This should only happen when testing/developing.
          drupal_set_message(t('Error: the transition from %from_sid to %to_sid could not be generated.'), 'error');
          // The current value is still the previous state.
          $to_sid = $from_sid;
        }
        elseif (!$transition->isScheduled()) {
          // Now the data is captured in the Transition, and before calling the
          // Execution, restore the default values for Workflow Field.
          // For instance, workflow_rules evaluates this.

          // It's an immediate change. Do the transition.
          // - validate option; add hook to let other modules change comment.
          // - add to history; add to watchdog
          // Return the new State ID. (Execution may fail and return the old Sid.)
          $to_sid = $transition->execute($force);
        }
        else {
          /*
           * A scheduled transition must only be saved to the database.
           * The entity is not changed.
           */
          $transition->save();

          // The current value is still the previous state.
          $to_sid = $from_sid;
        }

        // Set the value at the proper location.
        $item['value'] = $to_sid;
      }
    }
    return $values;
  }

  /**
   * Extract WorkflowTransition or WorkflowScheduledTransition from the form.
   *
   * This merely extracts the transition from the form/widget. No validation.
   *
   * @return WorkflowTransitionInterface $transition
   */
  private function getTransition($item, $field_name, $from_sid, AccountProxy $user) {
    /* @var $transition \Drupal\workflow\Entity\WorkflowTransitionInterface */
    $transition = NULL;

    if (isset($item['workflow']['workflow_transition'])) {
      // Normal situation: The original, proposed transition, before the state change.
      $transition = $item['workflow']['workflow_transition'];

      // Get new data.
      $to_sid = $item['workflow']['workflow_to_sid'];
      $comment = $item['workflow']['workflow_comment'];
      // Remember, the workflow_scheduled element is not set on 'add' page.
      $scheduled = !empty($item['workflow']['workflow_scheduled']);

      // Fetch the (scheduled) timestamp to change the state.
      if (!$scheduled) {
        $transition->schedule(FALSE);

        $timestamp = REQUEST_TIME;
      }
      else {
        $transition->schedule(TRUE);

        $scheduled_date_time
          = $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date']
//          = $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date']['year']
//          . substr('0' . $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date']['month'], -2, 2)
//          . substr('0' . $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date']['day'], -2, 2)
          . ' '
          . $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_hour']
//          . ' '
//          . $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_timezone']
        ;
        $timezone = $item['workflow']['workflow_scheduled_date_time']['workflow_scheduled_timezone'];
        $old_timezone = date_default_timezone_get();
        date_default_timezone_set($timezone);
        $timestamp = strtotime($scheduled_date_time);
        date_default_timezone_set($old_timezone);
        if (!$timestamp) {
          dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid());
          $timestamp = REQUEST_TIME;
        }
      }

      // If an existing Transition has been edited, $hid is set.
      $hid = $transition->id();
      if ($hid) {
        // We are editing an existing transition.
        // This can be a scheduled transition or an executed transition.
        $transition->set('to_sid', $to_sid);
        $transition->setUser($user);
        $transition->setTimestamp($timestamp);
        $transition->setComment($comment);
        $transition->setComment($comment);
      }
      elseif (!$scheduled) {
        /*
         * Update the current Transition.
         */
        $transition->set('to_sid', $to_sid);
        $transition->setUser($user);
        $transition->setTimestamp($timestamp);
        $transition->setComment($comment);
      }
      else {
        /*
         * Create a new ScheduledTransition.
         */
        $entity = $transition->getEntity();
        $from_sid = $transition->getFromSid();
        $transition = WorkflowScheduledTransition::create();
        $transition->setValues($entity, $field_name, $from_sid, $to_sid, $user->id(), $timestamp, $comment);
      }
    }
    elseif (isset($item['transition'])) {
      dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid());
      // a complete transition was already passed on.
      $transition = $item['transition'];
    }
    else {
      dpm('TODO D8-port: test function WorkflowDefaultWidget::' . __FUNCTION__.'/'.__LINE__.': '.$transition->getFromSid().' > '.$transition->getToSid());
      /*

            // Get the new Transition properties. First the new State ID.
            if (isset($item['workflow']['workflow_to_sid'])) {
              // We have shown a workflow form.
              $to_sid = $item['workflow']['workflow_to_sid'];
            }
            elseif (isset($item['value'])) {
              // We have shown a core options widget (radios, select).
              $to_sid = $item['value'];
            }
            else {
              // This may happen if only 1 option is left, and a formatter is shown.
              $state = WorkflowState::load($from_sid);
              if (!$state->isCreationState()) {
                $to_sid = $from_sid;
              }
              else {
                // This only happens on workflows, when only one transition from
                // '(creation)' to another state is allowed.
                $workflow = $state->getWorkflow();
                $to_sid = $workflow->getFirstSid($this->entity, $field_name, $user, FALSE);
              }
            }
            // If an existing Transition has been edited, $hid is set.
            $hid = $transition->id();

            // Get the comment.
            $comment = isset($item['workflow']['workflow_comment']) ? $item['workflow']['workflow_comment'] : '';
            // Remember, the workflow_scheduled element is not set on 'add' page.
            $scheduled = !empty($item['workflow']['workflow_scheduled']);
            if ($hid) {
              dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
              // We are editing an existing transition. Only comment may be changed.
              $transition = workflow_transition_load($hid);
              $transition->setComment($comment);
            }
            elseif (!$scheduled) {
              dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
              $transition = $transition ? $transition : WorkflowTransition::create();
              $transition->setValues($entity, $field_name, $from_sid, $to_sid, $user->id(), REQUEST_TIME, $comment);
            }
            else {
              dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
              // Schedule the time to change the state.
              // If Field Form is used, use plain values;
              // If Node Form is used, use fieldset 'workflow_scheduled_date_time'.
              $schedule = isset($item['workflow']['workflow_scheduled_date_time']) ? $item['workflow']['workflow_scheduled_date_time'] : $item['workflow'];
              if (!isset($schedule['workflow_scheduled_hour'])) {
                dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
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
                dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
                $transition = WorkflowScheduledTransition::create();
                $transition->setValues($entity, $field_name, $from_sid, $to_sid, $user->id(), $timestamp, $comment);
              }
              else {
                dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__.'/'.__LINE__);
                $transition = NULL;
              }
            }
      */
    }
    return $transition;
  }

}

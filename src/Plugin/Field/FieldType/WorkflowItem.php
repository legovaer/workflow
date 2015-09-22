<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Field\FieldType\WorkflowItem.
 */

namespace Drupal\workflow\Plugin\Field\FieldType;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldConfigStorageBase;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Plugin implementation of the 'workflow' field type.
 *
 * @FieldType(
 *   id = "workflow",
 *   label = @Translation("Workflow state"),
 *   description = @Translation("This field stores Workflow values for a certain Workflow type from a list of allowed 'value => label' pairs, i.e. 'Publishing': 1 => unpublished, 2 => draft, 3 => published."),
 *   category = @Translation("Workflow"),
 *   default_widget = "workflow_default",
 *   default_formatter = "basic_string",
 * )
 */
class WorkflowItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {

    $schema = array(
      'columns' => array(
        'value' => array(
          'description' => 'The {workflow_states}.sid that this node is currently in.',
          'type' => 'varchar',
          'length' => 128,
//          'unsigned' => TRUE,
//          'not null' => TRUE,
//          'default' => 0,
//          'disp-width' => '10',
        ),
      ),
      // 'primary key' => array('nid'),
      // 'indexes' => array(
      // 'nid' => array('nid', 'sid'),
      // ),
      'indexes' => array(
        'value' => array('value'),
      ),
    );
    return $schema;

  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['value'] = DataDefinition::create('string') // TODO D8-port : or 'any'
      ->setLabel(t('Workflow state'))
      ->setRequired(TRUE);

    /*
    $properties['date'] = DataDefinition::create('WorkflowTransition')
      ->setLabel(t('Computed date'))
      ->setDescription(t('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\datetime\DateTimeComputed')
      ->setSetting('date source', 'value');
*/

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // TODO D8: use this function for...
//    dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

//    // Enforce that the computed date is recalculated.
//    if ($property_name == 'value') {
//      $this->date = NULL;
//    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

//  dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);
/*
    $max_length = 128;
    $constraints[] = $constraint_manager->create('ComplexData', array(
      'value' => array(
        'Length' => array(
          'max' => $max_length,
          'maxMessage' => t('%name: the telephone number may not be longer than @max characters.', array('%name' => $this->getFieldDefinition()->getLabel(), '@max' => $max_length)),
        )
      ),
    ));
*/
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'workflow_type' => '',

// TODO D8-port: below settings may be removed.
      /*
            'allowed_values_function' => 'workflowfield_allowed_values', // For the list.module formatter
            // 'allowed_values_function' => 'WorkflowItem::getAllowedValues', // For the list.module formatter.
            'widget' => array(
              'options' => 'select',
              'name_as_title' => 1,
              'hide' => 0,
              'schedule' => 1,
              'schedule_timezone' => 1,
              'comment' => 1,
            ),
            'watchdog_log' => 1,
            'history' => array(
              'history_tab_show' => 1,
              'roles' => array(),
            ),
      */
    ) + parent::defaultStorageSettings();
  }

  /**
   * Implements hook_field_settings_form() -> ConfigFieldItemInterface::settingsForm().
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = array();

//    dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    // Create list of all Workflow types. Include an initial empty value.
    // Validate each workflow, and generate a message if not complete.
    $workflows = array();
    $workflows[''] = t('- Select a value -');
    foreach (Workflow::LoadMultiple() as $wid => $workflow) {
      if ($workflow->isValid()) {
        $workflows[$wid] = SafeMarkup::checkPlain($workflow->label()); // No t() on settings page.
      }
    }

    // Set message, if no 'validated' workflows exist.
    if (count($workflows) == 1) {
      drupal_set_message(
        t('You must create at least one workflow before content can be
          assigned to a workflow.')
      );
    }

    // Let the user choose between the available workflow types.
    $wid = $this->getSetting('workflow_type');
    $url = Url::fromRoute('entity.workflow_workflow.collection');
    $element['workflow_type'] = array( // TODO D8-port: check this change-record.
      '#type' => 'select',
      '#title' => t('Workflow type'),
      '#options' => $workflows,
      '#default_value' => $wid,
      '#required' => TRUE,
      '#disabled' => $has_data,
      // FIXME TODO D8-port: repair link.
      '#description' => t('Choose the Workflow type. Maintain workflows !url.', array('!url' => t('here'), $url)),
    );

    // Inform the user of possible states.
    // If no Workflow type is selected yet, do not show anything.
    if ($wid) {
      // Get a string representation to show all options.
      $allowed_values_string = $this->_allowed_values_string($wid);
      $element['allowed_values_string'] = array(
        '#type' => 'textarea',
        '#title' => t('Allowed values for the selected Workflow type'),
        '#default_value' => $allowed_values_string,
        '#rows' => 10,
        '#access' => TRUE, // User can see the data,
        '#disabled' => TRUE, // .. but cannot change them.
      );
    }

//    dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    /*
     * TODO D8-port: check if all below features are in WorkflowForm::form().
     */
    return $element;

    /*
    $field_info = self::getInfo();
    $settings = [];
    //TODO     $settings = $this->field['settings'];
    $settings += $field_info['workflow']['settings'];
    $settings['widget'] += $field_info['workflow']['settings']['widget'];

        // The allowed_values_functions is used in the formatter from list.module.
        $element['allowed_values_function'] = array(
          '#type' => 'value',
          '#value' => $settings['allowed_values_function'], // = 'workflowfield_allowed_values',
        );

        $element['widget'] = array(
          '#type' => 'fieldset',
          '#title' => t('Workflow widget'),
          '#description' => t('Set some global properties of the widgets for this
            workflow. Some can be altered per widget instance.'
          ),
        );
        $element['widget']['options'] = array(
          '#type' => 'select',
          '#title' => t('How to show the available states'),
          '#required' => FALSE,
          '#default_value' => $settings['widget']['options'],
          // '#multiple' => TRUE / FALSE,
          '#options' => array(
            // These options are taken from options.module
            'select' => 'Select list',
            'radios' => 'Radio buttons',
            // This option does not work properly on Comment Add form.
            'buttons' => 'Action buttons',
          ),
          '#description' => t("The Widget shows all available states. Decide which
            is the best way to show them. ('Action buttons' do not work on Comment form.)"
          ),
        );
        $element['widget']['hide'] = array(
          '#type' => 'checkbox',
          '#attributes' => array('class' => array('container-inline')),
          '#title' => t('Hide the widget on Entity form.'),
          '#default_value' => $settings['widget']['hide'],
          '#description' => t(
            'Using Workflow Field, the widget is always shown when editing an
            Entity. Set this checkbox in case you only want to change the status
            on the Workflow History tab or on the Node View. (This checkbox is
            only needed because Drupal core does not have a <hidden> widget.)'
          ),
        );
        $element['widget']['name_as_title'] = array(
          '#type' => 'checkbox',
          '#attributes' => array('class' => array('container-inline')),
          '#title' => t('Use the workflow name as the title of the workflow form'),
          '#default_value' => $settings['widget']['name_as_title'],
          '#description' => t(
            'The workflow section of the editing form is in its own fieldset.
             Checking the box will add the workflow name as the title of workflow
             section of the editing form.'
          ),
        );
        $element['widget']['schedule'] = array(
          '#type' => 'checkbox',
          '#title' => t('Allow scheduling of workflow transitions.'),
          '#required' => FALSE,
          '#default_value' => $settings['widget']['schedule'],
          '#description' => t(
            'Workflow transitions may be scheduled to a moment in the future.
             Soon after the desired moment, the transition is executed by Cron.
             This may be hidden by settings in widgets, formatters or permissions.'
          ),
        );
        $element['widget']['schedule_timezone'] = array(
          '#type' => 'checkbox',
          '#title' => t('Show a timezone when scheduling a transition.'),
          '#required' => FALSE,
          '#default_value' => $settings['widget']['schedule_timezone'],
        );
        $element['widget']['comment'] = array(
          '#type' => 'select',
          '#title' => t('Allow adding a comment to workflow transitions'),
          '#required' => FALSE,
          '#options' => array(
            // Use 0/1/2 to stay compatible with previous checkbox.
            0 => t('hidden'),
            1 => t('optional'),
            2 => t('required'),
          ),
          '#default_value' => $settings['widget']['comment'],
          '#description' => t('On the Workflow form, a Comment form can be included
            so that the person making the state change can record reasons for doing
            so. The comment is then included in the node\'s workflow history. This
            may be altered by settings in widgets, formatters or permissions.'
          ),
        );

        $element['watchdog_log'] = array(
          '#type' => 'checkbox',
          '#attributes' => array('class' => array('container-inline')),
          '#title' => t('Log informational watchdog messages when a transition is
            executed (a state value is changed)'),
          '#default_value' => $settings['watchdog_log'],
          '#description' => t('Optionally log transition state changes to watchdog.'),
        );

        $element['history'] = array(
          '#type' => 'fieldset',
          '#title' => t('Workflow history'),
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        );
        $element['history']['history_tab_show'] = array(
          '#type' => 'checkbox',
          '#title' => t('Use the workflow history, and show it on a separate tab.'),
          '#required' => FALSE,
          '#default_value' => $settings['history']['history_tab_show'],
          '#description' => t("Every state change is recorded in table
            {workflow_node_history}. If checked and user has proper permission, a
            tab 'Workflow' is shown on the entity view page, which gives access to
            the History of the workflow. If you have multiple workflows per bundle,
            better disable this feature, and use, clone & adapt the Views display
            'Workflow history per Entity'."),
        );
        $element['history']['roles'] = array(
          '#type' => 'checkboxes',
          '#options' => workflow_get_roles(),
          '#title' => t('Workflow history permissions'),
          '#default_value' => $settings['history']['roles'],
          '#description' => t('Select any roles that should have access to the workflow tab on nodes that have a workflow.'),
        );
    */
    return $element;
  }

  /**
   * Implements hook_field_insert() -> FieldItemInterface::insert().
   */
  public function insertTODO() {
//  dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    return $this->update();
  }

  /**
   * Implements hook_field_update() -> FieldItemInterface::update().
   */

  /**
   * Implements hook_field_update().
   *
   * It is the D7-wrapper for D8-style WorkflowDefaultWidget::submit.
   * It is called also from hook_field_insert, since we need $nid to store workflow_node_history.
   * We cannot use hook_field_presave, since $nid is not yet known at that moment.
   */
  public function updateTODO() {
//    function workflowfield_field_update($entity_type, $entity, array $field, $instance, $langcode, &$items) {
//  dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    $form = array();
    $form_state = array();
    $field_name = $field['field_name'];

    if ($entity_type == 'comment') {
      // This happens when we are on an entity's comment.
      // We save the field of the node. The comment is saved automatically.
      $referenced_entity_type = 'node'; // Comments only exist on nodes.
      $referenced_entity_id = $entity->nid;
      // Load the node again, since the passed node doesn't contain proper 'type' field.
      $referenced_entity = entity_load_single($referenced_entity_type, $referenced_entity_id);
      // Normalize the contents of the workflow field.
      $items[0]['value'] = _workflow_get_sid_by_items($items);

      // Execute the transition upon the node. Afterwards, $items is in form as expected by Field API.
      // Remember, we don't know if the transition is scheduled or not.
      $widget = new WorkflowTransitionForm($field, $instance, $referenced_entity_type, $referenced_entity);
      $widget->submitForm($form, $form_state, $items); // $items is a proprietary D7 parameter.
      // // Since we are saving the comment only, we must save the node separately.
      // entity_save($referenced_entity_type, $referenced_entity);
    }
    else {
      $widget = new WorkflowTransitionForm($field, $instance, $entity_type, $entity);
      $widget->submitForm($form, $form_state, $items); // $items is a proprietary D7 parameter.
    }
  }

  /**
   * Helper functions for the Field Settings page.
   *
   * Generates a string representation of an array of 'allowed values'.
   * This is a copy from list.module's list_allowed_values_string().
   * The string format is suitable for edition in a textarea.
   *
   * @param int $wid
   *   The Workflow Id.
   *
   * @return
   *   The string representation of the $values array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function _allowed_values_string($wid = '') {
    $lines = array();

    $states = WorkflowState::loadMultiple([], $wid);
    $previous_wid = -1;
    foreach ($states as $state) {
      // Only show enabled states.
      if ($state->isActive()) {
        // Show a Workflow name between Workflows, if more then 1 in the list.
        if ((!$wid) && ($previous_wid <> $state->wid)) {
          $previous_wid = $state->wid;
          $workflow = Workflow::load($previous_wid);
          $lines[] = $workflow->label() . "'s states: ";
        }
        $label = SafeMarkup::checkPlain(t($state->label()));
        $lines[] = '   ' . $state->id() . ' | ' . $label;
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Helper function for list.module formatter.
   *
   * Callback function for the list module formatter.
   *
   * @see list_allowed_values
   *   "The strings are not safe for output. Keys and values of the array should
   *   "be sanitized through field_filter_xss() before being displayed.
   *
   * @return array
   *   The array of allowed values. Keys of the array are the raw stored values
   *   (number or text), values of the array are the display labels.
   *   It contains all possible values, beause the result is cached,
   *   and used for all nodes on a page.
   */
  public function getAllowedValues() {
//    dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    // Get all state names, including inactive states.
    $options = workflow_get_workflow_state_names(0, $grouped = FALSE, $all = TRUE);
    return $options;
  }


  /**
   * {@inheritdoc}
   */
//  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
//    $values['value'] = rand(pow(10, 8), pow(10, 9)-1);
//    return $values;
//  }

}

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
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\Url;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;
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
 *   default_formatter = "list_default",
 * )
 */
class WorkflowItem extends ListItemBase {
//class WorkflowItem extends FieldItemBase  implements OptionsProviderInterface {
// TODO D8-port: perhaps even:
//class WorkflowItem extends FieldStringItem {

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
        ),
      ),
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
      ->addConstraint('Length', array('max' => 128))
      ->setRequired(TRUE);

//    TODO D8-port: test this.
    /*
    $properties['date'] = DataDefinition::create('any')
//    $properties['workflow_transition'] = DataDefinition::create('WorkflowTransition')
      ->setLabel(t('Computed date'))
      ->setDescription(t('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\workflow\WorkflowTransition')
      ->setSetting('date source', 'value');

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
    $is_empty = empty($this->value);
    return $is_empty;
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
//      'allowed_values' => array(),
      'allowed_values_function' => 'workflow_state_allowed_values',

// TODO D8-port: below settings may be (re)moved.
      /*
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

    // Create list of all Workflow types. Include an initial empty value.
    // Validate each workflow, and generate a message if not complete.
    $workflows = array();
    // $workflows = workflow_get_workflow_names();
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
//      '#description' => t('Choose the Workflow type. Maintain workflows !url.', array('!url' => t('here'), $url)),
      '#description' => t('Choose the Workflow type. Maintain workflows first.'),
    );

    // Get a string representation to show all options.

    /*
     * Overwrite ListItemBase::storageSettingsForm().
     */
    $allowed_values = WorkflowState::loadMultiple([], $wid);
    $allowed_values_function = $this->getSetting('allowed_values_function');

    $element['allowed_values'] = array(
      '#type' => ($wid) ? 'textarea' : 'hidden',
      '#title' => t('Allowed values for the selected Workflow type'),
      '#default_value' => $this->allowedValuesString($allowed_values),
      '#rows' => 10,
      '#access' => TRUE, // User can see the data,
      '#disabled' => TRUE, // .. but cannot change them.
      '#element_validate' => array(array(get_class($this), 'validateAllowedValues')),

      '#field_has_data' => $has_data,
      '#field_name' => $this->getFieldDefinition()->getName(),
      '#entity_type' => $this->getEntity()->getEntityTypeId(),
      '#allowed_values' => $allowed_values,
    );

    $element['allowed_values']['#description'] = $this->allowedValuesDescription();

//    dpm('TODO D8-port: test function WorkflowItem::' . __FUNCTION__);

    return $element;

//    return $element + parent::storageSettingsForm($form, $form_state, $has_data)

//  TODO D8-port: below settings may be (re)moved.
    /*
    $field_info = self::getInfo();
    $settings = [];
    //TODO     $settings = $this->field['settings'];
    $settings += $field_info['workflow']['settings'];
    $settings['widget'] += $field_info['workflow']['settings']['widget'];

        $element['widget'] = array(
          '#type' => 'details',
          '#title' => t('Workflow widget'),
          '#description' => t('Set some global properties of the widgets for this
            workflow. Some can be altered per widget instance.'
          '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
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
          '#type' => 'details',
          '#title' => t('Workflow history'),
          '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
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
   * {@inheritdoc}
   */
  protected function allowedValuesDescription() {
    return '';
  }

  /*
   * Generates a string representation of an array of 'allowed values'.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param array $states
   *   An array of WorkflowStates, where array keys are values and array values are
   *   labels.
   * @param $wid
   *   A Workflow ID.
   *
   * @return string
   *   The string representation of the $states array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function allowedValuesString($states) {
    $lines = array();

    $wid = $this->getSetting('workflow_type');

    $previous_wid = -1;
    foreach ($states as $key => $state) {
      // Only show enabled states.
      if ($state->isActive()) {
        // Show a Workflow name between Workflows, if more then 1 in the list.
        if ((!$wid) && ($previous_wid <> $state->wid)) {
          $previous_wid = $state->wid;
          $workflow = Workflow::load($previous_wid);
          $lines[] = $workflow->label() . "'s states: ";
        }
        $label = SafeMarkup::checkPlain(t($state->label()));
        $lines[] = '   ' . $key. '|' . $label;
//        $lines[] = "$key|$value";
      }
    }
    return implode("\n", $lines);
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

//  /**
//   * {@inheritdoc}
//   */
//  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
//    // @todo Implement this once https://www.drupal.org/node/2238085 lands.
//    $values['value'] = rand(pow(10, 8), pow(10, 9)-1);
//    return $values;
//  }


  /**
   * @return array
   *   An array of settable options for the object that may be used in an
   *   Options widget, usually when new data should be entered. It may either be
   *   a flat array of option labels keyed by values, or a two-dimensional array
   *   of option groups (array of flat option arrays, keyed by option group
   *   label). Note that labels should NOT be sanitized.
   */

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    // Flatten options firstly, because Possible Options may contain group
    // arrays.
    $flatten_options = OptGroup::flattenOptions($this->getPossibleOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL) {
    $allowed_options = array();

    $definition = $this->getFieldDefinition()->getFieldStorageDefinition();
    $entity = $this->getEntity();
    $cacheable = TRUE;

    // Use the 'allowed_values_function' to calculate the options.
    $allowed_options = workflow_state_allowed_values($definition, $entity, $cacheable, $account);

    return $allowed_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    // Flatten options firstly, because Settable Options may contain group
    // arrays.
    $flatten_options = OptGroup::flattenOptions($this->getSettableOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    $allowed_options = array();

    // Do not show the TransitionForm in the 'Default value' of a Field on
    //  page /admin/structure/types/manage/MY_CONTENT_TYPE/fields/MY_FIELD_NAME .
    $url_components = explode('/', $_SERVER['REQUEST_URI']);
    if (isset($url_components[6])
        && ($url_components[1] == 'admin')
        && ($url_components[2] == 'structure')
        && ($url_components[6] == 'fields')) {
      return $allowed_options = array();
    };

    $definition = $this->getFieldDefinition()->getFieldStorageDefinition();
    $entity = $this->getEntity();
    $cacheable = TRUE;

    // Use the 'allowed_values_function' to calculate the options.
    $allowed_options = workflow_state_allowed_values($definition, $entity, $cacheable, $account);

    return $allowed_options;
  }

}

<?php

/**
 * @file
 * Contains \Drupal\workflowfield\Plugin\Field\FieldType\WorkflowItem.
 */

namespace Drupal\workflowfield\Plugin\Field\FieldType;

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
          'description' => 'The {workflow_states}.sid that this entity is currently in.',
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

    /**
     * Property definitions of the contained properties.
     *
     * @see FileItem::getPropertyDefinitions()
     *
     * @var array
     */
    static $propertyDefinitions;


    $definition['settings']['target_type'] = 'workflow_transition';
    // Definitions vary by entity type and bundle, so key them accordingly.
    $key = $definition['settings']['target_type'] . ':';
    $key .= isset($definition['settings']['target_bundle']) ? $definition['settings']['target_bundle'] : '';

    if (!isset($propertyDefinitions[$key])) {

      $propertyDefinitions[$key]['value'] = DataDefinition::create('string') // TODO D8-port : or 'any'
      ->setLabel(t('Workflow state'))
        ->addConstraint('Length', array('max' => 128))
        ->setRequired(TRUE);

//      workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
/*
 *
       //    TODO D8-port: test this.
      $propertyDefinitions[$key]['workflow_transition'] = DataDefinition::create('any')
        //    $properties['workflow_transition'] = DataDefinition::create('WorkflowTransition')
        ->setLabel(t('Transition'))
        ->setDescription(t('The computed WokflowItem object.'))
        ->setComputed(TRUE)
        ->setClass('\Drupal\workflow\Entity\WorkflowTransition')
        ->setSetting('date source', 'value');

      $propertyDefinitions[$key]['display'] = array(
        'type' => 'boolean',
        'label' => t('Flag to control whether this file should be displayed when viewing content.'),
      );
      $propertyDefinitions[$key]['description'] = array(
        'type' => 'string',
        'label' => t('A description of the file.'),
      );

      $propertyDefinitions[$key]['display'] = array(
        'type' => 'boolean',
        'label' => t('Flag to control whether this file should be displayed when viewing content.'),
      );
      $propertyDefinitions[$key]['description'] = array(
        'type' => 'string',
        'label' => t('A description of the file.'),
      );
*/
    }
    return $propertyDefinitions[$key];
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
//    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    // TODO D8: use this function onChange for adding a line in table workfow_transition_*
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

//    workflow_debug( __FILE__ , __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
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
   * @param WorkflowState[] $states
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
    /* @var $state WorkflowState */
    foreach ($states as $key => $state) {
      // Only show enabled states.
      if ($state->isActive()) {
        // Show a Workflow name between Workflows, if more then 1 in the list.
        if ((!$wid) && ($previous_wid <> $state->getWorkflowId())) {
          $previous_wid = $state->getWorkflowId();
          $workflow = Workflow::load($previous_wid);
          $lines[] = $workflow->label() . "'s states: ";
        }
        $label = SafeMarkup::checkPlain(t($state->label()));

        $lines[] = "   $key|$label";
      }
    }
    return implode("\n", $lines);
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
   * Implementation of OptionsProviderInterface
   *
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

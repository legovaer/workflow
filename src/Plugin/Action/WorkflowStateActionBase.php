<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Action\WorkflowStateActionBase.
 *
 * This is an abstract Action. Derive your own from this.
 *
 */

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;

/**
 * Sets an entity to a new, given state.
 *
 * Example Annotation @ Action(
 *   id = "workflow_given_state_action",
 *   label = @Translation("Change a node to new Workflow state"),
 *   type = "workflow"
 * )
 */
class WorkflowStateActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Constructs a new DeleteNode object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The tempstore factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'));
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
// D7: As advanced action with Trigger 'node':
// - $entity is empty;
// - $context['group'] = 'node'
// - $context['hook'] = 'node_insert / _update / _delete'
// - $context['node'] = (Object) stdClass
// - $context['entity_type'] = NULL

// D7: As advanced action with Trigger 'taxonomy':
// - $entity is (Object) stdClass;
// - $context['type'] = 'entity'
// - $context['group'] = 'taxonomy'
// - $context['hook'] = 'taxonomy_term_insert / _update / _delete'
// - $context['node'] = (Object) stdClass
// - $context['entity_type'] = NULL

// D7: As advanced action with Trigger 'workflow API':
// ...

// D7: As VBO action:
// - $entity is (Object) stdClass;
// - $context['type'] = NULL
// - $context['group'] = NULL
// - $context['hook'] = NULL
// - $context['node'] = (Object) stdClass
// - $context['entity_type'] = 'node'

    /* @var $entity \Drupal\Core\Entity\EntityInterface */
    $entity = $object;

    // Add actual data.
    $transition = $this->getTransitionForExecution($entity);

    $force = $this->configuration['workflow']['workflow_force'];
    $transition->force();

    // Fire the transition.
    workflow_execute_transition($transition, $force);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = $this->configuration + ['workflow' => array(
      'workflow_field_name' => '',
      'workflow_to_sid' => '',
      'workflow_comment' => "Action set %title to %state by %user.",
      'workflow_force' => 0,
    )];
    return $configuration;
  }

  /**
   * @return WorkflowTransitionInterface
   */
  protected function getTransitionForConfiguration() {
    // Build a transition from the values.
    $config = $this->configuration['workflow'];
    $entity = NULL;
    $field_name = $config['workflow_field_name'];
    $current_sid = '';
    $new_sid = $config['workflow_to_sid'];
    $user = workflow_current_user();
    $comment = $config['workflow_comment'];
    $force = $config['workflow_force'];

    // Add transition to config.
    $transition = WorkflowTransition::create();
    $transition->setValues($entity, $field_name, $current_sid, $new_sid, $user->id(), REQUEST_TIME, $comment, TRUE);
    return $transition;
  }

  /**
   * @return WorkflowTransitionInterface
   */
  protected function getTransitionForExecution(EntityInterface $entity) {
    $user = workflow_current_user();

    if (!$entity) {
      \Drupal::logger('workflow_action')->notice('Unable to get current entity - entity is not defined.', []);
      return NULL;
    }

    // Get the entity type and numeric ID.
    $entity_id = $entity->id();
    if (!$entity_id) {
      \Drupal::logger('workflow_action')->notice('Unable to get current entity ID - entity is not yet saved.', []);
      return NULL;
    }

    // In 'after saving new content', the node is already saved. Avoid second insert.
    // Todo: clone?
    $entity->enforceIsNew(FALSE);

    // Get a default Transition from configuration.
    $transition = $this->getTransitionforConfiguration();
    $field_name = $transition->getFieldName();
    $current_sid = workflow_node_current_state($entity, $field_name);
    if (!$current_sid) {
      \Drupal::logger('workflow_action')->notice('Unable to get current workflow state of entity %id.', array('%id' => $entity_id));
      return NULL;
    }

    // Get the Comment. Parse the $comment variables.
    $comment_string = $this->configuration['workflow']['workflow_comment'];
    $comment = t($comment_string, array(
      '%title' => $entity->label(),
      // "@" and "%" will automatically run check_plain().
      '%state' => workflow_get_sid_name($transition->getToSid()),
      '%user' => $user->getUsername(),
    ));

    $to_sid = $transition->getToSid();

    // Add actual data.
    $transition->setValues($entity, $field_name, $current_sid, $to_sid, $user->id(), REQUEST_TIME, $comment);

    // // Leave Force to subclass.
    // $force = $this->configuration['workflow']['workflow_force'];
    // $transition->force();

    return $transition;
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // If we are on admin/config/system/actions and use CREATE AN ADVANCED ACTION
    // Then $context only contains:
    // - $context['actions_label'] = "Change workflow state of post to new state"
    // - $context['actions_type'] = "entity"
    //
    // If we are on a VBO action form, then $context only contains:
    // - $context['entity_type'] = "node"
    // - $context['view'] = "(Object) view"
    // - $context['settings'] = "array()"

    /* // @TODO D8-port
    // Get the common Workflow, or create a dummy Workflow.
    $workflow = $wid ? Workflow::load($wid) : Workflow::create(); // set('label','dummy VBO');
    // Show the current state and the Workflow form to allow state changing.
    // N.B. This part is replicated in hook_node_view, workflow_tab_page, workflow_vbo.
    if ($workflow) {
      $field = _workflow_info_field($field_name, $workflow);
      $field_name = $field['field_name'];
      $field_id = $field['id'];
      $instance = field_info_instance($entity_type, $field_name, $entity_bundle);

      // Hide the submit button. VBO has its own 'next' button.
      $instance['widget']['settings']['submit_function'] = '';
      if (!$field_id) {
        // This is a Workflow Node workflow. Set widget options as in v7.x-1.2
        $field['settings']['widget']['comment'] = isset($workflow->options['comment_log_tab']) ? $workflow->options['comment_log_tab'] : 1; // vs. ['comment_log_node'];
        $field['settings']['widget']['current_status'] = TRUE;
        // As stated above, the options list is probably very long, so let's use select list.
        $field['settings']['widget']['options'] = 'select';
        // Do not show the default [Update workflow] button on the form.
        $instance['widget']['settings']['submit_function'] = '';
      }
    }

    // Add the form/widget to the formatter, and include the nid and field_id in the form id,
    // to allow multiple forms per page (in listings, with hook_forms() ).
    // Ultimately, this is a wrapper for WorkflowDefaultWidget.
    // $form['workflow_current_state'] = workflow_state_formatter($entity_type, $entity, $field, $instance);
    $form_id = implode('_', array(
      'workflow_transition_form',
      $entity_type,
      $entity_id,
      $field_id
    ));
*/

    $transition = $this->getTransitionForConfiguration();

    $element = []; // Just to be explicit.
    $element['#default_value'] = $transition;
    $form += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
//    // @TODO D8-port: introduce '#type' => 'workflow_transition' element.
//    $form['workflow_transition'] = array(
//      '#type' => 'workflow_transition',
//      '#title' => t('Workflow transition'),
//      '#default_value' => $transition,
//    );

    /* // @TODO D8-port
    if (!$entity) {
      // For the Advanced actions form on admin/config/system/actions,
      // remove the Submit function.
      unset($form['#submit']);
    }
    */

    // Make adaptations for VBO-form:
    $entity = $transition->getTargetEntity();
    $field_name = $transition->getFieldName();
    $force = $this->configuration['workflow']['workflow_force'];

    // Override the options widget.
    $form['workflow']['workflow_to_sid']['#description'] = t('Please select the state that should be assigned when this action runs.');

    // Add Field_name. @todo?? Add field_name to WorkflowTransitionElement?
    $form['workflow']['workflow_field_name'] = array(
      '#type' => 'select',
      '#title' => t('Field name'),
      '#description' => t('Choose the field name.'),
      '#options' => _workflow_info_field_names($entity),
      '#default_value' => $field_name,
      '#required' => TRUE,
    );
    // Add Force. @todo?? Add field_name to WorkflowTransitionElement?
    $form['workflow']['workflow_force'] = array(
      '#type' => 'checkbox',
      '#title' => t('Force transition'),
      '#description' => t('If this box is checked, the new state will be assigned even if workflow permissions disallow it.'),
      '#default_value' => $force,
    );
    // Change comment field.
    $form['workflow']['workflow_comment'] = array(
      '#title' => t('Message'),
      '#description' => t('This message will be written into the workflow history log when the action
      runs. You may include the following variables: %state, %title, %user.'),
    ) + $form['workflow']['workflow_comment'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $form_state->getValues()['workflow'];
    unset($configuration['workflow_transition']); // No cluttered objects in datastorage.
    $this->configuration['workflow'] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(){
    return [
      'module' => array('workflow',),
    ];
  }

}

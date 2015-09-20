<?php

/**
 * @file
 * Contains \Drupal\workflow\Entity\Controller\WorkflowConfigTransitionListBuilder.
 */

namespace Drupal\workflow\Entity\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;


/**
 * Defines a class to build a draggable listing of Workflow Config Transitions entities.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 */
class WorkflowConfigTransitionListBuilder extends ConfigEntityListBuilder implements FormInterface {
  /**
   * The key to use for the form element containing the entities.
   *
   * @var string
   */
  protected $entitiesKey = 'entities';

  /**
   * The entities being listed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = array();

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_config_transition_form';
  }

  /**
   * {@inheritdoc}
   *
   * Create an $entity for every From-state.
   */
  public function load() {
    $entities = array();

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return $entities;
    }
    $wid = $url_wid = $workflow->id();

    $states = $workflow->getStates($all = 'CREATION');
    if ($states) {
      foreach ($states as $state) {
        $from = $state->id();
        $entities[$from] = $state;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = array();

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return $header;
    }
    $wid = $url_wid = $workflow->id();

    $states = $workflow->getStates($all = 'CREATION');
    if ($states) {
      $header['label'] = t('From \ To');

      foreach ($states as $state) {
        $label = SafeMarkup::checkPlain($state->label());
        // Don't allow transition TO (creation).
        if (!$state->isCreationState()) {
          $header[$state->id()] = array('data' => $label);
        }
      }
    }

    return $header;
//    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * Builds a row for the following table:
   *   Transitions, for example:
   *     18 => array(
   *       20 => array(
   *         'author' => 1,
   *         1        => 0,
   *         2        => 1,
   *       )
   *     )
   *   means the transition from state 18 to state 20 can be executed by
   *   the node author or a user in role 2. The $transitions array should
   *   contain ALL transitions for the workflow.
   */
  public function buildRow(EntityInterface $entity) {
    $row = array();

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return;
    }
    $wid = $url_wid = $workflow->id();

    if ($workflow) {
      // Each $entity is a from-state.
      /* @var $entity \Drupal\workflow\Entity\WorkflowState */
      $from_state = $entity;
      $from_sid = $from_state->id();

      $states = $workflow->getStates($all = 'CREATION');
      if ($states) {
        $roles = workflow_get_roles();
        foreach ($states as $state) {
          $label = SafeMarkup::checkPlain($from_state->label());
          $row['label'] = $label;

          foreach ($states as $nested_state) {
            // Don't allow transition TO (creation).
            if ($nested_state->isCreationState()) {
              continue;
            }
            // Only  allow transitions from $from_state.
            if ($state->id() <> $from_state->id()) {
              continue;
            }
            $to_sid = $nested_state->id();
            $stay_on_this_state = ($to_sid == $from_sid);

            // Load existing config_transitions. Create if not found.
            $config_transitions = $workflow->getTransitionsBySidTargetSid($from_sid, $to_sid);
            if (!$config_transition = reset($config_transitions)) {
              $config_transition = $workflow->createTransition($from_sid, $to_sid);
            }

            $row[$to_sid]['workflow_config_transition'] = ['#type' => 'value', '#value' => $config_transition,];
            $row[$to_sid]['roles'] = ['#type' => $stay_on_this_state ? 'hidden' : 'checkboxes', '#options' => $roles, '#disabled' => $stay_on_this_state, '#default_value' => $config_transition->roles,];
          }
        }
      }
    }
    return $row;
//    return $row += parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->formBuilder()->getForm($this);
//    return parent::render();
  }

  /**
   * {@inheritdoc}
   *
   * This is copied from DraggableListBuilder::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return $form;
    }
    $wid = $url_wid = $workflow->id();

    /*
     * Begin of copied code DraggableListBuilder::buildForm()
     */
    $form[$this->entitiesKey] = array('#type' => 'table', '#header' => $this->buildHeader(), '#empty' => t('There is no @label yet.', array('@label' => $this->entityType->getLabel())), '#tabledrag' => array(array('action' => 'order', 'relationship' => 'sibling', 'group' => 'weight',),),);

    $this->entities = $this->load();
    $delta = 10;
    // Change the delta of the weight field if have more than 20 entities.
    if (!empty($this->weightKey)) {
      $count = count($this->entities);
      if ($count > 20) {
        $delta = ceil($count / 2);
      }
    }
    foreach ($this->entities as $entity) {
      $row = $this->buildRow($entity);
      if (isset($row['label'])) {
        $row['label'] = array('#markup' => $row['label']);
      }
      if (isset($row['weight'])) {
        $row['weight']['#delta'] = $delta;
      }
      $form[$this->entitiesKey][$entity->id()] = $row;
    }
    /*
     * End of copied code DraggableListBuilder::buildForm()
     */

    $form['actions']['#type'] = 'actions';
    // Add 'submit' button.
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => t('Save'), '#button_type' => 'primary',];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return;
    }
    $wid = $url_wid = $workflow->id();

    // Make sure 'author' is checked for (creation) -> [something].
    $creation_state = $workflow->getCreationState();
    $creation_sid = $creation_state->id();

    if (empty($form_state->getValue($this->entitiesKey))) {
      $author_has_permission = TRUE;
    }
    else {
      $author_has_permission = FALSE;
      foreach ($form_state->getValue($this->entitiesKey) as $from_sid => $to_data) {
        foreach ($to_data as $to_sid => $transition_data) {
          if (!empty($transition_data['roles'][WORKFLOW_ROLE_AUTHOR_RID])) {
            $author_has_permission = TRUE;
            break;
          }
        }
      }
    }
    if (!$author_has_permission) {
      $form_state->setErrorByName('id', t('Please give the author permission to go from %creation to at least one state!',
        array('%creation' => $creation_state->label())));
    }

    return;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //  parent::submitForm($form, $form_state);

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      return ;
    }
    $wid = $url_wid = $workflow->id();

    foreach ($form_state->getValue($this->entitiesKey) as $from_sid => $to_data) {
      foreach ($to_data as $to_sid => $transition_data) {

        $roles = $transition_data['roles'];
        if ($config_transition = $transition_data['workflow_config_transition']) {
          $config_transition->roles = $roles;
          $config_transition->save();
        }
        else{
          // Should not be possible.
        }

//        dpm('TODO D8-port: test function WorkflowConfigListBuilder::' . __FUNCTION__ );
        /*
                foreach ($transition_data->roles as $role => $can_do) {
                  if ($can_do) {
                    $roles += array($role => $role);
                  }
                }
                if (count($roles)) {
                    $config_transition = $transitions_data->config_transition;
                    $config_transition->roles = $roles;
                    $config_transition->save();
                }
                else {
                  foreach ($workflow->getTransitionsBySidTargetSid($from, $to_sid, 'ALL') as $config_transition) {
                    $config_transition->delete();
                  }
                }
        */
      }
    }

    drupal_set_message(t('The workflow was updated.'));

    return;
  }

  /**
   * Returns the form builder.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder.
   */
  protected function formBuilder() {
    if (!$this->formBuilder) {
      $this->formBuilder = \Drupal::formBuilder();
    }
    return $this->formBuilder;
  }

}

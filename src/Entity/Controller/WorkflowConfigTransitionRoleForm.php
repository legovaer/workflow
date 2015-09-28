<?php

/**
 * @file
 * Contains \Drupal\workflow\Entity\Controller\WorkflowConfigTransitionRoleForm.
 */

namespace Drupal\workflow\Entity\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowConfigTransition;

/**
 * Defines a class to build a listing of Workflow Config Transitions entities.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 */
class WorkflowConfigTransitionRoleForm extends WorkflowConfigtransitionFormBase {

  /**
   * {@inheritdoc}
   *
   * Create an $entity for every From-state.
   */
  public function load() {
    $entities = array();

    $workflow = $this->workflow;
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

    $workflow = $this->workflow;
    $states = $workflow->getStates($all = 'CREATION');
    if ($states) {
      $header['label_new'] = t('From \ To');

      foreach ($states as $state) {
        $label = SafeMarkup::checkPlain($state->label());
        // Don't allow transition TO (creation).
        if (!$state->isCreationState()) {
          $header[$state->id()] = $label;
        }
      }
    }

    return $header;
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

    $workflow = $this->workflow;
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
          $row['to'] = [
            '#type' => 'value',
            '#markup' => $label,
          ];

          foreach ($states as $to_state) {
            // Don't allow transition TO (creation).
            if ($to_state->isCreationState()) {
              continue;
            }
            // Only  allow transitions from $from_state.
            if ($state->id() <> $from_state->id()) {
              continue;
            }
            $to_sid = $to_state->id();
            $stay_on_this_state = ($to_sid == $from_sid);

            // Load existing config_transitions. Create if not found.
            $config_transitions = $workflow->getTransitionsByStateId($from_sid, $to_sid);
            if (!$config_transition = reset($config_transitions)) {
              $config_transition = $workflow->createTransition($from_sid, $to_sid);
            }

            $row[$to_sid]['workflow_config_transition'] = ['#type' => 'value', '#value' => $config_transition,];
            $row[$to_sid]['roles'] = [
              '#type' => $stay_on_this_state ? 'hidden' : 'checkboxes',
              '#options' => $roles,
              '#disabled' => $stay_on_this_state,
              '#default_value' => $config_transition->roles,
            ];
          }
        }
      }
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $workflow = $this->workflow;
    // Make sure 'author' is checked for (creation) -> [something].
    $creation_state = $workflow->getCreationState();

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

    foreach ($form_state->getValue($this->entitiesKey) as $from_sid => $to_data) {
      foreach ($to_data as $to_sid => $transition_data) {
        /* @var $config_transition WorkflowConfigTransition */
        if ($config_transition = $transition_data['workflow_config_transition']) {
          $config_transition->roles = $transition_data['roles'];
          $config_transition->save();
        }
        else{
          // Should not be possible.
        }

//        dpm('TODO D8-port: test function WorkflowConfigTransitionPermissionForm::' . __FUNCTION__ );
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
                  foreach ($workflow->getTransitionsByStateId($from, $to_sid, 'ALL') as $config_transition) {
                    $config_transition->delete();
                  }
                }
        */
      }
    }

    drupal_set_message(t('The workflow was updated.'));

    return;
  }


}

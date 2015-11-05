<?php

/**
 * @file
 * Contains \Drupal\workflow_access\Form\WorkflowAccessSettingsForm.
 */

namespace Drupal\workflow_access\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Provides the base form for workflow add and edit forms.
 */
class WorkflowAccessForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_access_form';
  }

  /**
   * Title callback from workflow_access.routing.yml.
   */
  public function title() {
    $title = 'Access';
    if ($workflow = workflow_ui_url_get_workflow()) {
      $title = t('!name Access', array('!name' => $workflow->label()));
    }
    return $title;
  }

  /**
   * Implements hook_form().
   *
   * {@inheritdoc}
   *
   * Add a "three dimensional" (state, role, permission type) configuration
   * interface to the workflow edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Get the Workflow from the page.
    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    if (!$workflow = workflow_ui_url_get_workflow()) {
      // Leave this page immediately.
      return $form;
    }

    $form = array('#tree' => TRUE);

    $form['#wid'] = $workflow->id();

    // A list of role names keyed by role ID, including the 'author' role.
    // Only get the roles with proper permission + Author role.
    $type_id = $workflow->id();
    $roles = workflow_get_user_role_names("create $type_id workflow_transition");

    // Add a table for every workflow state.
    foreach ($workflow->getStates($all = TRUE) as $state) {
      if ($state->isCreationState()) {
        // No need to set perms on creation.
        continue;
      }
      $view = $update = $delete = array();
      $count = 0;
      foreach (workflow_access_get_workflow_access_by_sid($state->id()) as $access) {
        $count++;
        if ($access->grant_view) {
          $view[] = $access->rid;
        }
        if ($access->grant_update) {
          $update[] = $access->rid;
        }
        if ($access->grant_delete) {
          $delete[] = $access->rid;
        }
      }
      // Allow view grants by default for anonymous and authenticated users,
      // if no grants were set up earlier.
      if (!$count) {
        $view = array(AccountInterface::ANONYMOUS_ROLE, AccountInterface::AUTHENTICATED_ROLE);
      }
      // @todo: better tables using a #theme function instead of direct #prefixing.
      $form[$state->id()] = array(
        '#type' => 'fieldset',
        '#title' => $state->label(),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#tree' => TRUE,
      );

      $form[$state->id()]['view'] = array(
        '#type' => 'checkboxes',
        '#options' => $roles,
        '#default_value' => $view,
        '#title' => t('Roles who can view posts in this state'),
        '#prefix' => '<table width="100%" style="border: 0;"><tbody style="border: 0;"><tr><td>',
      );

      $form[$state->id()]['update'] = array(
        '#type' => 'checkboxes',
        '#options' => $roles,
        '#default_value' => $update,
        '#title' => t('Roles who can edit posts in this state'),
        '#prefix' => "</td><td>",
      );

      $form[$state->id()]['delete'] = array(
        '#type' => 'checkboxes',
        '#options' => $roles,
        '#default_value' => $delete,
        '#title' => t('Roles who can delete posts in this state'),
        '#prefix' => "</td><td>",
        '#suffix' => "</td></tr></tbody></table>",
      );
    }

    $form['submit'] = array('#type' => 'submit', '#value' => t('Save configuration'));

    return $form;
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * Stores permission settings for workflow states.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $sid => $access) {
      // @todo: not waterproof; can be done smarter, using elementchildren()..
      if (!WorkflowState::load($sid)) {
        continue;
      }

      foreach ($access['view'] as $rid => $checked) {
        $data = array(
          'sid' => $sid,
          'rid' => $rid,
          'grant_view' => (!empty($checked)) ? (bool) $checked : 0,
          'grant_update' => (!empty($access['update'][$rid])) ? (bool) $access['update'][$rid] : 0,
          'grant_delete' => (!empty($access['delete'][$rid])) ? (bool) $access['delete'][$rid] : 0,
        );
        workflow_access_insert_workflow_access_by_sid($data);
      }

      // Update all nodes having same workflow state to reflect new settings.
      // just set a flag, which is working for both Workflow Field ánd Workflow Node.
      node_access_needs_rebuild(TRUE);
    }

    drupal_set_message(t('Workflow access permissions updated.'));
//    $form_state['redirect'] = 'admin/config/workflow/workflow/' . $form['#wid'];
//    $form_state->setRedirect('entity.workflow_type.collection');
  }

}

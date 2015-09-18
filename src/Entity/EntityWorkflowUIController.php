<?php

/**
 * @file
 * Contains workflow_admin_ui\includes\Entity\EntityWorkflowUIController.
 */

class EntityWorkflowUIController extends EntityDefaultUIController {
// TODO D8-port: Move all functionality in this file 'EntityWorkflowUIController' elsewhere.

  protected function operationCount() {
    // Add more then enough colspan.
    return parent::operationCount() + 8;
  }

/*
  public function operationForm($form, &$form_state, $entity, $op) {}
 */

  public function overviewForm($form, &$form_state) {
    // Add table and pager.
    $form = parent::overviewForm($form, $form_state);

    // Allow modules to insert their own action links to the 'table', like cleanup module.
    $top_actions = \Drupal::moduleHandler()->invokeAll('workflow_operations', ['top_actions', NULL]);

    // Allow modules to insert their own workflow operations.
    foreach ($form['table']['#rows'] as &$row) {
      $url = $row[0]['data']['#url'];
      $workflow = $url['options']['entity'];
      foreach ($actions = \Drupal::moduleHandler()->invokeAll('workflow_operations', ['workflow', $workflow]) as $action) {
        $action['attributes'] = isset($action['attributes']) ? $action['attributes'] : array();
        // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l(strtolower($action['title']), $action['href'], $action['attributes']);

      }
    }

    // @todo: add these top actions next to the core 'Add workflow' action.
    $top_actions_args = array(
      'links' => $top_actions,
      'attributes' => array('class' => array('inline', 'action-links')),
    );

    // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $form['action-links'] = array(
//       '#type' => 'markup',
//       '#markup' => theme('links', $top_actions_args),
//       '#weight' => -1,
//     );

    // Add a submit button. The submit functions are added in the sub-forms.
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $form;
  }
  /*
   * Avoids the 'Delete' action if the Workflow is used somewhere.
   */
  protected function overviewTableRow($conditions, $id, $entity, $additional_cols = array()) {
    // Avoid the 'delete' operation if the Workflow is used somewhere.
    $status = $entity->status;

    // @see parent::overviewTableRow() how to determine a deletable entity.
    if (!entity_has_status($this->entityType, $entity, ENTITY_IN_CODE) && !$entity->isDeletable())  {
      // Set to a state that does not allow deleting, but allows other actions.
      $entity->status = ENTITY_IN_CODE;
    }
    $row = parent::overviewTableRow($conditions, $id, $entity, $additional_cols);

    // Just to be sure: reset status.
    $entity->status = $status;

    return $row;
  }

  /**
   * Overrides the 'revert' action, to not delete the workflows.
   *
   * @see https://www.drupal.org/node/2051079
   * @see https://www.drupal.org/node/1043634
   */
  public function applyOperation($op, $entity) {
    $label = $entity->label();
    $vars = array('%entity' => $this->entityInfo['label'], '%label' => $label);
    $id = entity_id($this->entityType, $entity);
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $edit_link = l(t('edit'), $this->path . '/manage/' . $id . '/edit');


    switch ($op) {
      case 'revert':
        // Do not delete the workflow, but recreate features_get_default($entity_type, $module);
        // entity_delete($this->entityType, $id);
        $workflow = $entity;
        $entity_type = $this->entityType;
        $funcname = $workflow->module . '_default_' . $this->entityType;
        $defaults = $funcname();
        // No defaults, no processing.
        if (empty($defaults)) {
          return;
        }

        foreach ($defaults as $name => $entity) {
          $existing[$name] = workflow_load($name);
          // If we got an existing entity with the same name, we reuse its entity id.
          if (isset($existing[$name])) {
            // Set the original as later reference.
            $entity->original = $existing[$name];

            // As we got an ID, the entity is not new.
            $entity->wid = $entity->original->wid;
            unset($entity->is_new);

            // Update the status to be in code.
            // $entity->status |= ENTITY_IN_CODE;
            $entity->status = ENTITY_IN_CODE;

            // We mark it for being in revert mode.
            $entity->is_reverted = TRUE;
            $entity->save();
            unset($entity->is_reverted);
          }
          // The rest of the defaults is handled by default implementation.
          // @see entity_defaults_rebuild()
        }
        \Drupal::logger($this->entityType)->notice('Reverted %entity %label to the defaults.', []);
        return t('Reverted %entity %label to the defaults.', $vars);

      case 'delete':
      case 'import':
      default:
        return parent::applyOperation($op, $entity);
    }
  }
}

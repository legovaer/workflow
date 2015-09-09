<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowStateController.
 */

class WorkflowStateController extends EntityAPIController {

  public function save($entity, DatabaseTransaction $transaction = NULL) {
    // Create the machine_name.
    if (empty($entity->name)) {
      if ($label = $entity->state) {
        $entity->name = str_replace(' ', '_', strtolower($label));
      }
      else {
        $entity->name = 'state_' . $entity->sid;
      }
    }

    $return = parent::save($entity, $transaction);
    if ($return) {
      $workflow = $entity->getWorkflow();
      // Maintain the new object in the workflow.
      $workflow->states[$entity->sid] = $entity;
    }

    // Reset the cache for the affected workflow.
    workflow_reset_cache($entity->wid);

    return $return;
  }

  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    // @todo: replace with parent.
    foreach ($ids as $id) {
      if ($state = workflow_state_load($id)) {
        $wid = $state->wid;
        db_delete('workflow_states')
          ->condition('sid', $state->sid)
          ->execute();

        // Reset the cache for the affected workflow.
        workflow_reset_cache($wid);
      }
    }
  }

}

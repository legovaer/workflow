<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowConfigTransitionController.
 */

/**
 * Implements a controller class for WorkflowConfigTransition.
 *
 * The 'true' controller class is 'Workflow'.
 */
class WorkflowConfigTransitionController extends EntityAPIController {

  /**
   * Overrides DrupalDefaultEntityController::cacheGet().
   *
   * Override default function, due to core issue #1572466.
   */
  protected function cacheGet($ids, $conditions = array()) {
    // Load any available entities from the internal cache.
    if ($ids === FALSE && !$conditions) {
      return $this->entityCache;
    }
    return parent::cacheGet($ids, $conditions);
  }

  public function save($entity, DatabaseTransaction $transaction = NULL) {
    $workflow = $entity->getWorkflow();

    // To avoid double posting, check if this transition already exist.
    if (empty($entity->tid)) {
      if ($workflow) {
        $config_transitions = $workflow->getTransitionsBySidTargetSid($entity->sid, $entity->target_sid);
        $config_transition = reset($config_transitions);
        if ($config_transition) {
          $entity->tid = $config_transition->tid;
        }
      }
    }

    // Create the machine_name. This can be used to rebuild/revert the Feature in a target system.
    if (empty($entity->name)) {
      $entity->name = $entity->sid . '_' . $entity->target_sid;
    }

    $return = parent::save($entity, $transaction);
    if ($return) {
      // Save in current workflow for the remainder of this page request.
      // Keep in sync with Workflow::getTransitions() !
      $workflow = $entity->getWorkflow();
      if ($workflow) {
        $workflow->transitions[$entity->tid] = $entity;
        // $workflow->sortTransitions();
      }
    }

    // Reset the cache for the affected workflow, to force reload upon next page_load.
    workflow_reset_cache($entity->wid);

    return $return;
  }
}

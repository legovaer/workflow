<?php

/**
 * @file
 * Contains workflow\includes\Entity\WorkflowController.
 */

/**
 * Implements a controller class for Workflow.
 */
class WorkflowController extends EntityAPIControllerExportable {

  // public function create(array $values = array()) {    return parent::create($values);  }
  // public function load($ids = array(), $conditions = array()) { }

  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    // @todo: replace WorkflowController::delete() with parent.
    // @todo: throw error if not workflow->isDeletable().
    foreach ($ids as $wid) {
      if ($workflow = workflow_load($wid)) {
        $workflow->delete();
      }
    }
    $this->resetCache();
  }

  /**
   * Overrides DrupalDefaultEntityController::cacheGet().
   *
   * Override default function, due to Core issue #1572466.
   */
  protected function cacheGet($ids, $conditions = array()) {
    // Load any available entities from the internal cache.
    if ($ids === FALSE && !$conditions) {
      return $this->entityCache;
    }
    return parent::cacheGet($ids, $conditions);
  }

  /**
   * Overrides DrupalDefaultEntityController::cacheSet().
   */
/*
  // protected function cacheSet($entities) { }
  //   return parent::cacheSet($entities);
  // }
 */

  /**
   * Overrides DrupalDefaultEntityController::resetCache().
   *
   * Called by workflow_reset_cache, to
   * Reset the Workflow when States, Transitions have been changed.
   */
  // public function resetCache(array $ids = NULL) {
  //   parent::resetCache($ids);
  // }

  /**
   * Overrides DrupalDefaultEntityController::attachLoad().
   */
  protected function attachLoad(&$queried_entities, $revision_id = FALSE) {
    foreach ($queried_entities as $entity) {
      // Load the states, so they are already present on the next (cached) load.
      $entity->states = $entity->getStates($all = TRUE);
      $entity->transitions = $entity->getTransitions(FALSE);
      $entity->typeMap = $entity->getTypeMap();
    }

    parent::attachLoad($queried_entities, $revision_id);
  }
}

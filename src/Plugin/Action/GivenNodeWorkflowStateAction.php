<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Action\GivenNodeWorkflowStateAction.
 */

namespace Drupal\workflow\Plugin\Action;

/**
 * Sets an entity to a new, given state.
 *
 * The only change is the 'type' in tha Annotation, so it works on Nodes,
 * and can be seen on admin/content page.
 *
 * @Action(
 *   id = "workflow_node_given_state_action",
 *   label = @Translation("Change a node to new Workflow state"),
 *   type = "node"
 * )
 */
class GivenNodeWorkflowStateAction extends WorkflowStateActionBase {

 /**
 * {@inheritdoc}
 */
  public function calculateDependencies(){
    return [
      'module' => array('workflow', 'node'),
    ];
  }

}

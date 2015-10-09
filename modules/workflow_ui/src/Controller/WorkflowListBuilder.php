<?php

/**
 * @file
 * Contains \Drupal\workflow_ui\Controller\WorkflowListBuilder.
 */

namespace Drupal\workflow_ui\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;

/**
 * Defines a class to build a listing of Workflow entities.
 *
 * @see \Drupal\workflow\Entity\Workflow
 */
class WorkflowListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // return 'workflow_form';
    return parent::getFormId();
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\workflow\Entity\Workflow */
    $row['id'] = $entity->id();
    $row['label'] = $this->getLabel($entity);
    $row['status'] = ''; // TODO $entity->getStatus();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    /* @var $workflow \Drupal\workflow\Entity\Workflow */
    $workflow = $entity;

    // Do not delete a Workflow if it contains content.
    if (isset($operations['delete']) && !$workflow->isDeletable()) {
      unset ($operations['delete']);
    }

//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ .': ' . 'top actions (via own routing??)');
    // Allow modules to insert their own action links to the 'table', like cleanup module.
    $top_actions = \Drupal::moduleHandler()->invokeAll('workflow_operations', ['top_actions', NULL]);
    // @todo: add these top actions next to the core 'Add workflow' action.
    $top_actions_args = array(
      'links' => $top_actions,
      'attributes' => array('class' => array('inline', 'action-links')),
    );
// $form['action-links'] = array(
//       '#type' => 'markup',
//       '#markup' => theme('links', $top_actions_args),
//       '#weight' => -1,
//     );


//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ .': ' . 'add default operations');
    // Allow modules to insert their own workflow operations.
//    foreach ($form['table']['#rows'] as &$row) {
//      $url = $row[0]['data']['#url'];
//      $workflow = $url['options']['entity'];
    foreach ($actions = \Drupal::moduleHandler()->invokeAll('workflow_operations', ['workflow', $workflow]) as $action) {
      $action['attributes'] = isset($action['attributes']) ? $action['attributes'] : array();

      $operations['MY_NEW_OPERATION'] = array(
        'title' => t('MY_NEW_OPERATION'),
        'weight' => -10,
        // @FIXME
        'url' => $entity->urlInfo('MY_NEW_OPERATION'),
//      'url'=> l(strtolower($action['title']), $action['href'], $action['attributes']);
      );
    }


//    dpm('TODO D8-port: test function WorkflowState::' . __FUNCTION__ .': ' . 'rempve delete operation');
    // Avoid the 'delete' operation if the Workflow is used somewhere.
//    $status = $entity->status;
//
//    // @see parent::overviewTableRow() how to determine a deletable entity.
//    if (!entity_has_status($this->entityType, $entity, ENTITY_IN_CODE) && !$entity->isDeletable())  {
//      // Set to a state that does not allow deleting, but allows other actions.
//      $entity->status = ENTITY_IN_CODE;
//    }
//
//    // Just to be sure: reset status.
//    $entity->status = $status;

    return $operations;
  }

}

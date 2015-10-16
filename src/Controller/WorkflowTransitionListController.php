<?php

/**
 * @file
 * Contains \Drupal\workflow\Controller\WorkflowTransitionListController.
 */

namespace Drupal\workflow\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityListController;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Workflow routes.
 */
class WorkflowTransitionListController extends EntityListController implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an  object.
   *
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(DateFormatter $date_formatter, RendererInterface $renderer) {
    // These parameters are taken from some random other controller.
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Generates an overview table of older revisions of a node,
   * but only if this::historyAccess() allows it.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function historyOverview(EntityInterface $node = NULL) {
    $form = array();

    /*
     * Get data from parameters.
     */
    $user = workflow_current_user();

    // TODO D8-port: make Workflow History tab happen for every entity_type.
    // For workflow_tab_page with multiple workflows, use a separate view. See [#2217291].
    // @see workflow.routing.yml, workflow.links.task.yml, WorkflowTransitionListController.
    //    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // ATM it only works for Nodes and Terms.
    // This is a hack. The Route should always pass an object.
    // On view tab, $entity is object,
    // On workflow tab, $entity is id().
    // Get the entity for this form.
    $entity = workflow_url_get_entity($node);

    /*
     * Get derived data from parameters.
     */
    $field_name = workflow_get_field_name($entity);
    if (!$field_name) {
      return $form;
    }

    /*
     * Step 1: generate the Transition Form.
     */
    // Create a transition, to pass to the form.
    $current_sid = workflow_node_current_state($entity, $field_name);
    $transition = WorkflowTransition::create();
    $transition->setValues($entity, $field_name, $current_sid, '', $user->id());
    // Add the WorkflowTransitionForm to the page.
    $form = $this->entityFormBuilder()->getForm($transition, 'add');

    // Add Submit buttons/Action buttons.
    // Either a default 'Submit' button is added, or a button per permitted state.
//    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    $settings_options_type = '';
    if ($settings_options_type == 'buttons') {
      workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
      // How do action buttons work? See also d.o. issue #2187151.
      // Create 'action buttons' per state option. Set $sid property on each button.
      // 1. Admin sets ['widget']['options']['#type'] = 'buttons'.
      // 2. This function formElelent() creates 'action buttons' per state option;
      //    sets $sid property on each button.
      // 3. User clicks button.
      // 4. Callback _workflow_transition_form_validate_buttons() sets proper State.
      // 5. Callback _workflow_transition_form_validate_buttons() sets Submit function.
      // @todo: this does not work yet for the Add Comment form.

      // Performance: inform workflow_form_alter() to do its job.
      _workflow_use_action_buttons(TRUE);
    }

    /*
     * Step 2: generate the Transition History List.
     */
    $entity_type = 'workflow_transition';
    // $form = $this->listing('workflow_transition');
    $list_builder = $this->entityManager()->getListBuilder($entity_type);
    // Add the Node explicitly, since $list_builder expects a Transition.
    $list_builder->workflow_entity = $entity;

    $form += $list_builder->render();

    /*
     * Finally: sort the elements (overriding their weight).
     */
    $form['workflow']['#weight'] = 10;
    $form['actions']['#weight'] = 100;
    $form['workflow_list_title']['#weight'] = 200;
    $form['table']['#weight'] = 201;

    return $form;
  }

  /**
   * Menu access control callback. Checks access to Workflow tab.
   *
   * This used to be D7-function workflow_tab_access($user, $entity).
   *
   * The History tab should not be used with multiple workflows per entity.
   * Use the dedicated view for this use case.
   * @todo D8: remove this in favour of View 'Workflow history per entity'.
   * @todo D8-port: make this workf for non-Node entity types.
   *
   * @param \Drupal\workflow\Controller\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   */

  public function historyAccess(AccountInterface $account) {
    static $access = array();

    $uid = ($account) ? $account->id() : -1;

    // TODO D8-port: make Workflow History tab happen for every entity_type.
    // @see workflow.routing.yml, workflow.links.task.yml, WorkflowTransitionListController.
//    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    // ATM it only works for Nodes and Terms.
    // This is a hack. The Route should always pass an object.
    // On view tab, $entity is object,
    // On workflow tab, $entity is id().
    // Get the entity for this form.
    $entity = workflow_url_get_entity();

    /* @var $entity EntityInterface */
    // Figure out the $entity's bundle and id.
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_id = ($entity) ? $entity->id() : '';

    if (isset($access[$uid][$entity_type][$entity_id])) {
      return $access[$uid][$entity_type][$entity_id];
    }

    // When having multiple workflows per bundle, use Views display
    // 'Workflow history per entity' instead!
    if (!$workflows = workflow_get_workflows_by_type($entity_bundle, $entity_type)) {
      return AccessResult::forbidden();
    }
    else {

      // @todo: Keep below code aligned between WorkflowState, ~Transition, ~TransitionListController
      // Determine if user is owner of the entity.
      $uid = ($account) ? $account->id() : -1;
      // Get the entity's ID and Author ID.
      $entity_id = ($entity) ? $entity->id() : '';
      // Some entities (e.g., taxonomy_term) do not have a uid.
      // $entity_uid = $entity->get('uid'); // isset($entity->uid) ? $entity->uid : 0;
      $entity_uid = (method_exists($entity, 'getOwnerId')) ? $entity->getOwnerId() : -1;

      $is_owner = FALSE;
      if (!$entity_id) {
        // This is a new entity. User is author. Add 'author' role to user.
        $is_owner = TRUE;
      }
      elseif (($entity_uid > 0) && ($uid > 0) && ($entity_uid == $uid)) {
        // This is an existing entity. User is author.
        // D8: use "access own" permission. D7: Add 'author' role to user.
        // N.B.: If 'anonymous' is the author, don't allow access to History Tab,
        // since anyone can access it, and it will be published in Search engines.
        $is_owner = TRUE;
      }
      else {
        // This is an existing entity. User is not the author. Do nothing.
      }

      /**
       * Get the object and its permissions.
       */
      /**
       * Determine if user has Access. Fill the cache.
       */
      // @todo: what to do with multiple workflow_fields per bundle? Use Views instead! Or introduce a setting.
      // @TODO D8-port: workflow_tab_access: use proper 'WORKFLOW_TYPE' permissions
      $access[$uid][$entity_type][$entity_id] = AccessResult::forbidden();
      foreach ($fields = _workflow_info_fields($entity, $entity_type, $entity_bundle) as $field_name => $definition) {
        $type_id = $definition->getSetting('workflow_type');
        if ($account->hasPermission("access any $type_id workflow_transion overview")) {
          $access[$uid][$entity_type][$entity_id] = AccessResult::allowed();
        }
        elseif ($is_owner && $account->hasPermission("access own $type_id workflow_transion overview")) {
          $access[$uid][$entity_type][$entity_id] = AccessResult::allowed();
        }
        elseif ($account->hasPermission('administer nodes')) {
          $access[$uid][$entity_type][$entity_id] = AccessResult::allowed();
        }
      }

      return $access[$uid][$entity_type][$entity_id];
    }
    return AccessResult::forbidden();
  }

}

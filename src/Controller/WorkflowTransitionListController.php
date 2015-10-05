<?php

/**
 * @file
 * Contains \Drupal\workflow\Controller\WorkflowTransitionListController.
 */

namespace Drupal\workflow\Controller;

use Drupal\comment\Controller\CommentController;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityListController;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\HtmlEntityFormController;
use Drupal\Core\Routing\CurrentRouteMatch;

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
   * Generates an overview table of older revisions of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function historyOverview(NodeInterface $node) {

    // TODO D8-port: make this entity-generic - not only Node.
    $form = array();

    $user = $this->currentUser();

    /* @var $entity EntityInterface */
    $entity = $node;
//    $route_match = \Drupal::routeMatch();
//    $node = $route_match->getParameter('node');

    // Get the field name.
    $field_name = workflow_get_field_name($entity);
    if (!$field_name) {
      // TODO D8-port: if no workflow_field found, then no history_tab
      return $form;
    }

    // Step 1: generate the Transtion Form.

    // Create a transition, to pass to the form.
    // Get the current sid. $field_name is updated with relevant value.
    $current_sid = workflow_node_current_state($entity, $field_name);
    $current_state = WorkflowState::load($current_sid);
    $current_state->field_name = $field_name;
    $workflow = $current_state->getWorkflow();
    $transition = WorkflowTransition::create();
    $transition->setValues($entity, $field_name, $current_sid, '', $user->id());
// TODO D8-port: implement below logic from NodeController: show transtionForm from WorkflowState
//    $node = $this->entityManager()->getStorage('node')->create(array(
//      'type' => $node_type->id(),
//    ));

//    $form = $this->entityFormBuilder()->getForm($transition, 'add');

    // Add the fields from the WorkflowTransition.
    //    field_attach_form('WorkflowTransition', $transition, $element['workflow'], $form_state);

//return $form;

    // Finally, add Submit buttons/Action buttons.
    // Either a default 'Submit' button is added, or a button per permitted state.
    $settings_options_type = '';  // TODO JVO
    if ($settings_options_type == 'buttons') {
      dpm('TODO D8-port: test function WorkflowController::' . __FUNCTION__ );
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


    // Step 2: generate the Transtion History List.
    $entity_type = 'workflow_transition';
    // $form = $this->listing('workflow_transition');
    $list_builder = $this->entityManager()->getListBuilder($entity_type);
    // Add the node explicitely, since $list_builder expects transition
    $list_builder->workflow_entity = $node;

    $form += $list_builder->render();

//    HtmlEntityFormController->getFormObject(Object, 'node.add')
//    Drupal\Core\Controller\FormController->getContentResult(Object, Object)

    // Sort the elements
    $form['workflow']['#weight'] = 10;
    $form['actions']['#weight'] = 100;
    $form['workflow_list_title']['#weight'] = 200;
    $form['table']['#weight'] = 201;
    return $form;


    // Show the current state and the Workflow form to allow state changing.
    // N.B. This part is replicated in hook_entity_view, workflow_tab_page, workflow_vbo, transition_edit.
    // @todo: support multiple workflows per entity.
    // For workflow_tab_page with multiple workflows, use a separate view. See [#2217291].
// TODO D8-port: test below code.
    /*
    $form_id = implode('_', array('workflow_transition_form', $entity_type, $entity_id, $field_id));
    $form += \Drupal::formBuilder()->getForm($form_id, $field, $instance, $entity_type, $entity);

    $output = \Drupal::service("renderer")->render($form);
*/

  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
    public function historyAccess(AccountInterface $account) {

    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');

    return $this->workflow_tab_access($account, $node);
  }

  /**
   * Menu access control callback. Determine access to Workflow tab.
   *
   * The History tab should not be used with multiple workflows per entity.
   * Use the dedicated view for this use case.
   * @todo D8: remove this in favour of View 'Workflow history per entity'.
   *
   * @param \Drupal\workflow\Controller\AccountInterface $user
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity type of this Entity subclass.
   *
   * @return \Drupal\Core\Access\AccessResult
   */
    function workflow_tab_access(AccountInterface $user, EntityInterface $entity) {

    $user = \Drupal::currentUser();
    $uid = $user->id();
    static $access = array();

    // Figure out the $entity's bundle and id.
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_id = $entity->id();

    if (isset($access[$uid][$entity_type][$entity_id])) {
      return $access[$uid][$entity_type][$entity_id];
    }

    // When having multiple workflows per bundle, use Views display
    // 'Workflow history per entity' instead!
    if (workflow_get_workflows_by_type($entity_bundle, $entity_type)) {

//      dpm('TODO D8-port: test function WorkflowController::' . __FUNCTION__ . '/' . __LINE__);
      // @TODO D8-port: workflow_tab_access: get author of node.

      // Get the user roles.
      $roles = array_keys($user->getRoles());

      // Some entities (e.g., taxonomy_term) do not have a uid.
//      $entity_uid = isset($entity->uid) ? $entity->uid : 0;
//      $entity_uid = $entity->get('uid');
      $entity_uid = $entity->uid->value;
      // If this is a new page, give the authorship role.
      if (!$entity_id) {
        $roles = array_merge(array(WORKFLOW_ROLE_AUTHOR_RID), $roles);
      }
      // Add 'author' role to user if user is author of this entity.
      // N.B.1: Some entities (e.g, taxonomy_term) do not have a uid.
      // N.B.2: If 'anonymous' is the author, don't allow access to History Tab,
      // since anyone can access it, and it will be published in Search engines.
      elseif (($entity_uid > 0) && ($uid > 0) && ($entity_uid == $uid)) {
        $roles = array_merge(array(WORKFLOW_ROLE_AUTHOR_RID), $roles);
      }

      // Get the permissions from the workflow settings.
      // @todo: workflow_tab_access(): what to do with multiple workflow_fields per bundle? Use Views instead!
      // @TODO D8-port: workflow_tab_access: use proper 'WORKFLOW_TYPE' permissions
      $tab_roles = array();
      $history_tab_show = FALSE;
      foreach ($fields = _workflow_info_fields($entity, $entity_type, $entity_bundle) as $field) {
        /* @var $workflow = Workflow */
        $workflow = Workflow::load($field->getSetting('workflow_type'));
        $workflow_settings = $workflow->options;
        $tab_roles += $workflow->tab_roles;
        $history_tab_show |= $workflow_settings['history_tab_show'];
      }

      if ($history_tab_show == FALSE) {
        $access[$uid][$entity_type][$entity_id] = AccessResult::forbidden();
      }
      elseif ($user->hasPermission('administer nodes') || array_intersect($roles, $tab_roles)) {
        $access[$uid][$entity_type][$entity_id] = AccessResult::allowed();
      }
      else {
        $access[$uid][$entity_type][$entity_id] = AccessResult::forbidden();
      }
      return $access[$uid][$entity_type][$entity_id];
    }
    return AccessResult::forbidden();
  }

  /**
   * The _title_callback for the node.add route.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The current node.
   *
   * @return string
   *   The page title.
   */
//  public function addPageTitle(NodeTypeInterface $node_type) {
//    dpm('TODO D8-port: test function WorkflowController::' . __FUNCTION__ );
//    return $this->t('Create @name', array('@name' => $node_type->label()));
//  }

}

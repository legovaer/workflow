<?php

/**
 * @file
 * Contains \Drupal\workflow\Tests\WorkflowAdminTest.
 */

namespace Drupal\workflow_ui\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests node administration page functionality.
 *
 * @group node
 */
class WorkflowUiAdminTest extends WebTestBase {
  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with the 'access content overview' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $baseUser1;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('workflow', 'workflow_ui');

  /**
   * Use the Standard profile to test the full admin UI.
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Remove the "view own unpublished content" permission which is set
    // by default for authenticated users so we can test this permission
    // correctly.
    user_role_revoke_permissions(RoleInterface::AUTHENTICATED_ID, array('view own unpublished content'));

    $this->adminUser = $this->drupalCreateUser(array('access administration pages', 'administer workflow'));
    $this->baseUser1 = $this->drupalCreateUser([]);
  }

  /**
   * Tests the Workflow overview UI.
   */
  public function testWorkflowsOverview() {
    $overview_url = 'admin/config/workflow/workflow';

    // Test the user permissions.
    $this->drupalGet($overview_url);
    $this->assertResponse(403);
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($overview_url);
    $this->assertResponse(200);

    // Test if the elements are visisble.
    $this->assertText("There is no Workflow yet.");
    $this->assertRaw("This page allows you to maintain Workflows.");
    $this->assertText("Add workflow");
    $this->assertLinkByHref('/admin/config/workflow/workflow/add');

    // Create a random workflow in order to test all the UI elements.
    $tmp_name = $this->randomGenerator->word(12);
    $tmp_id = $this->randomGenerator->word(12);
    $this->generateRandomWorkflow($tmp_name, $tmp_id);
    $this->drupalGet($overview_url);
    $this->assertText($tmp_name);
    $this->assertText($tmp_id);
    $this->assertText('Edit');
    // @todo Test if all Workflow Actions are available in the actions menu.
  }

  /**
   * Helper method for generating a random workflow.
   *
   * @param NULL|string $name
   *   The name for the workflow. Use NULL for a random name.
   * @param NULL|string $id
   *   The machine name for the workflow. Use NULL for a random name.
   */
  protected function generateRandomWorkflow($name = NULL, $id = NULL) {
    if ($name == NULL) {
      $name = $this->randomGenerator->word(12);
    }

    if ($id == NULL) {
      $id = $this->randomGenerator->word(12);
    }

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/workflow/worfkflow/add');
    $edit = array();
    $edit['label'] = $name;
    $edit['id'] = $id;
    $this->drupalPostForm('/admin/config/workflow/workflow/add', $edit, t('Save'));
  }

}

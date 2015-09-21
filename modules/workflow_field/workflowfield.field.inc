<?php

/**
 * @file
 * Defines a Workflow field, widget and formatter. (copied from list field).
 */

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Changes the hook_field_settings_form.
 * Fixes some Field settings and Field Instance settings, and makes sure users cannot change it.
 *
 * @todo: perhaps this is core functionality, but these values are only saved
 *        when the site builder explicitly save the instance settings. :-(
 */
function workflowfield_form_field_ui_field_edit_form_alter(&$form, &$form_state, $form_id) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  if ($form['#field']['type'] == 'workflow') {
    // The Workflow field must have a value, so set to required.
    $form['instance']['required']['#default_value'] = 1;
    $form['instance']['required']['#disabled'] = TRUE;

    // User may not set a default value (this is done by the Workflow module).
    // @see WorkflowState::getOptions()
    $form['instance']['default_value_widget']['#type'] = 'hidden';
    $form['instance']['default_value_widget']['#disabled'] = TRUE;
    unset($form['instance']['default_value_widget']);

    // Make sure only 1 value can be entered in the Workflow field.
    $form['field']['cardinality']['#default_value'] = 1;
    $form['field']['cardinality']['#disabled'] = TRUE;
  }
}

/**
 * Implements property_callbacks for hook_field_info().
 */
function workflowfield_property_info_callback(&$info, $entity_type, $field, $instance, $field_type) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $property = &$info[$entity_type]['bundles'][$instance['bundle']]['properties'][$field['field_name']];
  $property['getter callback'] = 'entity_metadata_field_property_get';
  $property['getter callback'] = '_workflowfield_metadata_property_get';
  // $property['setter callback'] = 'entity_metadata_field_property_set';
  // $property['setter callback'] = '_workflowfield_metadata_property_set';
  $property['options list'] = 'entity_metadata_field_options_list';
  $property['property info'] = array(
    'value' => array(
      'type' => 'integer',
      'label' => t('State ID'),
      'setter callback' => 'entity_property_verbatim_set',
    ),
    'workflow' => array(
      'type' => 'array',
      'label' => t('Workflow details'),
      'setter callback' => 'entity_property_verbatim_set',
    ),
  );
}

/**
 * Getter callback for Workflow defined in hook_entity_property_info_alter.
 *
 * This is different from the default, because 'value' is not always set
 * and 'workflow' may be set, but is not in the field data.
 */
function _workflowfield_metadata_property_get($entity, array $options, $name, $entity_type, $info) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $values = array();

  // return entity_metadata_field_property_get($entity, array $options, $name, $entity_type, $info);
  $field = field_info_field($name);
  $columns = array_keys($field['columns']);
  $langcode = isset($options['language']) ? $options['language']->language : \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;
  $langcode = entity_metadata_field_get_language($entity_type, $entity, $field, $langcode, TRUE);

  if (isset($entity->{$name}[$langcode])) {
    foreach ($entity->{$name}[$langcode] as $delta => $data) {
      // In workflowfield_property_info_callback(), we needed to set a column 'value'.
      // This is now filled from the widget data.
      // Sometimes that is not widget, or the submit function has not been processed yet.

      // On a normal widget:
      $data[$columns[0]] = $sid = isset($data['value']) ? $data['value'] : 0;
      // On a workflow form widget:
      $data[$columns[0]] = $sid = isset($data['workflow']['workflow_sid']) ? $data['workflow']['workflow_sid'] : $sid;

      $values[$delta] = $data[$columns[0]];
    }
  }
  // For an empty single-valued field, we have to return NULL.
  return $field['cardinality'] == 1 ? ($values ? reset($values) : NULL) : $values;
}

/**
 * Callback for setting field property values.
 */
function _workflowfield_metadata_property_set($entity, $name, $value, $langcode, $entity_type, $info) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  // return entity_metadata_field_property_set($entity, $name, $value, $langcode, $entity_type, $info);
  $field = field_info_field($name);
  $columns = array_keys($field['columns']);
  $langcode = entity_metadata_field_get_language($entity_type, $entity, $field, $langcode);
  $values = $field['cardinality'] == 1 ? array($value) : (array) $value;
}


/**
 * We will be using some default formatters and widgets from the List and Options modules.
 */

/**
 * Implements hook_field_formatter_info_alter().
 *
 * The module reuses the formatters defined in list.module.
 */
function workflowfield_field_formatter_info_alter(&$info) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $info['list_default']['field types'][] = 'workflow';
}

/**
 * Implements hook_field_widget_info_alter().
 *
 * The module does not implement widgets of its own, but reuses the
 * widgets defined in options.module.
 *
 * @see workflowfield_options_list()
 */
function workflowfield_field_widget_info_alter(&$info) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $info['options_select']['field types'][] = 'workflow';
  $info['options_buttons']['field types'][] = 'workflow';
}

/**
 * Implements hook_field_prepare_view().
 *
 * This hook is needed in the following case:
 * - Edit a node with Workflow Field;
 * - Change the value of the widget;
 * - Click [Preview] button;
 * See the "Notice: Undefined index: value in list_field_formatter_view() (line 467+472 of \modules\field\modules\list\list.module)."
 * This is because in the Workflow Widget, the data is stored in a 'workflow'
 * structure, not a 'value' value.
 *
 * However, it is a pity that we run this hook in every view :-(
 *
 * TODO D8-port: hook_field_prepare_view()	Removed, in favour of prepareView() on Formatters.
 * Field types that implemented specific prepare_view steps with this hook should consider providing
 * a base prepareView() implementation in an abstract base formatter class instead.
 */
function workflowfield_field_prepare_view($entity_type, $entities, $field, $instances, $langcode, &$items) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  // I guess Preview mode is only with 1 entity at a time.
  if (count ( $items ) !== 1) {
    return;
  }

  foreach ($items as $id => &$item_array) {
    foreach ($item_array as $index => &$item) {
      if (!isset($item['value'])) {
        $item['value'] = isset($item['workflow']['workflow_sid']) ? $item['workflow']['workflow_sid'] : '';
      }
    }
  }
}

/**
 * Implements hook_field_storage_config_delete()().
 *
 * @todo: implement functionality from workflow_node_delete().
 */
/*
// function workflow_hook_field_storage_config_delete()($entity_type, $entity, $field, $instance, $langcode, &$items) {
// }
 */

/**
 * Callback function for list.module formatter.
 *
 * Returns array of allowed values when using a 'core' list formatter,
 * instead of the 'workflow' formatter.
 *
 * @see list_allowed_values()
 */
function workflowfield_allowed_values($field, $instance, $entity_type, $entity) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $workflow_field = new WorkflowItem($field, $instance, $entity_type, $entity);
  return $workflow_field->getAllowedValues();
}

/**
 * Implements hook_options_list().
 *
 * Callback function for the default Options widgets.
 *
 * @todo: move to a class.
 */
function workflowfield_options_list($field, $instance, $entity_type, $entity) {
//  dpm('TODO D8-port: test function workflowfield.*.inc::' . __FUNCTION__);

  $user = \Drupal::currentUser(); // @todo #2287057: OK?
  // @todo: Perhaps global user is not always the correct user.
  // E.g., on ScheduledTransition->execute()? But this function is mostly used in UI.

  $options = array();

  if ($entity) {
    // Get the allowed new states for the entity's current state.
    $field_name = $field['field_name'];
    $sid = workflow_node_current_state($entity, $entity_type, $field_name);
    $state = workflow_state_load_single($sid);
    $options = $state->getOptions($entity_type, $entity, $field_name, $user, FALSE);
  }
  else {
    // $field['settings']['wid'] can be numeric or named.
    if ($workflow = workflow_load_single($field['settings']['wid'])) {
      // There is no entity, E.g., on the Rules action "Set a data value".
      $state = new WorkflowState(array('wid' => $workflow->wid));
      $options = $state->getOptions('', NULL, '', $user, FALSE);
    }
  }
  return $options;
}

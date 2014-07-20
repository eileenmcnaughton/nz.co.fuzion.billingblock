<?php

require_once 'billingblock.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function billingblock_civicrm_config(&$config) {
  _billingblock_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function billingblock_civicrm_xmlMenu(&$files) {
  _billingblock_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function billingblock_civicrm_install() {
  return _billingblock_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function billingblock_civicrm_uninstall() {
  return _billingblock_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function billingblock_civicrm_enable() {
  return _billingblock_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function billingblock_civicrm_disable() {
  return _billingblock_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function billingblock_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _billingblock_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function billingblock_civicrm_managed(&$entities) {
  return _billingblock_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function billingblock_civicrm_buildForm($formName, &$form) {
  if ($formName != 'CRM_Contribute_Form_Contribution_Main')  {
    return;
  }

  $profileFields = $form->get('profileAddressFields');
  $billingLocationID = $form->get('bltID');
  $billingFields = array(
    'first_name' => 'billing_first_name',
    'middle_name' => 'billing_middle_name',
    'last_name' => 'billing_last_name',
    'street_address' => "billing_street_address-{$billingLocationID}",
    'city' => "billing_city-{$billingLocationID}",
    'country' => "billing_country_id-{$billingLocationID}",
    'state_province' => "billing_state_province_id-{$billingLocationID}",
    'postal_code' => "billing_postal_code-{$billingLocationID}",
  );
  $profileAddressFields = array_diff_key($profileFields, array_fill_keys(array('first_name', 'middle_name', 'last_name',), 1));
  $billingFields = array_diff_key($billingFields, $profileAddressFields);
  $form->assign('billingFields', $billingFields);
  $form->assign('profileFields', $billingFields);

  CRM_Core_Region::instance('billing-block')->update('default', array(
    'disabled' => TRUE,
  ));
  CRM_Core_Region::instance('billing-block')->add(array(
    'template' => 'SubstituteBillingBlock.tpl',
  ));
}

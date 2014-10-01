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
 * implement buildForm hook to remove billing fields if elsewhere on the form
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function billingblock_civicrm_buildForm($formName, &$form) {
  if ($formName != 'CRM_Contribute_Form_Contribution_Main')  {
    return;
  }

  $billingLocationID = $form->get('bltID');
  $profileFields = $form->get('profileAddressFields');
  $billingFields = billingblock_getDisplayedBillingFields($profileFields, $billingLocationID);
  $form->assign('billingFields', $billingFields);
  $form->assign('profileFields', $billingFields);
  $profileIDs = array($form->_values['custom_post_id'], $form->_values['custom_pre_id']);
  foreach (billingblock_getSuppressedBillingFields($profileFields, $profileIDs, $form->_fields, $billingLocationID) as $billingField) {
    $form->_paymentFields[$billingField]['is_required'] = FALSE;
  }

  CRM_Core_Region::instance('billing-block')->update('default', array(
    'disabled' => TRUE,
  ));
  CRM_Core_Region::instance('billing-block')->add(array(
    'template' => 'SubstituteBillingBlock.tpl',
  ));
}

/**
 * Get address specific profile fields
 * @param $profileFields
 *
 * @return array
 */
function _billingblock_getProfileAddressFields($profileFields) {
  return array_diff_key((array)$profileFields, array_fill_keys(array('first_name', 'middle_name', 'last_name',), 1));
}

/**
 * get billing fields
 * @param integer $billingLocationID
 *
 * @return array
 */
function billingblock_getBillingFields($billingLocationID) {
  return array(
    'first_name' => 'billing_first_name',
    'middle_name' => 'billing_middle_name',
    'last_name' => 'billing_last_name',
    'street_address' => "billing_street_address-{$billingLocationID}",
    'city' => "billing_city-{$billingLocationID}",
    'country' => "billing_country_id-{$billingLocationID}",
    'state_province' => "billing_state_province_id-{$billingLocationID}",
    'postal_code' => "billing_postal_code-{$billingLocationID}",
  );
}

/**
 * Get the billing fields we have suppressed
 *
 * @param array $profileFields
 * @param array $profileIDs
 * @param array $fields
 * @param integer $billingLocationID
 *
 * @return array
 */
function billingblock_getSuppressedBillingFields($profileFields, $profileIDs, $fields, $billingLocationID) {
  // treat both pre & post profile fields as potential billing fields
  foreach (array_keys($fields) as $key) {
    if (in_array($key, array('first_name', 'middle_name', 'last_name'))) {
      $profileFields[$key] = NULL;
    }
    else {
      CRM_Core_BAO_UFField::assignAddressField($key, $profileFields, array('uf_group_id' => array('IN' => $profileIDs)));
    }
  }
  return array_diff_key(billingblock_getBillingFields($billingLocationID), billingblock_getDisplayedBillingFields($profileFields, $billingLocationID));
}

/**
 * Get the billing fields for display
 * @param array $profileFields
 * @param integer $billingLocationID
 *
 * @return array
 */
function billingblock_getDisplayedBillingFields($profileFields, $billingLocationID) {
  $profileAddressFields = _billingblock_getProfileAddressFields($profileFields);
  $profileAddressFields = array_merge($profileAddressFields, billingblock_getNameFields(TRUE));
  return array_diff_key(billingblock_getBillingFields($billingLocationID), $profileAddressFields);
}

/**
 * @return array
 */
function billingblock_getNameFields($flip = FALSE) {
  $nameFields = array('first_name', 'middle_name', 'last_name');
  return $flip ? array_fill_keys($nameFields, NULL): $nameFields;
}

/**
 * @param $formName
 * @param $fields
 * @param $files
 * @param $form
 * @param $errors
 */
function billingblock_civicrm_validateForm( $formName, &$fields, &$files, &$form, &$errors ) {
  if ($formName != 'CRM_Contribute_Form_Contribution_Main')  {
    return;
  }
  $billingLocationID = $form->get('bltID');
  $profileIDs = array($form->_values['custom_post_id'], $form->_values['custom_pre_id']);
  $billingFields = billingblock_getSuppressedBillingFields($form->get('profileAddressFields'), $profileIDs, $form->_fields, $billingLocationID);
  $locations = civicrm_api3('location_type', 'get', array('return' => 'id', 'is_active' => 1, 'options' => array('sort' => 'is_default DESC')));
  $locationIDs = array('Primary') + array_keys($locations['values']);
  $data = &$form->controller->container();

  foreach ($billingFields as $fieldName => $billingField) {
    foreach ($locationIDs as $locationID) {
      $possibleFieldName = $fieldName . '-' . $locationID;
      if (!empty($fields[$possibleFieldName])) {
        $fields[$billingField] = $fields[$possibleFieldName];
        $data['values']['Main'][$fields[$billingField]] = $fields[$possibleFieldName];
        $form->setElementError($billingField, NULL);
        if (stristr($billingField, 'country') && !empty($fields[$possibleFieldName]) ) {
          $data['values']['Main']['country'] = CRM_Core_PseudoConstant::countryIsoCode($fields[$possibleFieldName]);
        }
        continue 2;
      }
    }
  }
}

function billingblock_civicrm_postProcess($formName, &$form){
  if (!billingblock_civicrm_is_billing($formName)) {
    return;
  }
  if (empty($form->_params['country']) && !empty($form->_params['country_id'])) {
    $form->_params['country'] =  CRM_Core_PseudoConstant::countryIsoCode($form->_params['country_id']);
  }
}

/**
 * Is this form a billing form?
 * @param $formName
 *
 * @return bool
 */
function billingblock_civicrm_is_billing($formName) {
  if ($formName == 'CRM_Contribute_Form_Contribution_Main')  {
    return TRUE;
  }
  return FALSE;
}

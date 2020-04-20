<?php
use CRM_Deletetrashedcases_ExtensionUtil as E;

/**
 * DeleteCase.Emptytrash API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_delete_case_Emptytrash_spec(&$spec) {
}

/**
 * DeleteCase.Emptytrash API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_delete_case_emptytrash($params) {
  $caseDelete = new CRM_Deletetrashedcases_BAO_Delete();
  $caseDelete->emptyTrash($params);
  $returnValues = array();
  return civicrm_api3_create_success($returnValues, $params, 'DeleteCase', 'emptytrash');
}

<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2018                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2018
 */
class CRM_Deletetrashedcases_BAO_Delete {

  protected function getCasesToDelete($params) {
    $caseParams = [
      'sequential' => 1,
      'return' => ["id"],
      'is_deleted' => 1,
      'options' => ['limit' => 0],
    ];
    if (!empty($params['case_id'])) {
      $caseIds = explode(',', $params['case_id']);
      $isNumeric = TRUE;
      foreach ($caseIds as $id) {
        if (!is_numeric($id)) {
          $isNumeric = FALSE;
        }
      }
      if ($isNumeric) {
        $caseParams['id'] = ['IN' => $caseIds];
      }
    }
    if (!empty($params['case_type_id'])) {
      $caseTypeIds = explode(',', $params['case_type_id']);
      $isNumeric = TRUE;
      foreach ($caseTypeIds as $id) {
        if (!is_numeric($id)) {
          $isNumeric = FALSE;
        }
      }
      if ($isNumeric) {
        $caseParams['case_type_id'] = ['IN' => $caseTypeIds];
      }
    }
    if (empty($caseParams['case_type_id']) && empty($caseParams['id'])) {
      return [];
    }
    if (!empty($params['case_created_before']) && $params['case_created_before'] != 'yyyy-mm-dd') {
      $caseParams['start_date'] = ['<' => $params['case_created_before']];
    }
    $caseList = civicrm_api3('Case', 'get', $caseParams);

    return $caseList;
  }

  protected function deleteCustomFields($caseId) {
    $customTables = civicrm_api3('CustomGroup', 'get', [
      'sequential' => 1,
      'return' => ["table_name"],
      'extends' => "Case",
    ]);
    foreach ($customTables['values'] as $key => $value) {
      if (empty($value['table_name'])) {
        continue;
      }
      CRM_Core_DAO::executeQuery("DELETE FROM {$value['table_name']} WHERE entity_id = {$caseId}");
    }
  }

  protected function deleteRelationships($caseId) {
    $relationship = civicrm_api3('Relationship', 'get', [
      'sequential' => 1,
      'case_id' => $caseId,
    ]);
    if (!empty($relationship['count'])) {
      foreach ($relationship['values'] as $k => $rel) {
        civicrm_api3('Relationship', 'delete', [
          'id' => $rel['id'],
        ]);
      }
    }
  }

  protected function deleteCaseRelatedActivities($caseId) {
    $caseActivityTypes = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ["value"],
      'option_group_id' => "activity_type",
      'component_id' => "CiviCase",
    ]);
    $actTypes = implode(', ', array_column($caseActivityTypes['values'], 'value'));

    $activity = civicrm_api3('Activity', 'get', [
      'sequential' => 1,
      'return' => ["id"],
      'case_id' => $caseId,
    ]);
    if (!empty($activity['count'])) {
      foreach ($activity['values'] as $k => $act) {
        //delete custom table values related to this activity.
        $actCustomTables = civicrm_api3('CustomGroup', 'get', [
          'sequential' => 1,
          'return' => ["table_name"],
          'extends' => "Activity",
          'extends_entity_column_value' => ['IN' => [$actTypes]],
        ]);
        foreach ($actCustomTables['values'] as $key => $value) {
          if (empty($value['table_name'])) {
            continue;
          }
          CRM_Core_DAO::executeQuery("DELETE FROM {$value['table_name']} WHERE entity_id = {$act['id']}");
        }
        //Update original id so that parent activity deletion does not remove child activity which might be present on another case.
        CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET original_id = NULL WHERE original_id = {$act['id']}");

        //Delete main activity through API.
        try {
          civicrm_api3('Activity', 'delete', [
            'id' => $act['id'],
          ]);
        }
        catch (Exception $e) {
        }

        //If API keeps it in the trash, delete it permanantly.
        $dao = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_activity WHERE id = {$act['id']}");
        if ($dao) {
          CRM_Core_DAO::executeQuery("DELETE FROM civicrm_activity WHERE id = {$act['id']}");
        }
        //delete from case_activity table
        $caseAct = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_case_activity WHERE activity_id = {$act['id']}");
        if ($caseAct) {
          CRM_Core_DAO::executeQuery("DELETE FROM civicrm_case_activity WHERE activity_id = {$act['id']}");
        }
      }
    }
  }

  public function emptyTrash($params) {
    $caseList = self::getCasesToDelete($params);
    if (!empty($caseList['count'])) {
      foreach ($caseList['values'] as $key => $case) {
        $caseId = $case['id'];
        //delete custom fields
        $this->deleteCustomFields($caseId);

        //delete case relationship
        $this->deleteRelationships($caseId);

        //delete case activity
        $this->deleteCaseRelatedActivities($caseId);

        //delete case contact
        CRM_Core_DAO::executeQuery("DELETE FROM civicrm_case_contact WHERE case_id = {$caseId}");

        //delete case
        CRM_Core_DAO::executeQuery("DELETE FROM civicrm_case WHERE id = {$caseId}");
      }
    }

  }



}

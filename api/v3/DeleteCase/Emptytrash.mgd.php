<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'Cron:DeleteCase.Emptytrash',
    'entity' => 'Job',
    'params' =>
    array (
      'version' => 3,
      'name' => 'Call DeleteCase.Emptytrash API',
      'description' => 'Call DeleteCase.Emptytrash API',
      'run_frequency' => 'Monthly',
      'api_entity' => 'DeleteCase',
      'api_action' => 'Emptytrash',
      'parameters' => "case_id=[comma separated case type ids]\ncase_type_id=[comma separated case type ids]\ncase_created_before=yyyy-mm-dd",
    ),
  ),
);

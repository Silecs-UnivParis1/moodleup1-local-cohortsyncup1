<?php
// This is a debugging/testing script.
// It should not be run on the production instance

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__DIR__))).'/config.php'); // global moodle config file.
require(dirname(__DIR__) . '/upgradelib.php');

$cnt = upgrade_logs_to_specific_table('local_cohortsyncup1', 'cohort');
echo "Migrated cohort sync logs: " . $cnt['begin'] . " begins / " . $cnt['end'] . " ends. \n";

$cnt = upgrade_logs_to_specific_table('auth_ldapup1', 'ldap');
echo "Migrated ldap sync logs: " . $cnt['begin'] . " begins / " . $cnt['end'] . " ends. \n";

exit(0);
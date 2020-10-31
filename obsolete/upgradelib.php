<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/local/cohortsyncup1/locallib.php');
require_once($CFG->dirroot . '/local/mwsgroups/lib.php');

/**
 * Fix user sync for created users without record in table user_sync (catch-all, ONE-SHOT)
 * @param boolean $dryrun
 * @return boolean
 */
function fix_user_sync($dryrun=false) {
    global $DB;
    $sql = "SELECT u.id, u.auth, u.username, u.timemodified "
         . "FROM {user} u LEFT JOIN {user_sync} us ON (u.id=us.userid) "
         . "WHERE us.userid IS NULL AND u.auth='shibboleth'";
    $missingusers = $DB->get_records_sql($sql);
    echo count($missingusers) . " missing users (exisiting in table user but not in table user_sync).\n";
    //print_r($missingusers);
    if ($dryrun) {
        return true;
    }

    $diag = true;
    foreach ($missingusers as $missing) {
        $syncuser = new stdClass();
        $syncuser->ref_plugin = 'auth_ldapup1';
        $syncuser->ref_param = '';
        $syncuser->timemodified = time();
        $syncuser->userid = $missing->id;

        $id = $DB->insert_record('user_sync', $syncuser, true, false);
        if ($id) {
            echo "    " . $id . " " . $missing->username . "\n";
        }
        else {
            echo "ERR " . $missing->username . "not inserted.\n";
            $diag = false;
        }
    }
    return $diag;
}

/**
 * upgrade logs storage from (legacy) table log to table up1_cohortsync_log
 * @param string $module 'auth_ldapup1' or 'local_cohortsyncup1'
 * @param string actionprefix eg. 'ldap' or 'cohort'
 */
function upgrade_logs_to_specific_table($module, $actionprefix) {
    global $DB;
    $sql = "SELECT time, action, info "
            . "FROM {log} l "
            . "WHERE module=? ";
    $logs = $DB->get_recordset_sql($sql, [$module]);
    $cnt = ['begin' => 0, 'end' => 0];

    foreach ($logs as $log) {
        list($action, $beginend) = explode(':', $log->action);
        if ($action == 'delMissingCohorts') {
            continue;
        }
        if ($beginend == 'begin') {
            $lastid = up1_cohortsync_addlog(null, $actionprefix . ':' . $action, $log->info, $log->time);
            $cnt['begin']++;
        } elseif ($beginend == 'end') {
            up1_cohortsync_addlog($lastid, $actionprefix . ':' . $action, $log->info, $log->time);
            $cnt['end']++;
        } else {
            echo "Unknown action: $beginend !" ;
            var_dump($log);
        }
    }
    return $cnt;
}

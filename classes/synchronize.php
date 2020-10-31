<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2016 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * In this file, the functions related to cohorts sync used outside of local/cohortsyncup1
 */

/**
 * convert sync logs (ldapup1 and cohorts) from legacy logs to a new table: up1_cohortsync_log
 * @param int or null $beginid : the id of the matching begin timestamp, if it's an end timestamp
 * @param string $action among 'cohort:sync', 'cohort:syncAllGroups', 'cohort:syncFromUsers', 'ldap:sync'
 * @param sting $info
 * @param int $time ; if null, locally generated
 * @return int (record id for a begin timestamp) OR true (ending timestamp)
 */
/* public */ function up1_cohortsync_addlog($beginid = null, $action, $info, $time = null) {
    global $DB;

    $legitActions = ['cohort:sync', 'cohort:syncAllGroups', 'cohort:syncFromUsers', 'ldap:sync'];
    if ( ! in_array($action, $legitActions)) {
        die ('Action not allowed for cohortsync logs: '. $action . "\n");
    }
    if ($time === null) {
        $time = time();
    }
    if ($beginid) { // we have an ending timestamp
        $record = $DB->get_record('up1_cohortsync_log', ['id' => $beginid], '*', MUST_EXIST);
        $record->timeend = $time;
        $record->action = $action;
        $record->info = $info;
        $DB->update_record('up1_cohortsync_log', $record);
        return true;
    } else { // beginning timestamp
        $record = new stdClass();
        $record->timebegin = $time;
        $record->action = $action;
        $record->info = $info;
        $id = $DB->insert_record('up1_cohortsync_log', $record, true);
        return $id;
    }
}

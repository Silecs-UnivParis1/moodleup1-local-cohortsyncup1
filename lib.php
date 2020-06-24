<?php
// This file is part of a plugin for Moodle - http://moodle.org/

/**
 * @package    local
 * @subpackage cohortsyncup1
 * @copyright  2012-2016 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * In this file, the functions related to cohorts sync used outside of local/cohortsyncup1
 */

/**
 * returns the last sync from the logs
 * @param $synctype = 'syncFromUsers'|'syncAllGroups'
 * @return array('begin' => integer, 'end' => integer) as moodle timestamps
 * @uses exit
 */
function get_cohort_last_sync($synctype) {
    global $DB;

    $allowedSyncs = array('syncFromUsers', 'syncAllGroups');
    if ( ! in_array($synctype, $allowedSyncs)) {
        throw new coding_exception('unknown sync type: ['. $synctype . '].');
    }
    $sql = "SELECT MAX(timebegin) AS begin, MAX(timeend) AS end FROM {up1_cohortsync_log} WHERE action=? ";
    $record = $DB->get_record_sql($sql, ['cohort:' . $synctype]);
        $res = array(
            'begin' => $record->begin,
            'end' => $record->end,
        );
        return $res;
}


/**
 * search "new" cohorts equivalent to "old" ones, for yearly cohorts
 * @param array $old_idnumbers
 * @param bool $verbose
 * @return assoc. array (array)
 */
function get_equivalent_cohorts($old_idnumbers, $verbose=true) {
    global $DB;

    $res = array('new' => array(), 'notfound' => array(), 'unchanged' => array());
    $curyear = get_config('local_cohortsyncup1', 'cohort_period');
    foreach ($old_idnumbers as $idnumber) {
        $dbcohort = $DB->get_record('cohort', array('idnumber' => $idnumber), '*', MUST_EXIST);

        if ($dbcohort->up1period == '') {
            $res['unchanged'][] = $idnumber;
        } else {
            if ($dbcohort->up1period == $curyear) {
                $res['unchanged'][] = $idnumber;
            }
            else {
                $raw = cohort_raw_idnumber($idnumber);
                $tablemigrate = "up1_migrate_cohort_idnumber";
                if ($DB->get_manager()->table_exists($tablemigrate) ) {
                    $rawNew = $DB->get_field($tablemigrate, 'new', array('old' => $raw));
                    if ($rawNew) {
                        if ($verbose) {
                            echo "$raw has changed code, it is now $rawNew\n";
                        }
                        $raw = $rawNew;
                    }
                } else {
                    if ($verbose) {
                        echo "Warning: table $tablemigrate does not exist.";
                    }
                }
                $potidnumber = $raw . '-' . $curyear; // potential idnumber
                if ( $DB->record_exists('cohort', array('idnumber' => $potidnumber)) ) {
                    $res['new'][] = $potidnumber;
                } else {
                    $res['notfound'][] = $potidnumber;
                }
            }
        }
    }
    return $res;
}

/**
 * text to explain in user interface the reuse of "old" cohorts (to be used in wizard)
 * @param array $equivs as computed by the previous function
 */
function explain_equivalent_cohorts($equivs) {
    if (count($equivs['new'])) {
        echo "Les cohortes annualisées suivantes ont été reconnues et leurs équivalentes actuelles préselectionnées :\n<ul>\n";
        foreach ($equivs['new'] as $idnumber) {
            echo '<li>' . $idnumber . "</li>\n";
        }
        echo "</ul>\n";
    }
    if (count($equivs['notfound'])) {
        echo "Les cohortes annualisées suivantes n'ont apparemment pas d'équivalentes actuelles :\n<ul>\n";
        foreach ($equivs['notfound'] as $idnumber) {
            echo '<li>' . $idnumber . "</li>\n";
        }
        echo "</ul>\n";
    }
    if (count($equivs['unchanged'])) {
        echo "Les cohortes suivantes sont toujours valables : <br />\n<ul>\n";
        foreach ($equivs['unchanged'] as $idnumber) {
            echo '<li>' . $idnumber . "</li>\n";
        }
        echo "</ul>\n";
    }
    return true;
}

/**
 * convert sync logs (ldapup1 and cohorts) from legacy logs to a new table: up1_cohortsync_log
 * @param int or null $beginid : the id of the matching begin timestamp, if it's an end timestamp
 * @param string $action among 'cohort:sync', 'cohort:syncAllGroups', 'cohort:syncFromUsers', 'ldap:sync'
 * @param sting $info
 * @param int $time ; if null, locally generated
 * @return int (record id for a begin timestamp) OR true (ending timestamp)
 */
function up1_cohortsync_addlog($beginid = null, $action, $info, $time = null) {
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

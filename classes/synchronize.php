<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsyncup1;

use \local_cohortsyncup1\fetch_wsgroups;

/**
 * Methods used to synchronize the cohorts upon the webservices
 * normally, to be used by a scheduled task on a daily basis
 */
class synchronize
{
    private $verbose;

    /**
     *
     * @param int $verbose
     */
    function __construct(int $verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * Add a log message to up1_cohortsync_log. Begin or end message.
     * @param int or null $beginid : the id of the matching begin timestamp, if it's an end timestamp
     * @param string $action among 'cohort:sync', 'cohort:syncAllGroups', 'cohort:syncFromUsers', 'ldap:sync'
     * @param string $info
     * @param int $time ; if null, locally generated
     * @return int (record id for a begin timestamp) OR true (ending timestamp)
     * @global moodle_database $DB
     */
    public static function add_log(int $beginid = null, string $action, string $info, int $time = null) {
        global $DB;

        $legitActions = ['cohort:sync', 'cohort:syncAllGroups', 'cohort:syncFromUsers', 'ldap:sync'];
        if ( ! in_array($action, $legitActions)) {
            die ('Action not allowed for cohortsync logs: '. $action . "\n");
        }
        if ($time === null) {
            $time = time();
        }
        if ($beginid) { // we close a begin record with an ending timestamp
            $record = $DB->get_record('up1_cohortsync_log', ['id' => $beginid], '*', MUST_EXIST);
            $record->timeend = $time;
            $record->action = $action;
            $record->info = $info;
            $DB->update_record('up1_cohortsync_log', $record);
            return true;
        } else { // we create a beginning timestamp
            $record = new \stdClass();
            $record->timebegin = $time;
            $record->action = $action;
            $record->info = $info;
            $id = $DB->insert_record('up1_cohortsync_log', $record, true);
            return $id;
        }
    }


    /**
     * deletes all cohorts members, then all cohorts.
     * @param bool $force execution even if it breaks enrolments
     * @global moodle_database $DB
     */
    public function clean_all($force) {
        global $DB;

        $sql="SELECT COUNT(courseid) FROM {enrol} e LEFT JOIN {cohort} c ON
            (c.id = e.customint1) WHERE e.enrol='cohort' AND c.component='local_cohortsyncup1'";
        $n = $DB->count_records_sql($sql);
        if ($n > 0 && ! $force) {
            echo "Sorry, there are $n cohort enrolments. Can't do that.\n\n";
            return false;
        }

        echo "Deleting cohort_members...\n";
        $select = "cohortid IN (SELECT id FROM {cohort} WHERE component='local_cohortsyncup1')";
        $DB->delete_records_select('cohort_members', $select);

        echo "Deleting cohorts...\n";
        $DB->delete_records('cohort', array('component' => 'local_cohortsyncup1'));
        return true;
    }

    /**
     * @todo NON UTILISEE ? à vérifier
     * delete a cohort only if it is not enrolled
     * @global type $DB
     * @param integer $cohortid
     * @return boolean true = deleted ; false = not deleted
     * @global moodle_database $DB
     */
    private function safe_delete_cohort($cohortid) {
        global $DB;
        if ($DB->record_exists('enrol', ['enrol' => 'cohort', 'status' => 0, 'customint1' => $cohortid])) {
            return false; // cohort is enrolled
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', MUST_EXIST);
        \cohort_delete_cohort($cohort);
        return true;        
    }

    /**
     * @todo à passer dans le cli-script
     * wrapper around delete_missing_cohorts() to execute it by CLI one-shot
     * @return boolean
     */
    public function cli_delete_missing_cohorts() {
        $cnt = $this->delete_missing_cohorts();
        $logmsg = "parsed cohorts : " . $cnt['delete']. " deleted, " . $cnt['keep']. " kept.";
        echo "\n =>  $logmsg\n\n";
        return true;
    }

    /**
     * delete base cohorts which are not present in the webservice results anymore
     * @return array (int $deleted, int $kept) : # of deleted and kept cohorts
     * @global moodle_database $DB
     */
    private function delete_missing_cohorts() {
        global $DB;
        $fetching = new fetch_wsgroups($this->verbose);
        $wsdata = $fetching->fetch_all_groups();

        $action = [true => 'delete', false => 'keep'];
        $cnt = ['delete' => 0, 'keep' => 0];
        $progressMark = ['delete' => 'D', 'keep' => 'K'];

        $dbcohorts = $DB->get_fieldset_select('cohort', 'up1key', "component='local_cohortsyncup1' AND up1key != ''");
        $cohortsid = $DB->get_records_select_menu('cohort', "up1key != '' ", null, '', 'up1key, id');
        $wscohorts = array_map(function($data) {return $data->key;} , $wsdata);
        $todelete = array_diff($dbcohorts, $wscohorts);        
        $this->vecho (1, "\nThere are " . count($todelete) . " missing cohorts.\n");

        foreach ($todelete as $up1key) {
            $cohortid = $cohortsid[$up1key];
            $res = $this->safe_delete_cohort($cohortid);

            $act = $action[$res];
            $cnt[$act]++;
            $this->vecho(2, $progressMark[$act]);
            $this->vecho(3, '=' . $up1key . ' ');
        }
        return $cnt;
    }

    /*** MAIN METHODS ***/

    /**
     * second main function, based on WS allGroups
     * useful to get empty groups and name/description changes in cohorts
     * @param int $timelast since when the sync must be executed
     * @param int $limit
     */
    public function sync_all_groups(int $timelast=0, int $limit=0)
    {
        $this->update_period();
        $cnt = ['create' => 0, 'modify' => 0, 'pass' => 0, 'noop' => 0];
        $progressMark = array('create' => '+', 'modify' => 'M', 'pass' => 'P', 'noop' => '=');
        $count = 0;
        date_default_timezone_set('UTC');
        $ldaptimelast = date("YmdHis", $timelast) . 'Z';
        $cohortlogid = self::add_log(null, 'cohort:syncAllGroups', "From allGroups since $timelast");

        $fetching = new fetch_wsgroups($this->verbose);
        $wsdata = $fetching->fetch_all_groups();
        // $data = [ $key => '...', $name => '...', $modifyTimestamp => 'ldapTime' $description => '...'
        if ($wsdata) {
            $this->vecho(1, "Parsing " . count($wsdata) . " cohorts ; since $ldaptimelast. \n");

            foreach ($wsdata as $cohort) {
                $count++;
                if ($limit > 0 && $count > $limit) break;

                $action = $this->process_cohort($cohort, $ldaptimelast); // real processing here
                $cnt[$action]++;
                $this->vecho(2, $progressMark[$action]);
            }

            $logmsg = sprintf("All cohorts: %d passed, %d noop, %d modified, %d created.",
                $cnt['pass'], $cnt['noop'], $cnt['modify'], $cnt['create']);
            $cnt = $this->delete_missing_cohorts();
            $logmsg .= sprintf("\nMISSING:  %d deleted, %d kept.", $cnt['delete'], $cnt['keep']);
        }
        echo "\n\n$logmsg\n\n";
        self::add_log($cohortlogid, 'cohort:syncAllGroups', $logmsg);
    }


    /**
     * process individual cohort during sync
     * @param object $cohort
     * @param string $ldaptimelast
     * @return action among ('create', 'modify', 'pass', 'noop')
     * @global moodle_database $DB
     */
    function process_cohort($cohort, $ldaptimelast) {
        global $DB;

        if (property_exists($cohort,'modifyTimestamp') && $cohort->modifyTimestamp < $ldaptimelast) {
            return 'pass'; // passed due to modifyTimestamp
        }
        if (! $DB->record_exists('cohort', ['up1key' => $cohort->key])) { // cohort doesn't exist yet
            $newcohort = $this->define_cohort($cohort);
            $newid = \cohort_add_cohort($newcohort);
            if ( $newid > 0 ) {
                return 'create';
                }
        } else { // cohort exists ; must be modified
            $thiscohort = $this->update_cohort($cohort);
            if ($thiscohort) { // modified
                \cohort_update_cohort($thiscohort);
                return 'modify';
            } else { // nothing modified since last sync !
                return 'noop';
            }
        }
    }


    /**
     * PRIMARY MAIN function, linking users to cohorts, based on modified users and ws userGroupsAndRoles
     * @global type $DB
     * @param type $timelast since when the sync must be executed
     * @param type $limit
     * @param type $verbose
     * @global moodle_database $DB
     */
    public function sync_from_users($timelast=0, $limit=0, $verbose=0)
    {
        global $DB;

        $this->update_period();
        $cohortlogid = self::add_log(null, 'cohort:syncFromUsers', "since $timelast");

        $ws_userGroupsAndRoles = get_config('local_cohortsyncup1', 'ws_userGroupsAndRoles');
        // $ws_userGroupsAndRoles = 'http://ticetest.univ-paris1.fr/web-service-groups/userGroupsAndRoles';
        $ref_plugin = 'auth_ldapup1';
        $param = 'uid';   // ex. parameter '?uid=e0g411g01n6'

        $sql = 'SELECT u.id, username FROM {user} u JOIN {user_sync} us ON (u.id = us.userid) '
             . 'WHERE us.ref_plugin = ? AND us.timemodified > ?';
        $users = $DB->get_records_sql_menu($sql, [$ref_plugin, $timelast], 0, $limit);

        $cntCohortUsers = []; // users added in each cohort
        $cntCrcohorts = 0;
        $cntAddmembers = 0;
        $cntRemovemembers = 0;
        $cntUsers = 0;
        $totalUsers = count($users);
        $idcohort = $DB->get_records_select_menu('cohort', "up1key != ''", null, '', 'up1key, id');

        $prevpercent = '';
        foreach ($users as $userid => $username) {
            $cntUsers++;
            $localusername = strstr($username, '@', true);
            $requrl = $ws_userGroupsAndRoles . '?uid=' . $localusername;
            $memberof = []; //to compute memberships to be removed

            $fetching = fetch_wsgroups($this->verbose);
            $wsdata = $fetching->get_data($requrl);
            $this->vecho(2, ':'); // user
            $percent = sprintf("%3.0f", ($cntUsers / $totalUsers * 100)) ;
            if ($percent != $prevpercent) {
                $this->vecho(1, "\n $percent % ");
                $prevpercent = $percent;
            }
            if ( is_null($wsdata) ) {
                continue;
            }
            foreach ($wsdata as $cohort) {
                $ckey = $cohort->key;
                $memberof[] = $ckey;
                $this->vecho(3, '.'); //membership
                if ( isset($cntCohortUsers[$ckey]) ) {
                    $cntCohortUsers[$ckey]++;
                } else {
                    $cntCohortUsers[$ckey] = 1;
                    if (! isset($idcohort[$ckey])) { // cohort doesn't exist yet
                        $newcohort = $this->define_cohort($cohort);
                        $newid = \cohort_add_cohort($newcohort);
                        if ( $newid > 0 ) {
                            $cntCrcohorts++;
                            $idcohort[$ckey] = $newid;
                        }
                    }
                }
                if (! $DB->record_exists('cohort_members', ['cohortid' => $idcohort[$ckey], 'userid' => $userid])) {
                    \cohort_add_member($idcohort[$ckey], $userid);
                    $cntAddmembers++;
                }
            } // foreach($wsdata)

            $cntRemovemembers += $this->remove_memberships($userid, $memberof);
        } // foreach ($users)

        $logmsg = sprintf("%d parsed users.   Cohorts: %d parsed. %d created.   Membership: +%d  -%d.",
            $totalUsers, count($cntCohortUsers), $cntCrcohorts, $cntAddmembers, $cntRemovemembers);
        echo "\n\n$logmsg\n\n";
        self::add_log($cohortlogid, 'cohort:syncFromUsers', $logmsg);
    }


    /**
     * compute memberships to be removed from database, and then actually do removing
     * @param integer $userid
     * @param array $memberof array(int $cohort->up1key ... )
     * @global moodle_database $DB
     */
    private function remove_memberships($userid, $memberof) {
        global $DB;
        $cnt = 0;

        $sql = "SELECT cm.cohortid, c.up1key FROM {cohort_members} cm "
            . "INNER JOIN {cohort} c ON (c.id = cm.cohortid) "
            . "WHERE (cm.userid=? AND c.component='local_cohortsyncup1' AND c.up1key != '')";
        $res = $DB->get_records_sql_menu($sql, [$userid]);
        foreach ($res as $cohortid => $up1key) {
            if ( ! in_array($up1key, $memberof) ) {
                \cohort_remove_member($cohortid, $userid);
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * returns a "newcohort" object from the json-formatted webservice cohort
     * reads the cohort_period parameter and uses it to set idnumber, period, name
     * @param type $wscohort
     * @return (object) $newcohort
     */
    private function define_cohort($wscohort) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mwsgroups/lib.php');
        $groupYearly = groupYearlyPredicate();
        $curyear = get_config('local_cohortsyncup1', 'cohort_period');
        $groupcategory = groupKeyToCategory($wscohort->key);

        $newcohort = [
            'contextid' => 1,
            'name' => (property_exists($wscohort, 'name') ? $this->truncate($wscohort->name, 250) : $wscohort->key),
            'idnumber' => $wscohort->key,
            'description' => (property_exists($wscohort, 'description') ? $wscohort->description : $wscohort->key),
            'descriptionformat' => 0, //** @todo check
            'component' => 'local_cohortsyncup1',
            'up1category' => $groupcategory,
            'up1key' => $wscohort->key,
            'up1period' => '',
        ];

        if ( $groupYearly[$groupcategory] ) {
                $newcohort['up1period'] = $curyear;
                $newcohort['idnumber'] = $newcohort['idnumber'] . '-' . $curyear;
                $newcohort['name'] = '['. $curyear . '] ' . $newcohort['name'];
            }
        return ((object) $newcohort);
    }

    /**
     * returns an "updated cohort" object from the json-formatted webservice cohort
     * only 'name' and 'description' fields can be updated
     * @param type $wscohort
     * @return (object) $cohort OR FALSE if not modified
     * @global moodle_database $DB
     */
    private function update_cohort($wscohort) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/mwsgroups/lib.php');

        $cohort = $DB->get_record('cohort', ['up1key' => $wscohort->key]);
        $groupYearly = groupYearlyPredicate();
        $curyear = get_config('local_cohortsyncup1', 'cohort_period');
        $groupcategory = groupKeyToCategory($wscohort->key);

        $oldcohort = clone $cohort;
        $prename = (property_exists($wscohort, 'name') ? $this->truncate($wscohort->name, 250) : $wscohort->key);
        $cohort->name = ( $groupYearly[$groupcategory] ? '['. $curyear . '] ' : '') . $prename;
        $cohort->description = (property_exists($wscohort, 'description') ? $wscohort->description : $wscohort->key);
        $modified = !(bool)($cohort->name == $oldcohort->name && $cohort->description == $oldcohort->description);

        if ($modified) {
            return (object) $cohort;
        }
        return false;
    }


    /**
     * Cleanly truncates a string on a word boundary, if possible
     * @param string $str string to truncate
     * @param int $bytes number of bytes to keep (warning: bytes, not chars)
     * @param bool $end : true = keep the end ; false = keep the beginning
     * @return type
     */
    private function truncate($str, $bytes=254, $end=true, $complete='…') {
        if (strlen($str) <= $bytes) {
            return $str;
        }
        if ($end) {
            $strend = substr($str, -$bytes);
            $pos = strpos($strend, " ");
            $new = substr($strend, $pos);
        } else {
            $pos = strrpos(substr($str, 0, $bytes), " ");
            if ( ! $pos ) { // no space found => hard truncate
                $new = substr($str, 0, $bytes);
            } else { // clean truncate
                $new = substr($str, 0, $pos);
            }
        }
        return $new . $complete;
    }

    /**
     * checks if the current year (cohort_period) has changed since the last sync
     * and empties the cohort.up1key fields of matching cohorts
     * @return int count of blanked "old" cohorts
     * @global moodle_database $DB
     */
    private function update_period() {
        global $DB;

        $dbyear = $DB->get_field_sql(" SELECT MAX(up1period) FROM {cohort}", null, MUST_EXIST);
        $curyear = get_config('local_cohortsyncup1', 'cohort_period');
        if ($dbyear == $curyear) {
            $this->vecho(2, "Period = $dbyear ; does not change.\n\n");
            return 0;
        }
        $this->vecho(2, "/!\ Period change, from $dbyear to $curyear.\n\n");
        $sql = "SELECT COUNT(id) FROM {cohort} WHERE up1period=? AND up1key != '' ";
        $cntErased = $DB->get_field_sql($sql, [$dbyear], MUST_EXIST);
        $DB->execute("UPDATE {cohort} SET up1key='' WHERE up1period=? AND up1key != '' ", [$dbyear]);
        return $cntErased;
    }

    private function vecho($verbmin, $text)
    {
        if ($this->verbose >= $verbmin) {
            echo $text;
        }
    }
}
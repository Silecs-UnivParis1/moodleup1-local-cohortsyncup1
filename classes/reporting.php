<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_cohortsyncup1;

require_once($CFG->dirroot . '/auth/ldapup1/auth.php');
//require_once($CFG->dirroot . '/local/cohortsyncup1/lib.php');

/*
 * Users and Authentication statistics
 */
class reporting
{
    private const CohortPrefixes = ['structures', 'diploma-', 'groups-', 'affiliation-'];
    private const ViewTopCohortsUrl = '/local/cohortsyncup1/viewtopcohorts.php';
    private const ViewCohortUrl ='/local/cohortsyncup1/viewcohort.php';
    
    /**
     *
     * @global \moodle_database $DB
     * @return array
     */
    public static function get_users() {
        global $DB;
        $res = array();

        $count = $DB->count_records('user_sync', array('ref_plugin' => 'auth_ldapup1'));
        $res[] = array('Utilisateurs annuaire (user_sync)', $count);

        $rows = $DB->get_records_sql("SELECT auth, COUNT(id) AS cnt FROM {user} GROUP BY auth WITH ROLLUP");
        foreach ($rows as $row) {
            if ($row->auth == '') {
                $auth = "TOTAL auth.";
            } else {
                $auth = 'Auth. ' . $row->auth;
            }
            $res[] = array($auth, $row->cnt);
        }
        return $res;
    }

    /**
     *
     * @global \moodle_database $DB
     * @return array
     */
    public static function get_users_by_affiliation() {
        global $DB;
        $res = array();

        $fieldid = $DB->get_field('user_info_field', 'id',
                array('shortname'=>'up1edupersonprimaryaffiliation'), MUST_EXIST);
        $sql = "SELECT data, count(id) as cnt FROM {user_info_data} WHERE fieldid = ? GROUP BY data";
        $rows = $DB->get_records_sql($sql, array($fieldid));
        foreach ($rows as $row) {
            $res[] = array($row->data, $row->cnt);
        }
        return $res;
    }


    /*
     * Cohorts statistics
     * @global \moodle_database $DB
     * @return array
     */
    public static function get_cohorts_generic() {
        global $DB;
        $res = array();

        $counttot = $DB->count_records('cohort', array('component' => 'local_cohortsyncup1'));
        $res[] = array('Cohortes UP1', $counttot);
        $count = $DB->count_records('cohort', array('component' => 'local_cohortsyncup1', 'up1key' => ''));
        $res[] = array('Non synchronisées', $count);
        $res[] = array('Synchronisées', $counttot - $count);

        $sql = "SELECT COUNT(*) FROM {cohort_members} cm "
            . "JOIN {cohort} c ON (cm.cohortid = c.id) WHERE c.component = 'local_cohortsyncup1' ";
        $count = $DB->count_records_sql($sql);
        $res[] = array('Appartenances UP1', $count);
        return $res;
    }


    /**
     * compute cohorts nb and enrolled cohorts nb by (criterium $crit)
     * @param string $crit "up1category" or "up1period"
     * @return array(array) to be displayed by html_writer::table
     * @global \moodle_database $DB
     */
    public static function get_cohorts_criterium($crit) {
        global $DB;
    // NOTA: you have to define an index on enrol.customint1 to get a reasonable response time
        $sql = "SELECT IF(" .$crit. " <> '', " .$crit. ", '(none)') AS " .$crit
             . ", COUNT(DISTINCT c.id) AS cnt , COUNT(DISTINCT e.id) AS cntenrol, COUNT(DISTINCT e.customint1) AS cntec "
             . "FROM {cohort} c LEFT JOIN {enrol} e ON (e.enrol = 'cohort' AND e.customint1 = c.id) "
             . "WHERE component LIKE 'local_cohortsyncup1%' GROUP BY " .$crit." ORDER BY " .$crit. " ASC";
        $rows = $DB->get_records_sql($sql);
        $res = (array) $rows;
        return $res;
    }

    /**
     * compute top cohorts by members (optionally for a specific idnumber prefix)
     * @param int $limit SQL limit
     * @param string $prefix cohort.idnumber prefix
     * @return array(array) table partial content (N rows x 3 cols)
     * @global \moodle_database $DB
     */

    public static function get_cohorts_top($limit, $prefix=false) {
        global $DB;
        $res = array();

        $sql = "SELECT cm.cohortid, c.idnumber, c.name, COUNT(cm.id) AS cnt "
            . "FROM {cohort_members} cm "
            . "JOIN {cohort} c ON (c.id = cm.cohortid) "
            . ($prefix ? "WHERE idnumber LIKE '".$prefix."%' " : '')
            . "GROUP BY cohortid  ORDER BY cnt DESC  LIMIT " . $limit;
        $cohorts = $DB->get_records_sql($sql);
        foreach ($cohorts as $cohort) {
            $url = new \moodle_url(self::ViewCohortUrl, array('id' => $cohort->cohortid));
            $res[] = array($cohort->cnt,
                \html_writer::link($url, $cohort->idnumber),
                $cohort->name);
        }
        return $res;
    }

    /**
     * iterates the previous functions for all official prefixes
     * @param int $limit SQL limit
     * @return array(array)  table content (N rows x 3 cols)
     */
    public static function get_cohorts_top_by_prefix($limit) {
        $res = array();

        foreach (self::CohortPrefixes as $prefix) {
            $linkdetails = \html_writer::link(
                new \moodle_url(self::ViewTopCohortsUrl, array('number'=>50, 'prefix'=>$prefix)),
                'Détails');
            $res[] = array('', $prefix, $linkdetails); // Separator header row for a given prefix
            $tres = self::get_cohorts_top($limit, $prefix);
            $res = array_merge($res, $tres);
        }
        return $res;
    }


    /*
     * Sync and log statistics
     */

    public static function get_last_synchros() {
        $ldap = get_auth_plugin('ldapup1')->get_last_sync();
        $diag = new \local_cohortsyncup1\diagnostic(0);
        $cohorts = $diag->get_last_sync('syncAllGroups');

        $res = array(
            array('LDAP', $ldap['begin'], $ldap['end']),
            array('Cohorts',
                date('Y-m-d H:i:s ', $cohorts['begin']),
                date('Y-m-d H:i:s ', $cohorts['end'])),
        );
        return $res;
    }

    /**
     *
     * @param string $plugin "ldap" or "cohort" : prefix for the 'action' column
     * @param int $howmany
     * @return array
     */
    public static function get_synchros($plugin, $howmany) {
        global $DB;

        $res = array();
        $sql = "SELECT * FROM {up1_cohortsync_log} WHERE action LIKE ? ORDER BY id DESC LIMIT ". $howmany;
        $logs = $DB->get_records_sql($sql, [$plugin . ':%']);

        $logs = array_reverse($logs);
        foreach($logs as $log) {
            $begin = ($log->timebegin== 0 ? '?' : date('Y-m-d H:i:s ', $log->timebegin));
            $end = ($log->timeend == 0 ? '?' : date('Y-m-d H:i:s ', $log->timeend));
            $res[] = array($begin, $end, $log->action, $log->info);
        }
        return $res;
    }
}
<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsyncup1;

class diagnostic
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
     * returns the last sync from the logs
     * @param $synctype = 'syncFromUsers'|'syncAllGroups'
     * @return array('begin' => integer, 'end' => integer) as moodle timestamps
     */
    public function get_last_sync($synctype)
    {
        global $DB;

        $allowedSyncs = ['syncFromUsers', 'syncAllGroups'];
        if ( ! in_array($synctype, $allowedSyncs)) {
            throw new coding_exception('unknown sync type: ['. $synctype . '].');
        }
        $sql = "SELECT MAX(timebegin) AS begin, MAX(timeend) AS end FROM {up1_cohortsync_log} WHERE action=? ";
        $record = $DB->get_record_sql($sql, ['cohort:' . $synctype]);
        return ['begin' => $record->begin, 'end' => $record->end];
    }

    /**
     * performs various checkings on the cohorts consistency and display results (intended for CLI)
     */
    public function check_database()
    {
        $unique_key = $this->check_up1key_unicity();
        if ($unique_key === true) {
            echo "Key cohort.up1key unicity OK.\n\n";
        } else {
            echo "/!\ Error: key cohort.up1key not unique for following cohorts: (up1key => count)\n";
            print_r($unique_key);
            echo "\n";
        }
    }

    /**
     * check if the field up1key is well unique (or empty)
     * @return mixed : true if unique, or array of up1keys in error with their count
     */
    private function check_up1key_unicity() {
        global $DB;
        $sql = "SELECT up1key, COUNT(id) AS cnt FROM {cohort} WHERE up1key != '' GROUP BY up1key HAVING cnt > 1";
        $res = $DB->get_records_sql_menu($sql);
        if (count($res) == 0) {
            return true;
        } else {
            return $res;
        }
    }

    /**
     * show various statistics on cohort records
     */
    public function cohort_statistics() {
        $fields = ['up1period', 'up1category'];
        echo "\nCurrent year = " . get_config('local_cohortsyncup1', 'cohort_period') . "\n\n";

        foreach ($fields as $field) {
            echo "Statistics for $field : \n";
            print_r($this->cohort_statistics_by($field));
        }
    }

    /**
     * performs mysql statistics based on GROUP BY ($field)
     * @param string $field a column from table cohort
     * @return array (field-value => count)
     */
    private function cohort_statistics_by($field) {
        global $DB;
        $sql = sprintf("SELECT %s, COUNT(id) AS cnt FROM {cohort} GROUP BY %s", $field, $field);
        $res = $DB->get_records_sql_menu($sql);
        return $res;
    }


}
<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2014-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsyncup1;

/**
 * In this class, several methods to use the UP1 groups webservices :
 * - low-level and testing functions
 * - ad-hoc functions strongly linked with the webservice actions
 */
class fetch_wsgroups
{
    private $verbose;
    private $ws_allGroups;
    private $ws_ugar;
    public $curlinfo = null;

    /**
     *
     * @param int $verbose
     */
    function __construct(int $verbose)
    {
        $this->verbose = $verbose;
        $this->ws_allGroups = get_config('local_cohortsyncup1', 'ws_allGroups');
        // $ws_allGroups = 'http://ticetest.univ-paris1.fr/wsgroups/allGroups';
        $this->ws_ugar= get_config('local_cohortsyncup1', 'ws_userGroupsAndRoles');
    }

    /**
     * Get data from webservice - a wrapper around curl_exec
     * @param string $webservice URL of the webservice
     * @return array($curlinfo, $data)
     */
    public function get_data($webservice) {
        $wstimeout = 10;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $wstimeout);
        curl_setopt($ch, CURLOPT_URL, $webservice);
        $data = json_decode(curl_exec($ch));
        // $data = array($key => '...', $name => '...', $modifyTimestamp => 'ldapTime', $description => '...')

        $curlinfo = curl_getinfo($ch);
        if ($data === null) {
            $dump = var_export($curlinfo, true);
            throw new \coding_exception("webservice does NOT work", $dump);
        }
        curl_close($ch);
        $this->curlinfo = $curlinfo;
        return $data;
    }


    /**
     * Debug / display results of webservice
     * @todo à transformer en script-cli
     */
    public function test_user_groups_and_roles()
    {
        $data = $this->get_data($this->ws_ugar);
        printf("%s : %d entries.\n", $this->ws_ugar, count($data));
        print_r($this->curlinfo);

        $requrl = $this->ws_ugar . "?uid=prigaux";
        $data = $this->get_data($requrl);
        printf("%s : %d entries.\n", $requrl, count($data));
        print_r($this->curlinfo);
    }

    /**
     * fetch all groups
     */
    public function fetch_all_groups()
    {
        $data = $this->get_data($this->ws_allGroups);
        if ($data) {
            return $data;
        }
        $this->vecho(1, "\nUnable to fetch data from: " . $this->ws_allGroups . "\n");
        $this->vecho(2, "\n\nCurl diagnostic:\n" . print_r($this->curlinfo, true));
        return false;
    }


    /**
     * Debug / display results of webservice
     */
    public function display_all_groups()
    {
        $count = 0;
        $data = $this->fetch_all_groups();

        if ($data) {
            $this->vecho(1, "\nParsing " . count($data) . " groups. \n");
            foreach ($data as $group) {
                $count++;
                $this->vecho(2, '.');
                $this->vecho(3, "$count." . $group->key . "\n");
            } // foreach($data)
            echo "\nAll groups parsed.\n";
        }
    }


    /**
     * get related groups (from the up1 webservice)
     * @param string $key (generally, derived from Apogee code) ; ex. "groups-mati0938B05"
     * @return ['sub' => $subgroups, 'super' => $supergroups]
     */
    public function get_related_groups($key) {

        $urlws = str_replace('allGroups', 'getSubGroups', $this->ws_allGroups);
        $requrl = $urlws . "?key=" . $key;
        $wsdata = $this->get_data($requrl);
        $subgroups = [];
        foreach ($wsdata as $subgroup) {
            $subgroups[] = $subgroup->key;
        }

        $urlws = str_replace('allGroups', 'getSuperGroups', $this->ws_allGroups);
        $requrl = $urlws . "?key=" . $key;
        $wsdata = $this->get_data($requrl);
        $supergroups = $wsdata->$key->superGroups;

        return ['sub' => $subgroups, 'super' => $supergroups];
    }

    /**
     * get related cohorts : use the previous function get_related_groups()
     * and postprocess the result to return only the cohorts in local database
     * @param string $key (generally, derived from Apogee code) ; ex. "groups-mati0938B05"
     * @return array(associative array)
     */
    public function get_related_cohorts($keys) {
        global $DB;
        $flatrelated = [];

        foreach($keys as $key) {
            $relatedgroups = $this->get_related_groups($key);
            $flatrelated = array_merge($flatrelated, [$key], $relatedgroups['sub'], $relatedgroups['super']);
        }
        $flatrelated = array_unique($flatrelated);

        $inSql = "('" . join("','" , $flatrelated) . "')";
        $sql = "SELECT id, name, idnumber, description, descriptionformat, up1category "
             . "FROM {cohort} WHERE up1key IN  $inSql  ORDER BY name";
        $records = $DB->get_records_sql($sql);
        $groups = [];
        $order = 0;
        foreach ($records as $record) {
            $order++;
            $size = $DB->count_records('cohort_members', ['cohortid' => $record->id]);
            $groups[] = [
                'key' => $record->idnumber,
                'name' => $record->name,
                'description' => \format_text($record->description, $record->descriptionformat),
                'category' => $record->up1category,
                'size' => $size,
                'order' => $order
            ];
        }
        return $groups;
    }

    private function vecho($verbosemin, $text) {
        if ($this->verbose >= $verbosemin) {
            echo $text;
        }
    }

}
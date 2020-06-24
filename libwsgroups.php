<?php
// This file is part of a plugin for Moodle - http://moodle.org/

/**
 * @package    local
 * @subpackage cohortsyncup1
 * @copyright  2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * In this file, the functions able to use the UP1 groups webservices :
 * - low-level and testing functions
 * - ad-hoc functions strongly linked with the webservice actions
 */

/**
 * Get data from webservice - a wrapper around curl_exec
 * @param string $webservice URL of the webservice
 * @return array($curlinfo, $data)
 */
function get_ws_data($webservice) {
    $wstimeout = 5;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $wstimeout);
    curl_setopt($ch, CURLOPT_URL, $webservice);
    $data = json_decode(curl_exec($ch));
    // $data = array( stdClass( $key => '...', $name => '...', $modifyTimestamp => 'ldapTime', $description => '...')

    $curlinfo = curl_getinfo($ch);
    if ($data === null) {
        $dump = var_export($curlinfo, true);
        throw new coding_exception("webservice does NOT work", $dump);
    }
    curl_close($ch);
    return array($curlinfo, $data);
}


/**
 * Debug / display results of webservice
 * @param integer $verbose
 */
function test_user_groups_and_roles($verbose=2)
{
    global $CFG;
    $ws_ugar= get_config('local_cohortsyncup1', 'ws_userGroupsAndRoles');
    // $ws_allGroups = 'http://ticetest.univ-paris1.fr/wsgroups/allGroups';
    $requrl = $ws_ugar;
    list($curlinfo, $data) = get_ws_data($requrl);
    echo "$requrl : " . count($data) . " entries.\n";
    print_r($curlinfo);
    
    $requrl = $ws_ugar . "?uid=prigaux";
    list($curlinfo, $data) = get_ws_data($requrl);
    echo "$requrl : " . count($data) . " entries.\n";
    print_r($curlinfo);
}

/**
 * Debug / display results of webservice
 * @param integer $verbose
 */
function display_all_groups($verbose=2)
{
    global $CFG;
    $ws_allGroups = get_config('local_cohortsyncup1', 'ws_allGroups');
    // $ws_allGroups = 'http://ticetest.univ-paris1.fr/wsgroups/allGroups';
    $count = 0;
    list($curlinfo, $data) = get_ws_data($ws_allGroups);

    if ($data) {
        progressBar($verbose, 1, "\nParsing " . count($data) . " groups. \n");
        foreach ($data as $group) {
            $count++;
            progressBar($verbose, 2, '.');
            progressBar($verbose, 3, "$count." . $group->key . "\n");
        } // foreach($data)
        echo "\nAll groups parsed.\n";
    } else {
        echo "\nUnable to fetch data from: " . $ws_allGroups . "\n" ;
    }
    progressBar($verbose, 2, "\n\nCurl diagnostic:\n" . print_r($curlinfo, true));
}



/**
 * get related groups (from the up1 webservice)
 * @param string $key (generally, derived from Apogee code) ; ex. "groups-mati0938B05"
 * @return array('sub' => $subgroups, 'super' => $supergroups)
 */
function get_related_groups($key) {

    $url = get_config('local_cohortsyncup1', 'ws_allGroups');
    $urlws = str_replace('allGroups', 'getSubGroups', $url);
    $requrl = $urlws . "?key=" . $key;
    list($curlinfo, $wsdata) = get_ws_data($requrl);
    $subgroups = array();
    foreach ($wsdata as $subgroup) {
        $subgroups[] = $subgroup->key;
    }

    $urlws = str_replace('allGroups', 'getSuperGroups', $url);
    $requrl = $urlws . "?key=" . $key;
    list($curlinfo, $wsdata) = get_ws_data($requrl);
    $supergroups = $wsdata->$key->superGroups;

    return array('sub' => $subgroups, 'super' => $supergroups);
}

/**
 * get related cohorts : use the previous function get_related_groups()
 * and postprocess the result to return only the cohorts in local database
 * @param string $key (generally, derived from Apogee code) ; ex. "groups-mati0938B05"
 * @return array(associative array)
 */
function get_related_cohorts($keys) {
    global $DB;
    $flatrelated = array();

    foreach($keys as $key) {
        $relatedgroups = get_related_groups($key);
        $flatrelated = array_merge($flatrelated, array($key), $relatedgroups['sub'], $relatedgroups['super']);
    }
    $flatrelated = array_unique($flatrelated);
    //var_dump($flatrelated);

    $insql = "('" . join("','" , $flatrelated) . "')";
    $sql = "SELECT id, name, idnumber, description, descriptionformat, up1category "
         . "FROM {cohort} WHERE up1key IN " . $insql . " ORDER BY name";
    $records = $DB->get_records_sql($sql);
    $groups = array();
    $order = 0;
    foreach ($records as $record) {
        $order++;
        $size = $DB->count_records('cohort_members', array('cohortid' => $record->id));
        $groups[] = array(
                'key' => $record->idnumber,
                'name' => $record->name,
                'description' => format_text($record->description, $record->descriptionformat),
                'category' => $record->up1category,
                'size' => $size,
                'order' => $order
        );
    }
    return $groups;
}


function progressBar($verb, $verbmin, $text) {
    if ($verb >= $verbmin) {
        echo $text;
    }
}
<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2014-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \local_cohortsyncup1\fetch_wsgroups;

define('CLI_SCRIPT', true);
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->libdir.'/clilib.php');      // cli only functions

// now get cli options
list($options, $unrecognized) = cli_get_params([
        'help'=>false, 'verbose'=>0, 'test-ws-ugar'=>false,
        'display-groups'=>false, 'related'=>false, 'related-cohorts'=>false]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Testing functionalities over libwsgroups.php.

Options:
-h, --help            print this help

--related=X           display subgroups and supergroups.  X= eg. 'structures-U03'
--related-cohorts=X   display subgroups and supergroups which are real cohorts in DB
--test-ws-ugar        test the webservice userGroupsAndRoles (data download)
--display-groups      display all the groups (may be slow), to use with --verb= 1 to 3
--verbose=N           verbosity (0 to 3), 1 by default

Example:
/usr/bin/php local/cohortsyncup1/cli/wsgroups.php --test-ws --verb=2
";

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}

if ( $options['test-ws-ugar'] ) {
    $fetch = new fetch_wsgroups($options['verbose']);
    $fetch->test_user_groups_and_roles();
    return 0;
}

if ( $options['display-groups'] ) {
    $fetch = new fetch_wsgroups($options['verbose']);
    $fetch->display_all_groups();
    return 0;
}

if ( $group = $options['related'] ) {
    $fetch = new fetch_wsgroups($options['verbose']);
    $rgroups = $fetch->get_related_groups($group);
    print_r($rgroups);
    return 0;
}

if ( $cohort = $options['related-cohorts'] ) {
    $fetch = new fetch_wsgroups($options['verbose']);
    $rcohorts = $fetch->get_related_cohorts([$cohort]);
    print_r($rcohorts);
    return 0;
}

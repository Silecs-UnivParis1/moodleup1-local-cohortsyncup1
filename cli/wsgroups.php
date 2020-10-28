<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/local/cohortsyncup1/libwsgroups.php');


// now get cli options
list($options, $unrecognized) = cli_get_params(array(
        'help'=>false, 'verb'=>1, 'testws'=>false, 'displaygroups'=>false, 'related'=>false ),
    array('h'=>'help', 'i'=>'init'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Testing functionalities over libwsgroups.php.

Options:
-h, --help            Print out this help

--testws              Test the webservice (data download)
--displaygroups       Display all the groups (may be slow), to use with --verb= 1 to 3
--verb=N              Verbosity (0 to 3), 1 by default
--related=X           Display related groups. X= eg. groups-mati0938B05

Example:
/usr/bin/php local/cohortsyncup1/cli/wsgroups.php --testws --verb=2
";

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}


// Ensure errors are well explained
$CFG->debug = DEBUG_NORMAL;


if ( $options['testws'] ) {
    test_user_groups_and_roles($options['verb']);
    return 0;
}

if ( $options['displaygroups'] ) {
    display_all_groups($options['verb']);
    return 0;
}

if ( $options['related'] ) {
    $rgroups = get_related_groups($options['related']);
    print_r($rgroups);
    return 0;
}

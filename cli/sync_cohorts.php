<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \local_cohortsyncup1\diagnostic;

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/local/cohortsyncup1/locallib.php');

// now get cli options
list($options, $unrecognized) = cli_get_params([
        'help'=>false, 'verb'=>1,
        'print-last'=>false, 'testws'=>false, 'checking'=>false, 'statistics'=>false,
        'cleanall'=>false, 'force'=>false, 'delete-old'=>false, 'allGroups'=>false,
        'since'=>false, 'init'=>false]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Synchronize cohorts from PAGS webservice. Normally, to be executed by a cron job.

Options:
--since=(timestamp)   Apply only to users synchronized since given timestamp. If not set, use last cohort sync.
--allGroups           Uses 'allGroups' webservice instead of the standard one (from users)
--init                Apply to all users ever synchronized (like --since=0)
--help                Print out this help

--print-last          Display last syncs (diagnostic)
--checking            Performs various checkings on database consistency and display results
--statistics          Display various statistics
--verb=N              Verbosity (0 to 3), 1 by default

--delete-old   /!\    Delete cohorts still in database but not in webservice results anymore. One shot.
--cleanall            Empty cohort_members, then cohort
  --force      /!\    Do cleanall, even if it breaks enrolments. DO NOT USE UNLESS EMERGENCY!

If you want to force initialization, you should execute --cleanall first but it may be faster
to manually empty tables cohort and cohort_members with the following MySQL command:
DELETE FROM cohort, cohort_members  USING cohort INNER JOIN cohort_members
    WHERE cohort.component = 'local_cohortsyncup1' AND cohort.id = cohort_members.cohortid;

Example:
/usr/bin/php local/cohortsyncup1/cli/sync_cohorts.php --init --verb=2

";

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}

if ( $options['checking'] ) {
    $diagcohorts = new diagnostic(1);
    $diagcohorts->check_database($options['verb']);
    return 0;
}

if ( $options['statistics'] ) {
    $diagcohorts = new diagnostic(1);
    $diagcohorts->cohort_statistics($options['verb']);
    return 0;
}

if ( $options['print-last'] ) {
    $diagcohorts = new diagnostic(1);
    echo "last sync from users = \n";
    print_r($diagcohorts->get_cohort_last_sync('syncFromUsers'));
    echo "last sync AllGroups = \n";
    print_r($diagcohorts->get_cohort_last_sync('syncAllGroups'));
    return 0;
}


if ( $options['cleanall'] ) {
    cohorts_cleanall($options['force']);
    return 0;
}

if ( $options['delete-old'] ) {
    $res = cli_delete_missing_cohorts($options['verb']);
    return $res;
}


if ( $options['init'] ) {
    $since = 0;
} elseif ( $options['since'] || $options['since'] === '0' ) {
    $since = $options['since'];
} else {
    $diagcohorts = new diagnostic(1);
    if ($options['allGroups']) {
        $last = $diagcohorts->get_cohort_last_sync('syncAllGroups');
    } else {
        $last = $diagcohorts->get_cohort_last_sync('syncFromUsers');
    }
    $since = $last['begin'];
}

if ($options['allGroups']) {
    sync_cohorts_all_groups($since, 0, $options['verb']);
} else {
    sync_cohorts_from_users($since, 0, $options['verb']);
}

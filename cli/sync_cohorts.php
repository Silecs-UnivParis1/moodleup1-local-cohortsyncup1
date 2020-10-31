<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \local_cohortsyncup1\diagnostic;
use \local_cohortsyncup1\synchronize;

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/local/cohortsyncup1/locallib.php');

// now get cli options
list($options, $unrecognized) = cli_get_params([
        'help'=>false, 'verbose'=>1,
        'print-last'=>false, 'testws'=>false, 'checking'=>false, 'statistics'=>false,
        'sync-all-groups'=>false, 'sync-from-users'=>false, 'limit'=>0,
        'clean-all'=>false, 'force'=>false, 'delete-old'=>false, 'yearly-rotate'=>false,
        'since'=>false, 'init'=>false]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Synchronize cohorts from PAGS webservice. Normally, to be executed by a cron job.

Options:
--sync-all-groups     Uses 'allGroups' webservice for synchro (new standard process)
--sync-from-users     Uses processs from users for synchro (obsolete original process)
--since=(timestamp)   Apply only to users synchronized since given timestamp. If not set, use last cohort sync.
--init                Apply to all users ever synchronized (like --since=0)
--limit=N             Force a numeric limit to sync process
--help                Print out this help

--print-last          Display last syncs (diagnostic)
--checking            Performs various checkings on database consistency and display results
--statistics          Display various statistics
--verbose=N           Verbosity (0 to 3), 1 by default

--yearly-rotate       À lancer une fois par an pour la rotation des cohortes annualisées, après la mise à jour du paramètre cohort_period
--delete-old   /!\    Delete cohorts still in database but not in webservice results anymore. One shot.
--clean-all           Empty cohort_members, then cohort
  --force      /!\    Do cleanall, even if it breaks enrolments. DO NOT USE UNLESS EMERGENCY!

If you want to force initialization, you should execute --cleanall first but it may be faster
to manually empty tables cohort and cohort_members with the following MySQL command:
DELETE FROM cohort, cohort_members  USING cohort INNER JOIN cohort_members
    WHERE cohort.component = 'local_cohortsyncup1' AND cohort.id = cohort_members.cohortid;

Example:
/usr/bin/php local/cohortsyncup1/cli/sync_cohorts.php --sync-from-users --init --verbose=2

";

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}

if ( $options['checking'] ) {
    $diagcohorts = new diagnostic(1);
    $diagcohorts->check_database($options['verbose']);
    return 0;
}

if ( $options['statistics'] ) {
    $diagcohorts = new diagnostic(1);
    $diagcohorts->cohort_statistics($options['verbose']);
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


if ( $options['clean-all'] ) {
    $sync = new synchronize($options['verbose']);
    $sync->clean_all($options['force']);
    return 0;
}

if ( $options['delete-old'] ) {
    $sync = new synchronize($options['verbose']);
    $res = $sync->cli_delete_missing_cohorts();
    return $res;
}

if ( $options['yearly-rotate'] ) {
    $equivalence = new equivalence(1);
    $equivalence->yearly_rotate();
    return 0;
}


if ( $options['init'] ) {
    $since = 0;
} elseif ( $options['since'] || $options['since'] === '0' ) {
    $since = $options['since'];
} else {
    $diagcohorts = new diagnostic(1);
    if ($options['sync-all-groups']) {
        $last = $diagcohorts->get_cohort_last_sync('syncAllGroups');
    }
    if ($options['sync-from-users']) {
        $last = $diagcohorts->get_cohort_last_sync('syncFromUsers');
    }
    $since = $last['begin'];
}

if ($options['sync-all-groups']) {
    $sync = new synchronize($options['verbose']);
    $sync->sync_all_groups($since, $options['limit']);
    return 0;
}

if ($options['sync-from-users']) {
    $sync = new synchronize($options['verbose']);
    $sync->sync_from_users($since, $options['limit']);
    return 0;
}

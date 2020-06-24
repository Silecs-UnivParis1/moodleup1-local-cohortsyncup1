<?php

define('CLI_SCRIPT', true);
require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file.
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->dirroot.'/local/cohortsyncup1/locallib.php');
require_once($CFG->dirroot.'/local/cohortsyncup1/lib.php');

// now get cli options
list($options, $unrecognized) = cli_get_params(
    array('help'=>false, 'year'=>'', 'etab'=>''),
    array('h'=>'help')
    );

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$help =
"Update cohorts of courses in a specific category
Options:
  --year=...          year code, ex. '2012-2013'
  --etab=...          etablissement code, ex. 'UP1'
-h, --help            Print out this help

";

if ( ! empty($options['help']) ) {
    echo $help;
    return 0;
}


// Ensure errors are well explained
$CFG->debug = DEBUG_NORMAL;

$yearcode = $options['year']; 
$etabcode = $options['etab'];
$yearid = $DB->get_field('course_categories', 'id', array('idnumber'=>'1:'.$yearcode, 'depth'=>1), MUST_EXIST);
$etabid = $DB->get_field('course_categories', 'id', array('idnumber'=>'2:'.$yearcode.'/'.$etabcode, 'depth'=>2, 'parent'=>$yearid), MUST_EXIST);
$catpath = '/' . $yearid . '/' . $etabid . '/%';

$categories = $DB->get_fieldset_select('course_categories', 'id', "path LIKE ?", array($catpath));
list ($inSql, $inParams) = $DB->get_in_or_equal($categories);
$courses = $DB->get_fieldset_select('course', 'id', 'category ' . $inSql, $inParams);

$cohort2enrols = array();
foreach ($courses as $courseid) {
  $enrols = $DB->get_records('enrol', array("enrol" => 'cohort', 'courseid' => $courseid), null, 'id,customint1,courseid');

  foreach ($enrols as $enrol) {
    $cohortidnumber = $DB->get_field('cohort', 'idnumber', array('id' => $enrol->customint1));
    if (!isset($cohort2enrols[$cohortidnumber])) {
      $cohort2enrols[$cohortidnumber] = array();
    }
    $cohort2enrols[$cohortidnumber][] = $enrol;
  }
}


foreach ($cohort2enrols as $cohortidnumber => $enrols) {
    $res = get_equivalent_cohorts(array($cohortidnumber));
    if ($res['new']) {
      $new = $res['new'][0];
      $newId = $DB->get_field('cohort', 'id', array('idnumber' => $new), MUST_EXIST);
      foreach ($enrols as $enrol) {
	echo "updating course #" . $enrol->courseid . " to use cohort $new (replacing enrol #" . $enrol->id . " customint1 " . $enrol->customint1 . " with $newId)\n";
	$enrol->customint1 = $newId;
	$DB->update_record('enrol', $enrol, true);
      }
    } else if ($res['unchanged']) {
      echo "$cohortidnumber unchanged\n";
    } else {
      $courses = array();
      foreach ($enrols as $enrol) $courses[] = $enrol->courseid;
      echo "no corresponding cohort found for $cohortidnumber (used by courses " . implode($courses, ' ') . ")\n";
    }
}


<?php
/**
 * Displays the content of a cohort.
 *
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * source :
 */

require('../../config.php');
require($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/cohort/lib.php');

$id   = optional_param('id', 0, PARAM_INT);
$name = optional_param('name', 0, PARAM_RAW_TRIMMED);

require_login();

if ($id) {
    $cohort = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
    $context = context::instance_by_id($cohort->contextid, MUST_EXIST);
} else if ($name) {
    $cohort = $DB->get_record('cohort', array('name'=>$name), '*', MUST_EXIST);
    $context = context::instance_by_id($cohort->contextid, MUST_EXIST);
} else {
    redirect(new moodle_url('/cohort/index.php'));
}

require_capability('moodle/cohort:view', $context);

/* Moodle metadata */
$PAGE->set_context($context);
$PAGE->set_url('/local/cohortsyncup1/viewcohort.php', array('id' => $cohort->id));
$PAGE->set_context($context);
navigation_node::override_active_url(new moodle_url('/local/cohortsyncup1/viewcohort.php', []));
$PAGE->set_pagelayout('admin');

/* preparing data */
$main_table = new \html_table();
$table = [
    get_string('idnumber', 'cohort') => $cohort->idnumber,
    get_string('name', 'cohort') => $cohort->name,
    get_string('description', 'cohort') => format_text($cohort->description, $cohort->descriptionformat),
    get_string('context', 'role') => $context->get_context_name(),
    'Catégorie UP1' => $cohort->up1category,
    'Période UP1' => $cohort->up1period,
    'Clé UP1' => $cohort->up1key,
];
$main_table->head = array_keys($table);
$main_table->data = array(array_values($table));

$members_table = new html_table();
$members_table->width = "95%";
$members_table->head = [];
$user_fields = ['idnumber', 'username', 'lastname', 'firstname', 'email'];
foreach ($user_fields as $field) {
    $members_table->head[] = get_string($field);
}
$members_table->data = array();
$site = get_site();
foreach (get_cohort_members($cohort->id) as $user) {
    $row = [];
    $first = true;
    foreach ($user_fields as $field) {
        $text = format_text($user->$field, FORMAT_PLAIN);
        if ($first) {
            $row[] = \html_writer::link(new moodle_url('/user/view.php', ['id' => $user->id]), $text);
            $first = false;
        } else {
            $row[] = $text;
        }
    }
    $members_table->data[] = $row;
}
$main_table->head[] = get_string('memberscount', 'cohort');
$main_table->data[0][] = count($members_table->data);

/* display */
$strheading = get_string('cohort', 'cohort') . " " . format_text($cohort->name, FORMAT_PLAIN);
$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);
echo html_writer::table($main_table);
echo html_writer::table($members_table);
echo $OUTPUT->footer();

/**
 * Returns the full list of the members of a cohort.
 *
 * @global object $DB
 * @param integer $cohortid
 * @return array of user objects.
 */
function get_cohort_members($cohortid) {
    global $DB;
    $sql = "SELECT u.* FROM {user} u "
        . "INNER JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid) "
        . "ORDER BY u.lastname ASC, u.firstname ASC";
    return $DB->get_records_sql($sql, array('cohortid' => $cohortid));
}

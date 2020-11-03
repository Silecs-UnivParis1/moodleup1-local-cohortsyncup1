<?php
/**
 * UP1 Users Statistics
 *
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \local_cohortsyncup1\reporting;

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
global $PAGE, $OUTPUT;
$PAGE->set_context(context_system::instance());
$PAGE->set_url("$CFG->wwwroot/local/cohortsyncup1/viewreportusers.php");
$PAGE->navbar->add('Stats Utilisateurs');

// Print the header.
admin_externalpage_setup('local_cohortsyncup1_viewreportusers', '', null, '', array('pagelayout'=>'report'));
$titre = 'UP1 Statistiques utilisateurs';
echo $OUTPUT->header($titre);
echo $OUTPUT->heading($titre);

echo "<h2>Utilisateurs - authentification et statuts</h2>\n";
$table = new html_table();
$table->head = array('Items', 'Nb');
$table->data = reporting::get_users();
echo html_writer::table($table);

$table = new html_table();
$table->head = array('Affiliation', 'Nb');
$table->data = reporting::get_users_by_affiliation();
echo html_writer::table($table);


$linkdetails = html_writer::link(
        new moodle_url('/local/cohortsyncup1/viewreportcohorts.php' ),
        'Détails');
echo "<h2>Cohortes " . $linkdetails . "</h2>\n";
$table = new html_table();
$table->head = array('Items', 'Nb');
$table->data = reporting::get_cohorts_generic();
echo html_writer::table($table);


//***** LAST syncs
$linkdetails = html_writer::link(
        new moodle_url('/local/cohortsyncup1/viewsynchros.php', array('number'=>50)),
        'Détails');
echo "<h2>Dernières synchronisations ". $linkdetails ." </h2>\n";
$table = new html_table();
$table->head = array('Reference', 'Begin', 'End');
$table->data = reporting::get_last_synchros();
echo html_writer::table($table);

echo $OUTPUT->footer();

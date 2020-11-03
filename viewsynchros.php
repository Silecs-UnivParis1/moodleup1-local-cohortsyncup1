<?php
/**
 * UP1 Users Statistics - Last synchronizations page
 *
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \local_cohortsyncup1\reporting;

define('NO_OUTPUT_BUFFERING', true);

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
global $PAGE, $OUTPUT;
$PAGE->set_context(context_system::instance());
$PAGE->set_url("$CFG->wwwroot/local/cohortsyncup1/viewsynchros.php");
$PAGE->navbar->add('Synchronisations');

$howmany = optional_param('number', 10, PARAM_INT);
$titre = sprintf('Les %d dernières synchronisations', $howmany);
echo $OUTPUT->header($titre);
echo $OUTPUT->heading($titre);

echo "<h3>$titre LDAP</h3>\n";
$table = new html_table();
$table->head = array('Début', 'Fin', 'Action', 'Info');
$table->data = reporting::get_synchros("ldap", $howmany);
echo html_writer::table($table);

echo "<h3>$titre de cohortes</h3>\n";
$table = new html_table();
$table->head = array('Début', 'Fin', 'Action', 'Info');
$table->data = reporting::get_synchros("cohort", $howmany);
echo html_writer::table($table);

echo $OUTPUT->footer();

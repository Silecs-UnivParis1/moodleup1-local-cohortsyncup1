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
$PAGE->set_url("$CFG->wwwroot/local/cohortsyncup1/viewreportcohorts.php");
$PAGE->navbar->add('Stats Cohortes');

$titre = 'UP1 Statistiques cohortes';
echo $OUTPUT->header($titre);
echo $OUTPUT->heading($titre);
$url = "$CFG->wwwroot/local/cohortsyncup1/viewreportcohorts.php";

echo "<h2>Cohortes</h2>\n";
$table = new html_table();
$table->head = array('Items', 'Nb');
$table->data = reporting::get_cohorts_generic();
echo html_writer::table($table);

$table = new html_table();
$table->head = array('Catégorie', 'Cohortes', 'Inscriptions', 'C. inscrites');
$table->data = reporting::get_cohorts_criterium('up1category');
echo html_writer::table($table);

$table = new html_table();
$table->head = array('Période', 'Cohortes', 'Inscriptions', 'C. inscrites');
$table->data = reporting::get_cohorts_criterium('up1period');
echo html_writer::table($table);


echo "<h2>Effectifs</h2>\n";

//***** TOP NN cohorts
$linkdetails = html_writer::link(
        new moodle_url('/local/cohortsyncup1/viewtopcohorts.php', array('number'=>10)),
        'Détails');
echo "<h3>Cohortes - top 5 ". $linkdetails ." </h3>\n";
$table = new html_table();
$table->head = array('Effectif', 'Id', 'Nom');
$table->data = reporting::get_cohorts_top(5, false);
echo html_writer::table($table);

echo "<h3>Cohortes - top 3 par préfixe</h3>\n";
$table = new html_table();
$table->head = array('Effectif', 'Id', 'Nom');
$table->data = reporting::get_cohorts_top_by_prefix(3);
echo html_writer::table($table);

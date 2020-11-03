<?php
/**
 * UP1 Users Statistics - Top NN cohorts page
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
$PAGE->set_url("$CFG->wwwroot/local/cohortsyncup1/viewtopcohorts.php");
$PAGE->navbar->add('Stats Top cohortes');

$howmany = optional_param('number', 10, PARAM_INT);
$prefix = optional_param('prefix', '', PARAM_ALPHANUMEXT);

// admin_externalpage_setup('local_cohortsyncup1_viewtopcohorts', '', null, '', array('pagelayout'=>'report'));
$titre = 'Cohortes - top '.$howmany;
echo $OUTPUT->header($titre);
echo $OUTPUT->heading($titre);

$table = new html_table();
$table->head = array('Effectif', 'Nom', 'Id');
if (empty($prefix)) {
    $table->data = reporting::get_cohorts_top($howmany, false);
} else {
    $table->data = reporting::get_cohorts_top($howmany, $prefix);
}
echo html_writer::table($table);

echo $OUTPUT->footer();

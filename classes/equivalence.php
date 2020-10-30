<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohortsyncup1;

/**
 * cette classe calcule les équivalences de cohortes pour les cohortes annualisées d'une année à la suivante
 * elle n'est plus utilisée que par l'assistant de cours
 */
class equivalence
{
    private $verbose;

    /**
     *
     * @param int $verbose
     */
    function __construct(int $verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * search "new" cohorts equivalent to "old" ones, for yearly cohorts
     * @param array $old_idnumbers
     * @param int $verbose
     * @return assoc. array (array)
     */
    public function get_equivalent_cohorts($old_idnumbers, $verbose=1) {
        global $DB;
        $tablemigrate = "up1_migrate_cohort_idnumber";
        if (! $DB->get_manager()->table_exists($tablemigrate) ) {
            die("Warning: table $tablemigrate does not exist.");
        }
        $res = ['new' => [], 'notfound' => [], 'unchanged' => []];
        $curyear = get_config('local_cohortsyncup1', 'cohort_period');

        foreach ($old_idnumbers as $idnumber) {
            $dbcohort = $DB->get_record('cohort', array('idnumber' => $idnumber), '*', MUST_EXIST);

            if (empty($dbcohort->up1period)) {
                $res['unchanged'][] = $idnumber;
                continue;
            } 
            if ($dbcohort->up1period == $curyear) {
                $res['unchanged'][] = $idnumber;
                continue;
            }
            $raw = $this->get_raw_idnumber($idnumber);
            $rawNew = $DB->get_field($tablemigrate, 'new', ['old' => $raw]);
            if ($rawNew) {
                $this->vecho("$raw has changed code, it is now $rawNew\n");
                $raw = $rawNew;
            }
            $potidnumber = $raw . '-' . $curyear; // potential idnumber
            if ( $DB->record_exists('cohort', ['idnumber' => $potidnumber]) ) {
                $res['new'][] = $potidnumber;
            } else {
                $res['notfound'][] = $potidnumber;
            }            
        }
        return $res;
    }

    /**
     * text to explain in user interface the reuse of "old" cohorts (to be used in wizard)
     * @param array $equivs as computed by the previous function
     */
    public function explain_equivalent_cohorts($equivs) {
        if ($equivs['new']) {
            echo "Les cohortes annualisées suivantes ont été reconnues et leurs équivalentes actuelles préselectionnées :\n<ul>\n";
            foreach ($equivs['new'] as $idnumber) {
                echo '<li>' . $idnumber . "</li>\n";
            }
            echo "</ul>\n";
        }
        if ($equivs['notfound']) {
            echo "Les cohortes annualisées suivantes n'ont apparemment pas d'équivalentes actuelles :\n<ul>\n";
            foreach ($equivs['notfound'] as $idnumber) {
                echo '<li>' . $idnumber . "</li>\n";
            }
            echo "</ul>\n";
        }
        if ($equivs['unchanged']) {
            echo "Les cohortes suivantes sont toujours valables : <br />\n<ul>\n";
            foreach ($equivs['unchanged'] as $idnumber) {
                echo '<li>' . $idnumber . "</li>\n";
            }
            echo "</ul>\n";
        }
        return true;
    }


    /**
     * find the raw idnumber for a yearly cohort (unchanged if not yearly)  eg. 2017-2018 => 2017
     * @param type $idnumber
     * @return string
     */
    private function get_raw_idnumber($idnumber) {
        if ( preg_match('/^(.+)-(20[12][0-9])$/', $idnumber, $matches) ) {
            return $matches[1];
        } else {
            return $idnumber;
        }
    }

    private function vecho($text)
    {
        if ($this->verbose) {
            echo $text;
        }
    }

}
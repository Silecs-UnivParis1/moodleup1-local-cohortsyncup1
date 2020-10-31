<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_cohortsyncup1_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this

    if ($oldversion < 2014052001) {
        $table = new xmldb_table('cohort');
        $field1 = new xmldb_field('up1category', XMLDB_TYPE_CHAR, '100', null, false, false, '');
        // up1category is one of the cohort categories, as defined by the function groupKeyToCategory()
        $field2 = new xmldb_field('up1period', XMLDB_TYPE_CHAR, '100', null, false, false, '');
        if ( ! $dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        if ( ! $dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
    }

        if ($oldversion < 2014052200) {
        $table = new xmldb_table('cohort');
        $field1 = new xmldb_field('up1key', XMLDB_TYPE_CHAR, '100', null, false, false, '');
        // up1key is exactly the upstream key if this cohort is to be synced, or '' otherwise
        if ( ! $dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
    }



    if ($oldversion < 2016072000) {
        $table = new xmldb_table('up1_cohortsync_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timebegin', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, false, '0');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', true, XMLDB_NOTNULL, false, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '100', null, null, false, '');
        $table->add_field('info', XMLDB_TYPE_TEXT, 'big', null, null, false, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
		$dbman->create_table($table);
		upgrade_plugin_savepoint(true, 2016072000, 'local', 'cohortsyncup1');
    }

    if ($oldversion < 2016072001) {
        $cnt = upgrade_logs_to_specific_table('local_cohortsyncup1', 'cohort');
        echo "Migrated cohort sync logs: " . $cnt['begin'] . " begins / " . $cnt['end'] . " ends. \n";

        $cnt = upgrade_logs_to_specific_table('auth_ldapup1', 'ldap');
        echo "Migrated ldap sync logs: " . $cnt['begin'] . " begins / " . $cnt['end'] . " ends. \n";        
    }

    return true;
}

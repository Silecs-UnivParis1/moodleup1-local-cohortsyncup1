<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2020 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2020110300;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2020060900;        // Requires this Moodle version
$plugin->component = 'local_cohortsyncup1';       // Full name of the plugin (used for diagnostics)

$plugin->cron      = 0;
$plugin->maturity  = MATURITY_RC;
$plugin->release   = 'TODO';

$plugin->dependencies = array(
    'auth_ldapup1' => 2013101700,
    'local_mwsgroups' => 2014090200,
);
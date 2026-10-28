<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Creates supporting records that are not part of install.xml.
 */
function xmldb_local_isgcorp_install(): void {
    global $CFG;
    require_once($CFG->dirroot . '/local/isgcorp/lib.php');
    local_isgcorp_ensure_certificate_profile_field();
    local_isgcorp_ensure_workload_field();
}

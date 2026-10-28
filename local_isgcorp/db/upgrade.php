<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Rotina de upgrade do local_isgcorp.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_isgcorp_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082402) {
        $table = new xmldb_table('local_isgcorp_trilha');
        $field = new xmldb_field('category', XMLDB_TYPE_CHAR, '50', null, false, false, null, 'level');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082402, 'local', 'isgcorp');
    }

    if ($oldversion < 2026083001) {
        $table = new xmldb_table('local_isgcorp_lesson_progress');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_key('sectionid', XMLDB_KEY_FOREIGN, ['sectionid'], 'course_sections', ['id']);
            $table->add_key('user_course_section', XMLDB_KEY_UNIQUE, ['userid', 'courseid', 'sectionid']);

            $table->add_index('completed', XMLDB_INDEX_NOTUNIQUE, ['completed']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026083001, 'local', 'isgcorp');
    }

    if ($oldversion < 2026083002) {
        $table = new xmldb_table('local_isgcorp_lesson_progress');
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, false, false, null, 'courseid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $records = $DB->get_records_sql("
            SELECT lp.id, MIN(cm.id) AS cmid, COUNT(cm.id) AS totalmods
              FROM {local_isgcorp_lesson_progress} lp
              JOIN {course_modules} cm
                ON cm.course = lp.courseid
               AND cm.section = lp.sectionid
             WHERE lp.cmid IS NULL
          GROUP BY lp.id
        ");

        foreach ($records as $record) {
            if ((int) $record->totalmods !== 1 || empty($record->cmid)) {
                continue;
            }

            $DB->update_record('local_isgcorp_lesson_progress', (object) [
                'id' => (int) $record->id,
                'cmid' => (int) $record->cmid,
            ]);
        }

        $oldkey = new xmldb_key('user_course_section', XMLDB_KEY_UNIQUE, ['userid', 'courseid', 'sectionid']);
        $dbman->drop_key($table, $oldkey);

        $cmkey = new xmldb_key('cmidfk', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $dbman->add_key($table, $cmkey);

        $newkey = new xmldb_key('user_course_cmid', XMLDB_KEY_UNIQUE, ['userid', 'courseid', 'cmid']);
        $dbman->add_key($table, $newkey);

        $index = new xmldb_index('cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026083002, 'local', 'isgcorp');
    }

    if ($oldversion < 2026100201) {
        $table = new xmldb_table('local_isgcorp_certificate');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('trilhaid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('code', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('trilhaid', XMLDB_KEY_FOREIGN, ['trilhaid'], 'local_isgcorp_trilha', ['id']);
            $table->add_key('user_trilha', XMLDB_KEY_UNIQUE, ['userid', 'trilhaid']);
            $table->add_index('code', XMLDB_INDEX_UNIQUE, ['code']);
            $dbman->create_table($table);
        }

        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        local_isgcorp_ensure_workload_field();

        upgrade_plugin_savepoint(true, 2026100201, 'local', 'isgcorp');
    }

    if ($oldversion < 2026100901) {
        // Trail certificates used a different completion rule and cannot be carried over safely.
        $table = new xmldb_table('local_isgcorp_certificate');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('code', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('user_course', XMLDB_KEY_UNIQUE, ['userid', 'courseid']);
        $table->add_index('code', XMLDB_INDEX_UNIQUE, ['code']);
        $dbman->create_table($table);

        upgrade_plugin_savepoint(true, 2026100901, 'local', 'isgcorp');
    }

    if ($oldversion < 2026100902) {
        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        local_isgcorp_ensure_certificate_profile_field();

        upgrade_plugin_savepoint(true, 2026100902, 'local', 'isgcorp');
    }

    if ($oldversion < 2026101502) {
        $table = new xmldb_table('local_isgcorp_certname_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('changedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('oldvalue', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('newvalue', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('isinitial', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('changedby', XMLDB_KEY_FOREIGN, ['changedby'], 'user', ['id']);
            $table->add_index('userid_timecreated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
            $dbman->create_table($table);
        }

        $sql = "SELECT uid.userid, uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :shortname AND uid.data <> ''";
        foreach ($DB->get_records_sql($sql, ['shortname' => 'isg_certificatename']) as $profiledata) {
            $DB->insert_record('local_isgcorp_certname_log', (object) [
                'userid' => (int) $profiledata->userid,
                'changedby' => (int) $profiledata->userid,
                'oldvalue' => '',
                'newvalue' => trim((string) $profiledata->data),
                'isinitial' => 1,
                'timecreated' => time(),
            ]);
        }

        upgrade_plugin_savepoint(true, 2026101502, 'local', 'isgcorp');
    }

    if ($oldversion < 2026101503) {
        $table = new xmldb_table('local_isgcorp_certname_log');
        $field = new xmldb_field('isinitial', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'newvalue');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('local_isgcorp_certname_log', 'isinitial', 1);
        }

        upgrade_plugin_savepoint(true, 2026101503, 'local', 'isgcorp');
    }

    if ($oldversion < 2026102200) {
        // O certificado passa a ser emitido por trilha, ao concluir todos os modulos.
        $table = new xmldb_table('local_isgcorp_trilha_cert');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('trilhaid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('code', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('trilhaid', XMLDB_KEY_FOREIGN, ['trilhaid'], 'local_isgcorp_trilha', ['id']);
        $table->add_key('user_trilha', XMLDB_KEY_UNIQUE, ['userid', 'trilhaid']);
        $table->add_index('code', XMLDB_INDEX_UNIQUE, ['code']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026102200, 'local', 'isgcorp');
    }

    return true;
}

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
    global $DB;

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

        $handler = \core_course\customfield\course_handler::create();
        $hoursfield = $DB->get_record('customfield_field', [
            'shortname' => 'isg_cargahoraria',
        ]);
        if (!$hoursfield) {
            $categoryrecord = (object) [
                'name' => 'Universidade Corporativa ISG',
                'component' => $handler->get_component(),
                'area' => $handler->get_area(),
                'itemid' => $handler->get_itemid(),
                'contextid' => $handler->get_configuration_context()->id,
                'sortorder' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $category = \core_customfield\category_controller::create(0, $categoryrecord, $handler);
            $category->save();

            $fieldrecord = (object) [
                'categoryid' => $category->get('id'),
                'type' => 'text',
                'name' => 'Carga horaria (horas)',
                'shortname' => 'isg_cargahoraria',
                'description' => 'Carga horaria usada na soma do certificado da trilha.',
                'descriptionformat' => FORMAT_PLAIN,
                'sortorder' => 0,
                'configdata' => json_encode(['defaultvalue' => '', 'displaysize' => 10, 'maxlength' => 10]),
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $field = \core_customfield\field_controller::create(0, $fieldrecord, $category);
            $field->save();
        }

        upgrade_plugin_savepoint(true, 2026100201, 'local', 'isgcorp');
    }

    return true;
}

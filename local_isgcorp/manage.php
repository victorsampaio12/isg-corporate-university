<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Lista as trilhas cadastradas, com links pra criar/editar/apagar.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('local/isgcorp:manage', $context);

$PAGE->set_url(new moodle_url('/local/isgcorp/manage.php'));
$PAGE->set_context($context);
admin_externalpage_setup('local_isgcorp_manage');
$PAGE->navbar->ignore_active();

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/isgcorp/training.php'),
    '&larr; ' . get_string('backtotrainingdashboard', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary mb-3']
);
echo '<div class="isg-admin-page-head"><div><h2>' . get_string('managetrilhas', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('trainingtrailsdesc', 'local_isgcorp') . '</p></div>';
echo html_writer::link(
    new moodle_url('/local/isgcorp/edit.php'),
    get_string('addtrilha', 'local_isgcorp'),
    ['class' => 'btn btn-primary']
);
echo '</div>';

$trilhas = $DB->get_records('local_isgcorp_trilha', null, 'sortorder ASC, id ASC');

if (empty($trilhas)) {
    echo html_writer::div(get_string('notrilhas', 'local_isgcorp'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable isg-assessment-admin-table';
    $table->head = [
        get_string('trilhaname', 'local_isgcorp'),
        get_string('trilhalevel', 'local_isgcorp'),
        get_string('trilhahours', 'local_isgcorp'),
        get_string('trilhacourses', 'local_isgcorp'),
        get_string('status'),
        get_string('actionscolumn', 'local_isgcorp'),
    ];

    foreach ($trilhas as $trilha) {
        $coursecount = $DB->count_records('local_isgcorp_trilha_course', ['trilhaid' => $trilha->id]);

        $editurl = new moodle_url('/local/isgcorp/edit.php', ['id' => $trilha->id]);
        $deletelink = local_isgcorp_delete_action(
            '/local/isgcorp/delete.php',
            ['id' => $trilha->id],
            get_string('confirmdelete', 'local_isgcorp', format_string($trilha->name)),
            'delete-trail-' . $trilha->id
        );

        $viewurl = new moodle_url('/local/isgcorp/view.php', ['id' => $trilha->id]);
        $actions = html_writer::div(
            html_writer::link($editurl, get_string('edit')) .
            ' <span aria-hidden="true">|</span> ' .
            html_writer::link($viewurl, get_string('view')) .
            ' <span aria-hidden="true">|</span> ' . $deletelink,
            'isg-admin-row-actions'
        );

        $levelstring = get_string('level' . $trilha->level, 'local_isgcorp');

        $table->data[] = [
            format_string($trilha->name),
            $levelstring,
            $trilha->estimatedhours . 'h',
            $coursecount,
            $trilha->visible
                ? html_writer::span(get_string('statusactive', 'local_isgcorp'), 'badge badge-success')
                : html_writer::span(get_string('statusdraft', 'local_isgcorp'), 'badge badge-secondary'),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();

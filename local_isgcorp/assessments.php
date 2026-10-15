<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_assessments_manage');
$PAGE->navbar->ignore_active();

$courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC');
$table = new html_table();
$table->attributes['class'] = 'generaltable isg-assessment-admin-table';
$table->head = [
    get_string('course'),
    get_string('assessmenttrail', 'local_isgcorp'),
    get_string('courseassessment', 'local_isgcorp'),
    get_string('assessmentquestions', 'local_isgcorp'),
    get_string('status'),
    get_string('actions'),
];

foreach ($courses as $course) {
    $trail = local_isgcorp_get_trilha_by_courseid((int)$course->id);
    $assessment = local_isgcorp_get_course_assessment((int)$course->id);
    $actions = [];

    if ($assessment) {
        $questioncount = $DB->count_records('quiz_slots', ['quizid' => $assessment->id]);
        $status = $questioncount > 0
            ? html_writer::span(get_string('assessmentready', 'local_isgcorp'), 'badge badge-success')
            : html_writer::span(get_string('assessmentincompleteconfig', 'local_isgcorp'), 'badge badge-warning');
        $actions[] = html_writer::link(
            new moodle_url('/mod/quiz/edit.php', ['cmid' => $assessment->cmid]),
            get_string('assessmenteditquestions', 'local_isgcorp')
        );
        $actions[] = html_writer::link(
            new moodle_url('/course/modedit.php', ['update' => $assessment->cmid, 'return' => 1]),
            get_string('assessmentsettings', 'local_isgcorp')
        );
        $actions[] = local_isgcorp_delete_action(
            '/local/isgcorp/assessment_delete.php',
            ['cmid' => $assessment->cmid],
            get_string('confirmdeleteassessment', 'local_isgcorp', format_string($assessment->name)),
            'delete-assessment-' . $assessment->cmid
        );
        $assessmentname = format_string($assessment->name);
    } else {
        $questioncount = 0;
        $status = html_writer::span(get_string('assessmentnone', 'local_isgcorp'), 'badge badge-secondary');
        $actions[] = html_writer::link(
            new moodle_url('/local/isgcorp/assessment_create.php', ['courseid' => $course->id]),
            get_string('createassessment', 'local_isgcorp')
        );
        $assessmentname = '&mdash;';
    }

    $table->data[] = [
        format_string($course->fullname),
        $trail ? format_string($trail->name) : '&mdash;',
        $assessmentname,
        (string)$questioncount,
        $status,
        html_writer::div(implode(' <span aria-hidden="true">|</span> ', $actions), 'isg-admin-row-actions'),
    ];
}

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/isgcorp/training.php'),
    '&larr; ' . get_string('backtotrainingdashboard', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary mb-3']
);
echo '<div class="isg-admin-page-head"><div><h2>' . get_string('manageassessments', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('assessmentadmindesc', 'local_isgcorp') . '</p></div>';
echo html_writer::link(
    new moodle_url('/local/isgcorp/assessment_create.php'),
    get_string('createassessment', 'local_isgcorp'),
    ['class' => 'btn btn-primary']
);
echo '</div>';
echo html_writer::table($table);
echo $OUTPUT->footer();

<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_assessment_create');

$form = new \local_isgcorp\form\assessment_form();
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid]);
    $form->set_data((object)[
        'courseid' => $courseid,
        'name' => $course ? get_string('courseassessment', 'local_isgcorp') . ' - ' . format_string($course->fullname) : '',
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/isgcorp/assessments.php'));
}
if ($data = $form->get_data()) {
    $cm = local_isgcorp_create_course_assessment((int)$data->courseid, $data->name);
    redirect(
        new moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]),
        get_string('assessmentcreated', 'local_isgcorp'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo '<div class="isg-admin-form-card">';
echo '<h2>' . get_string('createassessment', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('assessmentcoursedesc', 'local_isgcorp') . '</p>';
$form->display();
echo '</div>';
echo $OUTPUT->footer();


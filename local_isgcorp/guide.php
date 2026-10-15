<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_admin_guide');

$steps = [
    ['number' => '1', 'title' => get_string('guidecoursetitle', 'local_isgcorp'), 'description' => get_string('guidecoursedesc', 'local_isgcorp'), 'items' => [get_string('guidecourseitem1', 'local_isgcorp'), get_string('guidecourseitem2', 'local_isgcorp')], 'actions' => [[get_string('addnewcourseadmin', 'local_isgcorp'), new moodle_url('/course/edit.php', ['category' => 0]), 'primary'], [get_string('gotonativecourses', 'local_isgcorp'), new moodle_url('/local/isgcorp/courses_manage.php'), 'secondary']]],
    ['number' => '2', 'title' => get_string('guidelessontitle', 'local_isgcorp'), 'description' => get_string('guidelessondesc', 'local_isgcorp'), 'items' => [get_string('guidelessonitem1', 'local_isgcorp'), get_string('guidelessonitem2', 'local_isgcorp')], 'actions' => [[get_string('guideopenlessons', 'local_isgcorp'), new moodle_url('/local/isgcorp/courses_manage.php'), 'primary']]],
    ['number' => '3', 'title' => get_string('guideassessmenttitle', 'local_isgcorp'), 'description' => get_string('guideassessmentdesc', 'local_isgcorp'), 'items' => [get_string('guideassessmentitem1', 'local_isgcorp'), get_string('guideassessmentitem2', 'local_isgcorp')], 'actions' => [[get_string('createassessment', 'local_isgcorp'), new moodle_url('/local/isgcorp/assessment_create.php'), 'primary'], [get_string('manageassessments', 'local_isgcorp'), new moodle_url('/local/isgcorp/assessments.php'), 'secondary']]],
    ['number' => '4', 'title' => get_string('guidetrailtitle', 'local_isgcorp'), 'description' => get_string('guidetraildesc', 'local_isgcorp'), 'items' => [get_string('guidetrailitem1', 'local_isgcorp'), get_string('guidetrailitem2', 'local_isgcorp')], 'actions' => [[get_string('addtrilha', 'local_isgcorp'), new moodle_url('/local/isgcorp/edit.php'), 'primary'], [get_string('managetrilhas', 'local_isgcorp'), new moodle_url('/local/isgcorp/manage.php'), 'secondary']]],
];

echo $OUTPUT->header();
echo html_writer::start_div('isg-admin-guide');
echo html_writer::start_div('isg-guide-hero');
echo html_writer::tag('span', get_string('guideeyebrow', 'local_isgcorp'), ['class' => 'isg-guide-eyebrow']);
echo html_writer::tag('h2', get_string('guidetitle', 'local_isgcorp'));
echo html_writer::tag('p', get_string('guidesubtitle', 'local_isgcorp'));
echo html_writer::end_div();
echo html_writer::start_div('isg-guide-flow');
foreach ($steps as $step) {
    echo html_writer::start_tag('article', ['class' => 'isg-guide-card']);
    echo html_writer::tag('span', $step['number'], ['class' => 'isg-guide-step', 'aria-hidden' => 'true']);
    echo html_writer::tag('h3', $step['title']);
    echo html_writer::tag('p', $step['description'], ['class' => 'isg-guide-description']);
    echo html_writer::start_tag('ul');
    foreach ($step['items'] as $item) { echo html_writer::tag('li', $item); }
    echo html_writer::end_tag('ul');
    echo html_writer::start_div('isg-guide-actions');
    foreach ($step['actions'] as [$label, $url, $style]) { echo html_writer::link($url, $label, ['class' => $style === 'primary' ? 'btn btn-primary' : 'btn btn-outline-primary']); }
    echo html_writer::end_div();
    echo html_writer::end_tag('article');
}
echo html_writer::end_div();
echo html_writer::start_tag('aside', ['class' => 'isg-guide-footer']);
echo html_writer::start_div('isg-guide-footer-copy');
echo html_writer::tag('h3', get_string('guidemonitortitle', 'local_isgcorp'));
echo html_writer::tag('p', get_string('guidemonitordesc', 'local_isgcorp'));
echo html_writer::end_div();
echo html_writer::start_div('isg-guide-actions');
echo html_writer::link(new moodle_url('/local/isgcorp/dashboard.php'), get_string('guideopendashboard', 'local_isgcorp'), ['class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_isgcorp_branding']), get_string('guideopenbranding', 'local_isgcorp'), ['class' => 'btn btn-outline-primary']);
echo html_writer::end_div();
echo html_writer::end_tag('aside');
echo html_writer::end_div();
echo $OUTPUT->footer();

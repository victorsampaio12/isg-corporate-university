<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_isgcorp_training');

$cards = [
    [
        'icon' => local_isgcorp_icon('graduation-cap'),
        'title' => get_string('trainingtrails', 'local_isgcorp'),
        'description' => get_string('trainingtrailsdesc', 'local_isgcorp'),
        'actions' => [
            [get_string('managetrilhas', 'local_isgcorp'), new moodle_url('/local/isgcorp/manage.php')],
            [get_string('addtrilha', 'local_isgcorp'), new moodle_url('/local/isgcorp/edit.php')],
        ],
    ],
    [
        'icon' => local_isgcorp_icon('library'),
        'title' => get_string('trainingcourses', 'local_isgcorp'),
        'description' => get_string('trainingcoursesdesc', 'local_isgcorp'),
        'actions' => [
            [get_string('gotonativecourses', 'local_isgcorp'), new moodle_url('/local/isgcorp/courses_manage.php')],
            [get_string('addnewcourseadmin', 'local_isgcorp'), new moodle_url('/course/edit.php', ['category' => 0])],
        ],
    ],
    [
        'icon' => local_isgcorp_icon('check-circle'),
        'title' => get_string('assessmentsadmin', 'local_isgcorp'),
        'description' => get_string('assessmentadmindesc', 'local_isgcorp'),
        'actions' => [
            [get_string('manageassessments', 'local_isgcorp'), new moodle_url('/local/isgcorp/assessments.php')],
            [get_string('createassessment', 'local_isgcorp'), new moodle_url('/local/isgcorp/assessment_create.php')],
        ],
    ],
];

echo $OUTPUT->header();
echo '<div class="isg-admin-hub">';
echo '<div class="isg-admin-hub-hero"><h2>' . get_string('trainingdashboard', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('trainingdashboardsubtitle', 'local_isgcorp') . '</p></div>';
echo '<div class="isg-admin-hub-grid">';
foreach ($cards as $card) {
    echo '<section class="isg-admin-hub-card">';
    echo '<span class="isg-admin-hub-icon">' . $card['icon'] . '</span>';
    echo '<h3>' . $card['title'] . '</h3><p>' . $card['description'] . '</p>';
    echo '<div class="isg-admin-hub-actions">';
    foreach ($card['actions'] as [$label, $url]) {
        echo html_writer::link($url, $label, ['class' => 'btn btn-outline-primary']);
    }
    echo '</div></section>';
}
echo '</div></div>';
echo $OUTPUT->footer();

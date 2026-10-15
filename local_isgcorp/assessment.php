<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$trilhaid = optional_param('trilhaid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);

$state = local_isgcorp_get_course_assessment_state($course, (int)$USER->id);
$backurl = new moodle_url('/local/isgcorp/course.php', [
    'courseid' => $courseid,
    'trilhaid' => $trilhaid,
]);

if (!$state['quiz']) {
    redirect($backurl, get_string('assessmentmissing', 'local_isgcorp'), null, \core\output\notification::NOTIFY_ERROR);
}
if (!$state['configured']) {
    redirect(
        $backurl,
        get_string('assessmentquestioncount', 'local_isgcorp', $state['questioncount']),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
if (!$state['lessonscomplete']) {
    redirect($backurl, get_string('assessmentlocked', 'local_isgcorp'), null, \core\output\notification::NOTIFY_WARNING);
}
if ($state['passed']) {
    redirect(new moodle_url('/local/isgcorp/certificate.php', ['courseid' => $courseid]));
}
if ($state['attemptsremaining'] <= 0) {
    redirect($backurl, get_string('assessmentattemptsexhausted', 'local_isgcorp'), null, \core\output\notification::NOTIFY_ERROR);
}

redirect(new moodle_url('/mod/quiz/view.php', ['id' => $state['quiz']->cmid]));


<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');

admin_externalpage_setup('local_isgcorp_courses_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}

require_sesskey();
$courseid = required_param('courseid', PARAM_INT);
if ($courseid === (int) SITEID || !can_delete_course($courseid)) {
    throw new required_capability_exception(
        context_course::instance($courseid),
        'moodle/course:delete',
        'nopermissions',
        ''
    );
}

$course = get_course($courseid);
$transaction = $DB->start_delegated_transaction();
$DB->delete_records('local_isgcorp_trilha_course', ['courseid' => $courseid]);
$DB->delete_records('local_isgcorp_lesson_progress', ['courseid' => $courseid]);
$DB->delete_records('local_isgcorp_certificate', ['courseid' => $courseid]);
if (!delete_course($course, false)) {
    $transaction->rollback(new moodle_exception('cannotdeletecourse'));
}
$transaction->allow_commit();

redirect(
    new moodle_url('/local/isgcorp/courses_manage.php'),
    get_string('coursedeleted', 'local_isgcorp'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

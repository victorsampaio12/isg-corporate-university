<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');

admin_externalpage_setup('local_isgcorp_assessments_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}

require_sesskey();
$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$modulecontext = context_module::instance($cmid);
require_capability('moodle/course:manageactivities', $modulecontext);

course_delete_module($cmid);

redirect(
    new moodle_url('/local/isgcorp/assessments.php'),
    get_string('assessmentdeleted', 'local_isgcorp'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

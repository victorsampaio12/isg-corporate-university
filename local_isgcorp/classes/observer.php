<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_isgcorp;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the corporate learning flow.
 */
class observer {
    /**
     * Issues any learning-path certificate earned after a passing quiz attempt.
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], 'id,quiz,userid');
        if (!$attempt) {
            return;
        }

        $assessment = local_isgcorp_get_course_assessment((int)$event->courseid);
        if (!$assessment || (int)$assessment->id !== (int)$attempt->quiz) {
            return;
        }

        try {
            local_isgcorp_sync_user_certificates((int)$attempt->userid);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'certificateincomplete') {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
        } catch (\Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

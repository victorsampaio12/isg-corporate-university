<?php
// This file is part of Moodle - http://moodle.org/.

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * Restricts the ISG final assessment until every course lesson is complete.
 */
class quizaccess_isgcorp extends access_rule_base {
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $CFG;

        $lib = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (!is_readable($lib)) {
            return null;
        }
        require_once($lib);

        $assessment = local_isgcorp_get_course_assessment((int)$quizobj->get_courseid());
        if (!$assessment || (int)$assessment->id !== (int)$quizobj->get_quizid()) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        global $USER;

        $context = \context_module::instance($this->quiz->cmid);
        if (has_capability('mod/quiz:manage', $context)) {
            return false;
        }

        $state = local_isgcorp_get_course_assessment_state($this->quizobj->get_course(), (int)$USER->id);
        if (!$state['lessonscomplete']) {
            return get_string('lessonsincomplete', 'quizaccess_isgcorp');
        }
        if (!$state['configured']) {
            return get_string('invalidquestioncount', 'quizaccess_isgcorp', $state['questioncount']);
        }
        if ($state['passed']) {
            return get_string('alreadypassed', 'quizaccess_isgcorp');
        }

        return false;
    }
}


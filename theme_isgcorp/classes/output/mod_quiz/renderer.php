<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_isgcorp\output\mod_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapts learner-facing quiz messages to the ISG percentage-based rule.
 */
class renderer extends \mod_quiz\output\renderer {
    /**
     * Keeps the learner inside the custom ISG course experience.
     */
    public function view_page($course, $quiz, $cm, $context, $viewobj) {
        global $CFG;
        $lib = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (is_readable($lib)) {
            require_once($lib);
            $assessment = local_isgcorp_get_course_assessment((int)$course->id);
            if ($assessment && (int)$assessment->id === (int)$quiz->id) {
                $trail = local_isgcorp_get_trilha_by_courseid((int)$course->id);
                $viewobj->backtocourseurl = local_isgcorp_get_course_page_url((int)$course->id, $trail ? (int)$trail->id : null);
            }
        }
        return parent::view_page($course, $quiz, $cm, $context, $viewobj);
    }

    /**
     * Replaces Moodle's absolute passing grade with the equivalent percentage.
     */
    public function view_information($quiz, $cm, $context, $messages, bool $quizhasquestions = false) {
        global $CFG, $DB;
        $lib = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (is_readable($lib)) {
            require_once($lib);
            $assessment = local_isgcorp_get_course_assessment((int)$quiz->course);
            if ($assessment && (int)$assessment->id === (int)$quiz->id && (float)$quiz->grade > 0) {
                $gradepass = (float)$DB->get_field('grade_items', 'gradepass', ['courseid' => $quiz->course, 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
                $parameters = (object)['grade' => quiz_format_grade($quiz, $gradepass), 'maxgrade' => quiz_format_grade($quiz, $quiz->grade)];
                $defaultmessage = get_string('gradetopassoutof', 'quiz', $parameters);
                $percentage = (int)round(($gradepass / (float)$quiz->grade) * 100);
                foreach ($messages as &$message) {
                    if ($message === $defaultmessage) { $message = get_string('quizpassingpercentage', 'theme_isgcorp', $percentage); }
                }
                unset($message);
            }
        }
        return parent::view_information($quiz, $cm, $context, $messages, $quizhasquestions);
    }
}

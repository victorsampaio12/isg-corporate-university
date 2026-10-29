<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_isgcorp\output\mod_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapts learner-facing quiz messages to the ISG percentage-based rule.
 */
class renderer extends \mod_quiz\output\renderer {
    /**
     * Uses question-oriented labels instead of Moodle's page terminology.
     */
    protected function attempt_navigation_buttons($page, $lastpage, $navmethod = 'free') {
        $output = '';
        $output .= \html_writer::start_tag('div', ['class' => 'submitbtns']);
        if ($page > 0 && $navmethod === 'free') {
            $output .= \html_writer::empty_tag('input', [
                'type' => 'submit',
                'name' => 'previous',
                'value' => get_string('navigateprevious', 'quiz'),
                'class' => 'mod_quiz-prev-nav btn btn-secondary',
                'id' => 'mod_quiz-prev-nav',
            ]);
            $this->page->requires->js_call_amd('core_form/submit', 'init', ['mod_quiz-prev-nav']);
        }
        $nextlabel = $lastpage
            ? get_string('endtest', 'quiz')
            : get_string('nextquestion', 'theme_isgcorp');
        $output .= \html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => 'next',
            'value' => $nextlabel,
            'class' => 'mod_quiz-next-nav btn btn-primary',
            'id' => 'mod_quiz-next-nav',
        ]);
        $output .= \html_writer::end_tag('div');
        $this->page->requires->js_call_amd('core_form/submit', 'init', ['mod_quiz-next-nav']);
        return $output;
    }

    /**
     * Returns from the finished review to the custom ISG course page.
     */
    public function finish_review_link(\quiz_attempt $attemptobj) {
        global $CFG;
        $lib = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (is_readable($lib)) {
            require_once($lib);
            $courseid = (int)$attemptobj->get_courseid();
            $assessment = local_isgcorp_get_course_assessment($courseid);
            if ($assessment && (int)$assessment->id === (int)$attemptobj->get_quizid()) {
                $trail = local_isgcorp_get_trilha_by_courseid($courseid);
                $url = local_isgcorp_get_course_page_url($courseid, $trail ? (int)$trail->id : null);
                return \html_writer::link($url, get_string('backtocustomcourse', 'theme_isgcorp'), [
                    'class' => 'mod_quiz-next-nav btn btn-primary',
                ]);
            }
        }
        return parent::finish_review_link($attemptobj);
    }

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

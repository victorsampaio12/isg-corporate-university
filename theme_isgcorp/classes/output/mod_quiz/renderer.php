<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_isgcorp\output\mod_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapts learner-facing quiz messages to the ISG percentage-based rule.
 */
class renderer extends \mod_quiz\output\renderer {
    /**
     * Builds the URL for the custom learner course page.
     *
     * @param int $courseid
     * @return \moodle_url|null
     */
    private function custom_course_url(int $courseid): ?\moodle_url {
        global $CFG;

        $lib = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (!is_readable($lib)) {
            return null;
        }

        require_once($lib);
        $trail = local_isgcorp_get_trilha_by_courseid($courseid);
        return local_isgcorp_get_course_page_url($courseid, $trail ? (int)$trail->id : null);
    }

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
        $url = $this->custom_course_url((int)$attemptobj->get_courseid());
        if ($url) {
            return \html_writer::link($url, get_string('backtocustomcourse', 'theme_isgcorp'), [
                'class' => 'mod_quiz-next-nav btn btn-primary',
            ]);
        }
        return parent::finish_review_link($attemptobj);
    }

    /**
     * Keeps the learner inside the custom ISG course experience.
     */
    public function view_page($course, $quiz, $cm, $context, $viewobj) {
        $url = $this->custom_course_url((int)$course->id);
        if ($url) {
            $viewobj->backtocourseurl = $url;
        }
        return parent::view_page($course, $quiz, $cm, $context, $viewobj);
    }

    /**
     * Replaces Moodle's native course URL immediately before rendering the button.
     *
     * @param \mod_quiz\output\view_page $viewobj
     * @return string
     */
    public function view_page_buttons(\mod_quiz\output\view_page $viewobj) {
        $url = $this->custom_course_url((int)$this->page->course->id);
        if ($url) {
            $viewobj->backtocourseurl = $url;
        }

        return parent::view_page_buttons($viewobj);
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

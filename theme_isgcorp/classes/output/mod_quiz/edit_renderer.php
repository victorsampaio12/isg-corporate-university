<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_isgcorp\output\mod_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Improves the administrator-facing quiz structure editor.
 */
class edit_renderer extends \mod_quiz\output\edit_renderer {
    /**
     * Adds concise guidance before Moodle's native question editor.
     */
    public function edit_page(
        \mod_quiz\quiz_settings $quizobj,
        \mod_quiz\structure $structure,
        \core_question\local\bank\question_edit_contexts $contexts,
        \moodle_url $pageurl,
        array $pagevars
    ) {
        $guidance = \html_writer::div(
            \html_writer::tag('strong', get_string('quizbuildertiptitle', 'theme_isgcorp')) .
            \html_writer::tag('span', get_string('quizbuildertiptext', 'theme_isgcorp')),
            'isg-quiz-builder-guidance'
        );

        return $guidance . parent::edit_page($quizobj, $structure, $contexts, $pageurl, $pagevars);
    }
}


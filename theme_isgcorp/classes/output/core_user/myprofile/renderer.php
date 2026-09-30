<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_isgcorp\output\core_user\myprofile;

defined('MOODLE_INTERNAL') || die();

/**
 * Perfil enxuto para alunos da Universidade Corporativa.
 */
class renderer extends \core_user\output\myprofile\renderer {

    /**
     * Remove secoes administrativas do perfil de alunos.
     *
     * @param \core_user\output\myprofile\tree $tree
     * @return string
     */
    public function render_tree(\core_user\output\myprofile\tree $tree) {
        if (theme_isgcorp_is_privileged_user()) {
            return parent::render_tree($tree);
        }

        $html = \html_writer::start_tag('div', ['class' => 'profile_tree']);
        $hiddentitles = [get_string('reports'), get_string('miscellaneous')];
        foreach ($tree->categories as $category) {
            if (in_array($category->name, ['reports', 'miscellaneous'], true)
                    || in_array($category->title, $hiddentitles, true)) {
                continue;
            }
            $html .= $this->render($category);
        }
        $html .= \html_writer::end_tag('div');

        return $html;
    }
}

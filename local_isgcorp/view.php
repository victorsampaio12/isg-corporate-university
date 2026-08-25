<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Detalhe de uma trilha: descrição e lista de cursos que a compõem.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();

$id = required_param('id', PARAM_INT);
$trilha = $DB->get_record('local_isgcorp_trilha', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/isgcorp/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($trilha->name));
// Mesmo motivo do index.php: evitar título duplicado.

global $USER;

$courses = local_isgcorp_get_trilha_courses($trilha->id);
$levelstring = get_string('level' . $trilha->level, 'local_isgcorp');

echo $OUTPUT->header();

echo '<div class="isg-trilhas-page">';
echo '<a class="isg-link-more" href="' . (new moodle_url('/local/isgcorp/index.php'))->out() . '">&larr; ' . get_string('trilhas', 'local_isgcorp') . '</a>';

$coverurl = local_isgcorp_get_trilha_cover_url($trilha->id);
if ($coverurl) {
    echo '<div class="isg-trilha-detail-cover" style="background-image:url(' . $coverurl->out() . ')"></div>';
}

echo '<h1 class="isg-trilhas-title mt-3">' . format_string($trilha->name) . '</h1>';
echo '<p class="isg-trilhas-subtitle">' . $levelstring . ' &middot; ' . $trilha->estimatedhours . 'h &middot; ' . count($courses) . ' ' . get_string('coursescount', 'local_isgcorp') . '</p>';

if (!empty($trilha->description)) {
    echo '<div class="isg-trilha-fulldesc">' . format_text($trilha->description, $trilha->descriptionformat) . '</div>';
}

if (empty($courses)) {
    echo html_writer::div(get_string('trilhanocourses', 'local_isgcorp'), 'isg-empty');
} else {
    echo '<div class="isg-course-grid mt-4">';

    foreach ($courses as $course) {
        $percent = null;
        try {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        } catch (\Throwable $e) {
            $percent = null;
        }
        if ($percent !== null) {
            $percent = (int) round($percent);
        }

        if ($percent === null) {
            $statuslabel = get_string('statusunavailable', 'local_isgcorp');
            $barclass = 'isg-bar-gray';
        } else if ($percent <= 0) {
            $statuslabel = get_string('statusnotstarted', 'local_isgcorp');
            $barclass = 'isg-bar-gray';
        } else if ($percent < 100) {
            $statuslabel = get_string('statusinprogress', 'local_isgcorp');
            $barclass = 'isg-bar-red';
        } else {
            $statuslabel = get_string('statuscomplete', 'local_isgcorp');
            $barclass = 'isg-bar-green';
        }

        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $barwidth = $percent ?? 0;

        echo '<a class="isg-course-card" href="' . $courseurl->out() . '">';
        echo     '<span class="isg-course-status">' . $statuslabel . '</span>';
        echo     '<span class="isg-course-title">' . format_string($course->fullname) . '</span>';
        echo     '<div class="isg-course-progress-track">';
        echo         '<div class="isg-course-progress-fill ' . $barclass . '" style="width:' . $barwidth . '%"></div>';
        echo     '</div>';
        if ($percent !== null) {
            echo '<span class="isg-course-progress-label ' . $barclass . '-text">' . $percent . '% ' . get_string('concluded', 'local_isgcorp') . '</span>';
        }
        echo '</a>';
    }

    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();

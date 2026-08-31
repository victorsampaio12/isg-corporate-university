<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Pagina customizada de aula dentro da experiencia da trilha.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$trilhaid = optional_param('trilhaid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$pageurl = new moodle_url('/local/isgcorp/lesson.php', [
    'courseid' => $courseid,
    'cmid' => $cmid,
    'trilhaid' => $trilhaid,
]);
$PAGE->set_url($pageurl);
require_login($course);

$trilha = $trilhaid ? $DB->get_record('local_isgcorp_trilha', ['id' => $trilhaid]) : null;
if (!$trilha) {
    $trilha = local_isgcorp_get_trilha_by_courseid((int) $course->id);
    $trilhaid = $trilha ? (int) $trilha->id : 0;
}

global $USER;

$lessons = array_values(local_isgcorp_get_course_lessons($course, (int) $USER->id, $trilhaid ?: null));

if ($cmid <= 0 && $sectionid > 0) {
    foreach ($lessons as $lesson) {
        if ((int) $lesson['sectionid'] === $sectionid) {
            redirect($lesson['url']);
        }
    }
}

if ($cmid <= 0) {
    redirect(local_isgcorp_get_course_page_url((int) $course->id, $trilhaid ?: null));
}

$currentlesson = null;
$currentindex = null;
foreach ($lessons as $index => $lesson) {
    if ((int) $lesson['cmid'] === $cmid) {
        $currentlesson = $lesson;
        $currentindex = $index;
        break;
    }
}

if ($currentlesson === null) {
    redirect(local_isgcorp_get_course_page_url((int) $course->id, $trilhaid ?: null));
}

if ($action === 'togglecomplete' && confirm_sesskey()) {
    local_isgcorp_toggle_lesson_completion($course, $cmid, (int) $USER->id);
    redirect($pageurl);
}

local_isgcorp_mark_lesson_viewed($course, $cmid, (int) $USER->id);

$lessons = array_values(local_isgcorp_get_course_lessons($course, (int) $USER->id, $trilhaid ?: null));
$currentlesson = null;
$currentindex = null;
foreach ($lessons as $index => $lesson) {
    if ((int) $lesson['cmid'] === $cmid) {
        $currentlesson = $lesson;
        $currentindex = $index;
        break;
    }
}

if ($currentlesson === null) {
    redirect(local_isgcorp_get_course_page_url((int) $course->id, $trilhaid ?: null));
}

$context = context_course::instance($course->id);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($currentlesson['name']);

$contentitems = local_isgcorp_get_lesson_content_items($course, $cmid, (int) $USER->id);
$progress = local_isgcorp_get_manual_course_progress_summary($course, (int) $USER->id, $trilhaid ?: null);
$progresspercent = $progress['percent'] ?? 0;
$completedlessons = $progress['completed'] ?? 0;
$totallessons = $progress['total'] ?? count($lessons);
$previouslesson = $currentindex > 0 ? $lessons[$currentindex - 1] : null;
$nextlesson = $currentindex < count($lessons) - 1 ? $lessons[$currentindex + 1] : null;
$backurl = local_isgcorp_get_course_page_url((int) $course->id, $trilhaid ?: null);
$completed = !empty($currentlesson['completed']);
$showtoggle = !empty($currentlesson['cancomplete']);
$togglelabel = $completed ? get_string('marklessonincomplete', 'local_isgcorp') : get_string('marklessoncomplete', 'local_isgcorp');

if ($completed) {
    $statuslabel = get_string('lessoncomplete', 'local_isgcorp');
    $statusclass = 'is-complete';
} else if (!empty($currentlesson['tracked'])) {
    $statuslabel = get_string('lessoninprogress', 'local_isgcorp');
    $statusclass = 'is-progress';
} else {
    $statuslabel = get_string('statusunavailable', 'local_isgcorp');
    $statusclass = 'is-neutral';
}

echo $OUTPUT->header();

echo '<div class="isg-learner-page isg-lesson-page">';
echo     '<nav class="isg-learner-breadcrumbs">';
if ($trilha) {
    echo     '<a href="' . (new moodle_url('/local/isgcorp/view.php', ['id' => $trilha->id, 'tab' => 'content']))->out() . '">' . s(format_string($trilha->name)) . '</a>';
    echo     '<span>/</span>';
}
echo         '<a href="' . $backurl->out() . '">' . s(format_string($course->fullname)) . '</a>';
echo         '<span>/</span>';
echo         '<span>' . s($currentlesson['name']) . '</span>';
echo     '</nav>';

echo     '<div class="isg-lesson-layout">';
echo         '<main class="isg-lesson-main">';
echo             '<a class="isg-back-link" href="' . $backurl->out() . '">&larr; ' . get_string('backtomodule', 'local_isgcorp') . '</a>';

echo             '<section class="isg-lesson-hero">';
echo                 '<div class="isg-lesson-hero-icon">&#9654;</div>';
echo                 '<div class="isg-lesson-hero-copy">';
echo                     '<h1 class="isg-lesson-title">' . s($currentlesson['name']) . '</h1>';
echo                     '<div class="isg-lesson-meta">';
echo                         '<span>' . s($currentlesson['contentlabel']) . '</span>';
echo                         '<span class="isg-status-pill ' . $statusclass . '">' . s($statuslabel) . '</span>';
echo                     '</div>';
if ($currentlesson['summaryplain'] !== '') {
    echo                 '<p class="isg-lesson-summary">' . s($currentlesson['summaryplain']) . '</p>';
}
echo                 '</div>';
echo             '</section>';

echo             '<section class="isg-lesson-content-card">';
if (empty($contentitems)) {
    echo html_writer::div(get_string('nolessoncontent', 'local_isgcorp'), 'isg-empty');
} else {
    foreach ($contentitems as $item) {
        $showtitle = trim((string) $item['title']) !== '' && trim((string) $item['title']) !== trim((string) $currentlesson['name']);

        echo '<article class="isg-lesson-block">';
        echo     '<div class="isg-lesson-block-head">';
        echo         '<span class="isg-lesson-block-type">' . s($item['contentlabel']) . '</span>';
        if ($showtitle) {
            echo     '<h2 class="isg-lesson-block-title">' . s($item['title']) . '</h2>';
        }
        echo     '</div>';
        if (!empty($item['bodyhtml'])) {
            echo     '<div class="isg-lesson-block-body">' . $item['bodyhtml'] . '</div>';
        }
        if (!empty($item['actionurl'])) {
            echo     '<div class="isg-lesson-block-actions">';
            echo         '<a class="isg-module-inline-btn" href="' . s($item['actionurl']) . '" target="_blank" rel="noopener">' . s($item['actionlabel']) . ' &rarr;</a>';
            echo     '</div>';
        }
        echo '</article>';
    }
}
echo             '</section>';

echo             '<div class="isg-lesson-bottom-actions">';
if ($previouslesson) {
    echo         '<a class="isg-module-secondary-btn" href="' . $previouslesson['url']->out() . '">&larr; ' . get_string('previouscontent', 'local_isgcorp') . '</a>';
} else {
    echo         '<span></span>';
}
if ($showtoggle) {
    echo         '<form method="post" action="' . $pageurl->out() . '">';
    echo             '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo             '<input type="hidden" name="courseid" value="' . (int) $courseid . '">';
    echo             '<input type="hidden" name="cmid" value="' . (int) $cmid . '">';
    echo             '<input type="hidden" name="trilhaid" value="' . (int) $trilhaid . '">';
    echo             '<input type="hidden" name="action" value="togglecomplete">';
    echo             '<button type="submit" class="isg-module-primary-btn">' . s($togglelabel) . '</button>';
    echo         '</form>';
} else {
    echo         '<span></span>';
}
if ($nextlesson) {
    echo         '<a class="isg-module-secondary-btn" href="' . $nextlesson['url']->out() . '">' . get_string('nextcontent', 'local_isgcorp') . ' &rarr;</a>';
} else {
    echo         '<span></span>';
}
echo             '</div>';
echo         '</main>';

echo         '<aside class="isg-lesson-sidebar">';
echo             '<section class="isg-module-progress-card">';
echo                 '<span class="isg-module-card-kicker">' . get_string('moduleprogress', 'local_isgcorp') . '</span>';
echo                 '<strong class="isg-module-progress-value">' . $progresspercent . '%</strong>';
echo                 '<div class="isg-course-progress-track">';
echo                     '<div class="isg-course-progress-fill isg-bar-red" style="width:' . $progresspercent . '%"></div>';
echo                 '</div>';
echo                 '<div class="isg-module-progress-meta">';
echo                     '<span>' . get_string('moduleprogresscount', 'local_isgcorp', (object) ['completed' => $completedlessons, 'total' => $totallessons]) . '</span>';
echo                 '</div>';
echo             '</section>';

echo             '<section class="isg-lesson-sidebar-card">';
echo                 '<h2 class="isg-lesson-sidebar-title">' . get_string('modulecontenttitle', 'local_isgcorp') . '</h2>';
echo                 '<div class="isg-lesson-sidebar-list">';
foreach ($lessons as $lesson) {
    $itemclass = ((int) $lesson['cmid'] === $cmid) ? 'is-current' : '';

    if (!empty($lesson['completed'])) {
        $itemstatusclass = 'is-complete';
        $itemstatuslabel = get_string('lessoncomplete', 'local_isgcorp');
    } else if (!empty($lesson['viewed'])) {
        $itemstatusclass = 'is-progress';
        $itemstatuslabel = get_string('lessoninprogress', 'local_isgcorp');
    } else if (!empty($lesson['tracked'])) {
        $itemstatusclass = 'is-neutral';
        $itemstatuslabel = get_string('lessonnotstarted', 'local_isgcorp');
    } else {
        $itemstatusclass = 'is-neutral';
        $itemstatuslabel = get_string('statusunavailable', 'local_isgcorp');
    }

    echo         '<a class="isg-lesson-sidebar-item ' . $itemclass . '" href="' . $lesson['url']->out() . '">';
    echo             '<span class="isg-lesson-sidebar-step">' . s($lesson['position']) . '</span>';
    echo             '<span class="isg-lesson-sidebar-copy">';
    echo                 '<strong>' . s($lesson['name']) . '</strong>';
    echo                 '<small>' . s($lesson['contentlabel']) . '</small>';
    echo             '</span>';
    echo             '<span class="isg-status-pill ' . $itemstatusclass . '">' . s($itemstatuslabel) . '</span>';
    echo         '</a>';
}
echo                 '</div>';
echo             '</section>';

echo             '<section class="isg-lesson-sidebar-card is-help-card">';
echo                 '<h2 class="isg-lesson-sidebar-title">' . get_string('needhelp', 'local_isgcorp') . '</h2>';
echo                 '<p>' . get_string('modulehelptext', 'local_isgcorp') . '</p>';
echo                 '<a class="isg-module-secondary-btn" href="' . $backurl->out() . '">' . get_string('backtomodule', 'local_isgcorp') . '</a>';
echo             '</section>';
echo         '</aside>';
echo     '</div>';
echo '</div>';

echo $OUTPUT->footer();

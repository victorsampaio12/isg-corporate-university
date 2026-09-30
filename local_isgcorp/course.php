<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Pagina customizada do curso dentro da experiencia da trilha.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$trilhaid = optional_param('trilhaid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$pageurl = new moodle_url('/local/isgcorp/course.php', ['courseid' => $courseid, 'trilhaid' => $trilhaid]);
$PAGE->set_url($pageurl);
require_login($course);

$trilha = $trilhaid ? $DB->get_record('local_isgcorp_trilha', ['id' => $trilhaid]) : null;
if (!$trilha) {
    $trilha = local_isgcorp_get_trilha_by_courseid((int) $course->id);
    $trilhaid = $trilha ? (int) $trilha->id : 0;
}

$context = context_course::instance($course->id);
$PAGE->set_url(new moodle_url('/local/isgcorp/course.php', ['courseid' => $courseid, 'trilhaid' => $trilhaid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($course->fullname));

global $USER;

$lessons = local_isgcorp_get_course_lessons($course, (int) $USER->id, $trilhaid ?: null);
$progress = local_isgcorp_get_manual_course_progress_summary($course, (int) $USER->id, $trilhaid ?: null);
$courseprogress = local_isgcorp_get_course_progress_data($course, (int) $USER->id);
$summaryhtml = '';

if (!empty($course->summary)) {
    $summaryhtml = format_text($course->summary, $course->summaryformat, ['context' => $context]);
}

if ($summaryhtml === '' && !empty($lessons)) {
    $summaryhtml = html_writer::tag('p', s($lessons[0]['summaryplain']));
}

$backurl = $trilha
    ? new moodle_url('/local/isgcorp/view.php', ['id' => $trilha->id, 'tab' => 'content'])
    : new moodle_url('/local/isgcorp/index.php');
$progresspercent = $progress['percent'] ?? ($courseprogress['percent'] ?? 0);
$completedlessons = $progress['completed'] ?? 0;
$totallessons = $progress['total'] ?? count($lessons);
$coursecompleted = $progresspercent >= 100;
$primaryurl = !empty($lessons) ? $lessons[0]['url'] : new moodle_url('/course/view.php', ['id' => $course->id]);

if (!$coursecompleted) {
    foreach ($lessons as $lesson) {
        if (empty($lesson['completed'])) {
            $primaryurl = $lesson['url'];
            break;
        }
    }
}

if ($coursecompleted) {
    $primarylabel = get_string('coursecompletedcta', 'local_isgcorp');
} else if ($progresspercent > 0) {
    $primarylabel = get_string('continuecourse', 'local_isgcorp');
} else {
    $primarylabel = get_string('startmodule', 'local_isgcorp');
}

echo $OUTPUT->header();

echo '<div class="isg-learner-page isg-course-page">';
echo     '<section class="isg-module-hero">';
echo         '<div class="isg-module-hero-main">';
echo             '<div class="isg-module-hero-icon">&#128640;</div>';
echo             '<div class="isg-module-hero-copy">';
echo                 '<h1 class="isg-module-title">' . s(format_string($course->fullname)) . '</h1>';
if ($summaryhtml !== '') {
    echo             '<div class="isg-module-summary">' . $summaryhtml . '</div>';
}
echo                 '<div class="isg-module-meta">';
echo                     '<span class="isg-module-pill">&#128196; ' . ($totallessons ?: 0) . ' ' . get_string('courselessonscount', 'local_isgcorp') . '</span>';
echo                     '<span class="isg-module-pill">&#128202; ' . ($progresspercent ?: 0) . '%</span>';
echo                 '</div>';
echo                 '<div class="isg-module-actions">';
$primaryicon = $coursecompleted ? ' &#10003;' : ' &rarr;';
echo                     '<a class="isg-module-primary-btn' . ($coursecompleted ? ' is-complete' : '') . '" href="' . $primaryurl->out() . '">' . s($primarylabel) . $primaryicon . '</a>';
echo                     '<a class="isg-module-secondary-btn" href="' . $backurl->out() . '">' . s(get_string('backtotrail', 'local_isgcorp')) . '</a>';
echo                 '</div>';
echo             '</div>';
echo         '</div>';

echo         '<aside class="isg-module-progress-card">';
echo             '<span class="isg-module-card-kicker">' . get_string('moduleprogress', 'local_isgcorp') . '</span>';
echo             '<strong class="isg-module-progress-value">' . ($progresspercent ?: 0) . '%</strong>';
echo             '<div class="isg-course-progress-track">';
echo                 '<div class="isg-course-progress-fill isg-bar-red" style="width:' . ($progresspercent ?: 0) . '%"></div>';
echo             '</div>';
echo             '<div class="isg-module-progress-meta">';
echo                 '<span>' . get_string('moduleprogresscount', 'local_isgcorp', (object) ['completed' => $completedlessons, 'total' => $totallessons]) . '</span>';
echo             '</div>';
echo         '</aside>';
echo     '</section>';

echo     '<section class="isg-module-panel">';
echo         '<div class="isg-module-panel-head">';
echo             '<div>';
echo                 '<h2 class="isg-module-panel-title">' . get_string('coursecontenttitle', 'local_isgcorp') . '</h2>';
echo                 '<p class="isg-module-panel-subtitle">' . get_string('coursecontentsubtitle', 'local_isgcorp') . '</p>';
echo             '</div>';
echo         '</div>';

if (empty($lessons)) {
    echo html_writer::div(get_string('nolessonsavailable', 'local_isgcorp'), 'isg-empty');
} else {
    echo     '<div class="isg-module-lesson-list">';

    foreach ($lessons as $index => $lesson) {
        if (!empty($lesson['completed'])) {
            $statusclass = 'is-complete';
            $statuslabel = get_string('lessoncomplete', 'local_isgcorp');
        } else if (!empty($lesson['viewed'])) {
            $statusclass = 'is-progress';
            $statuslabel = get_string('lessoninprogress', 'local_isgcorp');
        } else if (!empty($lesson['tracked'])) {
            $statusclass = 'is-neutral';
            $statuslabel = get_string('lessonnotstarted', 'local_isgcorp');
        } else {
            $statusclass = 'is-neutral';
            $statuslabel = get_string('statusunavailable', 'local_isgcorp');
        }

        echo '<article class="isg-module-lesson-card">';
        echo     '<div class="isg-module-lesson-index">' . ($index + 1) . '</div>';
        echo     '<div class="isg-module-lesson-media">';
        echo         '<span class="isg-module-lesson-type">' . s($lesson['contentlabel']) . '</span>';
        echo     '</div>';
        echo     '<div class="isg-module-lesson-copy">';
        echo         '<h3 class="isg-module-lesson-title">' . s($lesson['name']) . '</h3>';
        echo         '<div class="isg-module-lesson-meta">';
        echo             '<span>' . s($lesson['contentlabel']) . '</span>';
        echo             '<span>' . get_string('modulecontentcount', 'local_isgcorp', (int) $lesson['contentcount']) . '</span>';
        echo         '</div>';
        if ($lesson['summaryplain'] !== '') {
            echo     '<p class="isg-module-lesson-summary">' . s($lesson['summaryplain']) . '</p>';
        }
        echo     '</div>';
        echo     '<div class="isg-module-lesson-actions">';
        echo         '<span class="isg-status-pill ' . $statusclass . '">' . s($statuslabel) . '</span>';
        echo         '<a class="isg-module-inline-btn" href="' . $lesson['url']->out() . '">' . s($lesson['actionlabel']) . ' &rarr;</a>';
        echo     '</div>';
        echo '</article>';
    }

    echo     '</div>';
}

echo         '<div class="isg-module-info-box">';
echo             '<strong>' . get_string('modulehelpheading', 'local_isgcorp') . '</strong>';
echo             '<p>' . get_string('modulehelptext', 'local_isgcorp') . '</p>';
echo         '</div>';
echo     '</section>';
echo '</div>';

echo $OUTPUT->footer();

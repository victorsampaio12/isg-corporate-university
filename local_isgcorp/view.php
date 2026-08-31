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

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'content', PARAM_ALPHA);
$trilha = $DB->get_record('local_isgcorp_trilha', ['id' => $id], '*', MUST_EXIST);

if (!in_array($tab, ['content', 'about'], true)) {
    $tab = 'content';
}

$PAGE->set_url(new moodle_url('/local/isgcorp/view.php', ['id' => $id, 'tab' => $tab]));
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($trilha->name));
// Mesmo motivo do index.php: evitar título duplicado.

global $USER;

$courses = array_values(local_isgcorp_get_trilha_courses($trilha->id));
$levelstring = get_string('level' . $trilha->level, 'local_isgcorp');
$trilhaprogress = local_isgcorp_get_trilha_progress((int) $trilha->id, (int) $USER->id);
$coursecount = count($courses);
$bannerurl = $OUTPUT->image_url('trilha-onboarding-banner', 'theme_isgcorp');
$traildescriptionplain = trim(preg_replace('/\s+/', ' ', strip_tags(
    format_text($trilha->description, $trilha->descriptionformat)
)));
$aboutcontent = !empty($trilha->description)
    ? format_text($trilha->description, $trilha->descriptionformat)
    : html_writer::div(get_string('trailaboutempty', 'local_isgcorp'), 'isg-empty');

$firstcourseurl = null;
$completedcourses = 0;
$courseitems = [];

foreach ($courses as $index => $course) {
    $progressdata = local_isgcorp_get_course_progress_data($course, (int) $USER->id);
    $outline = local_isgcorp_get_course_activity_outline($course, (int) $USER->id, (int) $trilha->id);
    $courseurl = local_isgcorp_get_course_page_url((int) $course->id, (int) $trilha->id);
    $summaryplain = trim(preg_replace('/\s+/', ' ', strip_tags(
        format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML)
    )));

    if ($firstcourseurl === null) {
        $firstcourseurl = $courseurl;
    }

    if (($progressdata['percent'] ?? 0) >= 100) {
        $completedcourses++;
    }

    $courseitems[] = [
        'index' => $index + 1,
        'course' => $course,
        'courseurl' => $courseurl,
        'summaryplain' => $summaryplain,
        'progressdata' => $progressdata,
        'outline' => $outline,
    ];
}

$ctalabel = ($trilhaprogress !== null && $trilhaprogress > 0)
    ? get_string('continuelearning', 'local_isgcorp')
    : get_string('startlearning', 'local_isgcorp');
$heroactivitytext = $coursecount > 0
    ? get_string('trailcoursecompletion', 'local_isgcorp', (object) ['completed' => $completedcourses, 'total' => $coursecount])
    : get_string('trilhanocourses', 'local_isgcorp');
$heroprogresslabel = $trilhaprogress !== null
    ? $trilhaprogress . '% ' . get_string('concluded', 'local_isgcorp')
    : get_string('notrackinglabel', 'local_isgcorp');
$contenttaburl = new moodle_url('/local/isgcorp/view.php', ['id' => $id, 'tab' => 'content']);
$abouttaburl = new moodle_url('/local/isgcorp/view.php', ['id' => $id, 'tab' => 'about']);

echo $OUTPUT->header();

echo '<div class="isg-trilhas-page">';
echo '<nav class="isg-trilha-breadcrumbs">';
echo     '<a class="isg-link-more" href="' . (new moodle_url('/local/isgcorp/index.php'))->out() . '">&larr; ' . get_string('trilhas', 'local_isgcorp') . '</a>';
echo     '<span>/</span>';
echo     '<span>' . format_string($trilha->name) . '</span>';
echo '</nav>';

echo '<section class="isg-trilha-hero">';
echo     '<div class="isg-trilha-hero-copy">';
echo         '<div class="isg-trilha-hero-head">';
echo             '<div class="isg-trilha-hero-icon">&#128640;</div>';
echo             '<div class="isg-trilha-hero-heading">';
echo                 '<span class="isg-trilha-kicker">' . get_string('learningpathlabel', 'local_isgcorp') . '</span>';
echo                 '<h1 class="isg-trilhas-title isg-trilha-hero-title">' . format_string($trilha->name) . '</h1>';
if ($traildescriptionplain !== '') {
    echo             '<p class="isg-trilha-hero-subtitle">' . s($traildescriptionplain) . '</p>';
}
echo             '</div>';
echo         '</div>';

echo         '<div class="isg-trilha-hero-meta">';
echo             '<span class="isg-trilha-meta-pill">&#128214; ' . $coursecount . ' ' . get_string('coursescount', 'local_isgcorp') . '</span>';
echo             '<span class="isg-trilha-meta-pill">&#128202; ' . s($levelstring) . '</span>';
if (!empty($trilha->estimatedhours)) {
    echo         '<span class="isg-trilha-meta-pill">&#9201; ' . (int) $trilha->estimatedhours . 'h</span>';
}
echo         '</div>';

echo         '<div class="isg-trilha-hero-actions">';
if ($firstcourseurl !== null) {
    echo         '<a class="isg-trilha-cta" href="' . $firstcourseurl->out() . '">' . s($ctalabel) . ' &rarr;</a>';
}
echo         '</div>';

echo         '<div class="isg-trilha-hero-progress">';
echo             '<div class="isg-trilha-hero-progress-head">';
echo                 '<span class="isg-trilha-progress-summary">' . s($heroactivitytext) . '</span>';
echo                 '<span class="isg-trilha-progress-summary is-highlight">' . s($heroprogresslabel) . '</span>';
echo             '</div>';
echo             '<div class="isg-course-progress-track">';
echo                 '<div class="isg-course-progress-fill ' . ($trilhaprogress !== null ? 'isg-bar-red' : 'isg-bar-gray') . '" style="width:' . ($trilhaprogress ?? 0) . '%"></div>';
echo             '</div>';
echo         '</div>';
echo     '</div>';

echo     '<div class="isg-trilha-hero-artwrap">';
echo     '<div class="isg-trilha-hero-art" style="background-image:url(' . s($bannerurl->out(false)) . ')"></div>';
echo     '</div>';
echo '</section>';

echo '<nav class="isg-trilha-section-tabs">';
echo     '<a class="isg-trilha-section-tab ' . ($tab === 'content' ? 'active' : '') . '" href="' . $contenttaburl->out() . '">' . get_string('trailcontenttab', 'local_isgcorp') . '</a>';
echo     '<a class="isg-trilha-section-tab ' . ($tab === 'about' ? 'active' : '') . '" href="' . $abouttaburl->out() . '">' . get_string('trailabouttab', 'local_isgcorp') . '</a>';
echo '</nav>';

if ($tab === 'content') {
    echo '<section class="isg-trilha-panel" id="trilha-conteudo">';
    echo     '<h2 class="isg-trilha-panel-title">' . get_string('trailcontenttab', 'local_isgcorp') . '</h2>';

    if (empty($courses)) {
        echo html_writer::div(get_string('trilhanocourses', 'local_isgcorp'), 'isg-empty');
    } else {
        echo '<div class="isg-trilha-steps">';

        foreach ($courseitems as $courseitem) {
            $stepnumber = str_pad((string) $courseitem['index'], 2, '0', STR_PAD_LEFT);
            $progressdata = $courseitem['progressdata'];
            $outline = $courseitem['outline'];
            $summary = $courseitem['summaryplain'];
            $pillclass = 'is-' . $progressdata['statusclass'];
            $summarylabel = $outline['trackedcount'] > 0
                ? get_string('trilhastepcompletion', 'local_isgcorp', (object) [
                    'completed' => $outline['completedcount'],
                    'total' => $outline['trackedcount'],
                ])
                : get_string('notrackinglabel', 'local_isgcorp');

            echo '<details class="isg-trilha-step"' . ($courseitem['index'] === 1 ? ' open' : '') . '>';
            echo     '<summary class="isg-trilha-step-summary">';
            echo         '<span class="isg-trilha-step-marker ' . $pillclass . '"></span>';
            echo         '<span class="isg-trilha-step-copy">';
            echo             '<span class="isg-trilha-step-title">' . $stepnumber . '. ' . format_string($courseitem['course']->fullname) . '</span>';
            if ($summary !== '') {
                echo         '<span class="isg-trilha-step-desc">' . s($summary) . '</span>';
            }
            echo         '</span>';
            echo         '<span class="isg-trilha-step-meta">';
            echo             '<span class="isg-trilha-step-progress">' . s($summarylabel) . '</span>';
            echo             '<span class="isg-status-pill ' . $pillclass . '">' . s($progressdata['statuslabel']) . '</span>';
            echo         '</span>';
            echo     '</summary>';
            echo     '<div class="isg-trilha-step-body">';

            if (!empty($outline['items'])) {
                echo '<div class="isg-trilha-activity-list">';

                foreach ($outline['items'] as $activityindex => $activity) {
                    $activitystateclass = !$activity['tracked']
                        ? 'is-neutral'
                        : ($activity['completed'] ? 'is-complete' : (!empty($activity['viewed']) ? 'is-progress' : 'is-neutral'));
                    $activitystatelabel = !$activity['tracked']
                        ? get_string('notrackinglabel', 'local_isgcorp')
                        : ($activity['completed']
                            ? get_string('statuscomplete', 'local_isgcorp')
                            : (!empty($activity['viewed'])
                                ? get_string('statusinprogress', 'local_isgcorp')
                                : get_string('statusnotstarted', 'local_isgcorp')));

                    echo '<a class="isg-trilha-activity" href="' . $activity['url']->out() . '">';
                    echo     '<span class="isg-trilha-activity-index">' . $stepnumber . '.' . ($activityindex + 1) . '</span>';
                    echo     '<span class="isg-trilha-activity-copy">';
                    echo         '<span class="isg-trilha-activity-title">' . s($activity['name']) . '</span>';
                    echo         '<span class="isg-trilha-activity-type">' . s($activity['modlabel']) . '</span>';
                    echo     '</span>';
                    echo     '<span class="isg-status-pill ' . $activitystateclass . '">' . s($activitystatelabel) . '</span>';
                    echo '</a>';
                }

                echo '</div>';
            } else {
                echo '<a class="isg-trilha-activity isg-trilha-activity-direct" href="' . $courseitem['courseurl']->out() . '">';
                echo     '<span class="isg-trilha-activity-index">' . $stepnumber . '</span>';
                echo     '<span class="isg-trilha-activity-copy">';
                echo         '<span class="isg-trilha-activity-title">' . format_string($courseitem['course']->fullname) . '</span>';
                echo         '<span class="isg-trilha-activity-type">' . get_string('opencourse', 'local_isgcorp') . '</span>';
                echo     '</span>';
                echo '</a>';
            }

            echo         '<div class="isg-trilha-step-footer">';
            echo             '<a class="isg-link-more" href="' . $courseitem['courseurl']->out() . '">' . get_string('opencourse', 'local_isgcorp') . ' &rarr;</a>';
            echo         '</div>';
            echo     '</div>';
            echo '</details>';
        }
        echo '</div>';
    }
    echo '</section>';
} else {
    echo '<section class="isg-trilha-panel isg-trilha-panel-about" id="trilha-sobre">';
    echo     '<h2 class="isg-trilha-panel-title">' . get_string('trailabouttab', 'local_isgcorp') . '</h2>';
    echo     '<div class="isg-trilha-fulldesc">' . $aboutcontent . '</div>';
    echo '</section>';
}

echo '</div>';

echo $OUTPUT->footer();

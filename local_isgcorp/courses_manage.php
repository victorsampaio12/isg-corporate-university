<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_courses_manage');
$PAGE->navbar->ignore_active();

$query = optional_param('q', '', PARAM_TEXT);
$params = ['siteid' => SITEID];
$where = 'c.id <> :siteid';
if ($query !== '') {
    $where .= ' AND ' . $DB->sql_like('c.fullname', ':query', false);
    $params['query'] = '%' . $DB->sql_like_escape($query) . '%';
}

$sql = "SELECT c.*, cc.name AS categoryname
          FROM {course} c
          JOIN {course_categories} cc ON cc.id = c.category
         WHERE {$where}
      ORDER BY c.fullname ASC";
$courses = $DB->get_records_sql($sql, $params);

echo $OUTPUT->header();
echo '<div class="isg-courses-admin">';
echo html_writer::link(
    new moodle_url('/local/isgcorp/training.php'),
    '&larr; ' . get_string('backtotrainingdashboard', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary mb-3']
);
echo '<div class="isg-admin-page-head"><div><h2>' . get_string('coursesmanagementtitle', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('coursesmanagementsubtitle', 'local_isgcorp') . '</p></div>';
echo '<div class="isg-admin-page-actions">';
echo html_writer::link(
    new moodle_url('/course/edit.php', ['category' => 0]),
    get_string('addnewcourseadmin', 'local_isgcorp'),
    ['class' => 'btn btn-primary']
);
echo html_writer::link(
    new moodle_url('/course/management.php'),
    get_string('managecategoriessecondary', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary']
);
echo '</div></div>';

echo '<form class="isg-course-admin-search" method="get">';
echo '<input class="form-control" type="search" name="q" value="' . s($query) . '" placeholder="';
echo s(get_string('searchcoursesplaceholder', 'local_isgcorp')) . '">';
echo '<button class="btn btn-primary" type="submit">' . get_string('search') . '</button>';
echo '</form>';

if (!$courses) {
    echo html_writer::div(get_string('nocoursesfound', 'local_isgcorp'), 'isg-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable isg-assessment-admin-table';
    $table->head = [
        get_string('course'),
        get_string('assessmenttrail', 'local_isgcorp'),
        get_string('coursecategorylabel', 'local_isgcorp'),
        get_string('courselessons', 'local_isgcorp'),
        get_string('courseassessment', 'local_isgcorp'),
        get_string('status'),
        get_string('actions'),
    ];

    foreach ($courses as $course) {
        $trail = local_isgcorp_get_trilha_by_courseid((int)$course->id);
        $assessment = local_isgcorp_get_course_assessment((int)$course->id);
        $lessons = local_isgcorp_get_course_lessons($course, (int)$USER->id);

        if ($assessment) {
            $questioncount = $DB->count_records('quiz_slots', ['quizid' => $assessment->id]);
            $assessmentlabel = $questioncount > 0
                ? get_string('assessmentready', 'local_isgcorp')
                : get_string('assessmentnoquestions', 'local_isgcorp');
            $assessmenturl = new moodle_url('/mod/quiz/edit.php', ['cmid' => $assessment->cmid]);
        } else {
            $assessmentlabel = get_string('assessmentnone', 'local_isgcorp');
            $assessmenturl = new moodle_url('/local/isgcorp/assessment_create.php', ['courseid' => $course->id]);
        }

        $editlessonsurl = new moodle_url('/course/view.php', [
            'id' => $course->id,
            'edit' => 'on',
            'sesskey' => sesskey(),
        ]);
        $settingsurl = new moodle_url('/course/edit.php', ['id' => $course->id]);
        $viewurl = new moodle_url('/local/isgcorp/course.php', ['courseid' => $course->id]);

        $actions = [
            html_writer::link($editlessonsurl, get_string('editcourselessons', 'local_isgcorp')),
            html_writer::link($settingsurl, get_string('editcoursesettings', 'local_isgcorp')),
            html_writer::link($assessmenturl, get_string('courseassessment', 'local_isgcorp')),
            html_writer::link($viewurl, get_string('viewcourseadmin', 'local_isgcorp')),
            local_isgcorp_delete_action(
                '/local/isgcorp/course_delete.php',
                ['courseid' => $course->id],
                get_string('confirmdeletecourse', 'local_isgcorp', format_string($course->fullname)),
                'delete-course-' . $course->id
            ),
        ];
        $status = $course->visible
            ? html_writer::span(get_string('statusactive', 'local_isgcorp'), 'badge badge-success')
            : html_writer::span(get_string('statusdraft', 'local_isgcorp'), 'badge badge-secondary');

        $table->data[] = [
            format_string($course->fullname),
            $trail ? format_string($trail->name) : '&mdash;',
            format_string($course->categoryname),
            count($lessons),
            s($assessmentlabel),
            $status,
            html_writer::div(implode(' <span aria-hidden="true">|</span> ', $actions), 'isg-admin-row-actions'),
        ];
    }
    echo html_writer::table($table);
}
echo '</div>';
echo $OUTPUT->footer();

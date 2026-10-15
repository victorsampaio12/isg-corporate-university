<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_dashboard');

$trailid = optional_param('trilhaid', 0, PARAM_INT);
$datefrom = optional_param('datefrom', '', PARAM_RAW_TRIMMED);
$dateto = optional_param('dateto', '', PARAM_RAW_TRIMMED);
$trails = $DB->get_records('local_isgcorp_trilha', null, 'name ASC', 'id, name');

$fromtimestamp = $datefrom !== '' ? strtotime($datefrom . ' 00:00:00') : 0;
$totimestamp = $dateto !== '' ? strtotime($dateto . ' 23:59:59') : 0;
$courseids = $trailid
    ? $DB->get_fieldset_select('local_isgcorp_trilha_course', 'courseid', 'trilhaid = ?', [$trailid])
    : $DB->get_fieldset_select('course', 'id', 'id <> ?', [SITEID]);

$students = 0;
$lessons = 0;
$certificates = 0;
if ($courseids) {
    [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
    $datewhere = '';
    $studentparams = $inparams;
    if ($fromtimestamp) {
        $datewhere .= ' AND ue.timecreated >= :studentfrom';
        $studentparams['studentfrom'] = $fromtimestamp;
    }
    if ($totimestamp) {
        $datewhere .= ' AND ue.timecreated <= :studentto';
        $studentparams['studentto'] = $totimestamp;
    }
    $students = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {user} u ON u.id = ue.userid
           JOIN {context} ctx ON ctx.contextlevel = :coursecontext AND ctx.instanceid = e.courseid
           JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
           JOIN {role} r ON r.id = ra.roleid AND r.archetype = :studentrole
          WHERE e.courseid {$insql} AND u.deleted = 0 AND u.suspended = 0 {$datewhere}",
        $studentparams + ['coursecontext' => CONTEXT_COURSE, 'studentrole' => 'student']
    );

    $lessons = (int)$DB->count_records_sql(
        "SELECT COUNT(cm.id)
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course {$insql} AND cm.deletioninprogress = 0 AND m.name <> :quiz",
        $inparams + ['quiz' => 'quiz']
    );

    $certificatewhere = "courseid {$insql}";
    $certificateparams = $inparams;
    if ($fromtimestamp) {
        $certificatewhere .= ' AND timecreated >= :certificatefrom';
        $certificateparams['certificatefrom'] = $fromtimestamp;
    }
    if ($totimestamp) {
        $certificatewhere .= ' AND timecreated <= :certificateto';
        $certificateparams['certificateto'] = $totimestamp;
    }
    $certificates = $DB->count_records_select('local_isgcorp_certificate', $certificatewhere, $certificateparams);
}

$trailcount = $trailid ? 1 : $DB->count_records('local_isgcorp_trilha');
$coursecount = count($courseids);
$chartdata = [];
$maxstudents = 1;
$maxcertificates = 1;
foreach ($trails as $trail) {
    if ($trailid && (int)$trail->id !== $trailid) {
        continue;
    }
    $trailstudentwhere = '';
    $trailstudentparams = ['trilhaid' => $trail->id];
    if ($fromtimestamp) {
        $trailstudentwhere .= ' AND ue.timecreated >= :chartstudentfrom';
        $trailstudentparams['chartstudentfrom'] = $fromtimestamp;
    }
    if ($totimestamp) {
        $trailstudentwhere .= ' AND ue.timecreated <= :chartstudentto';
        $trailstudentparams['chartstudentto'] = $totimestamp;
    }
    $trailstudents = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {local_isgcorp_trilha_course} tc
           JOIN {enrol} e ON e.courseid = tc.courseid
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
           JOIN {user} u ON u.id = ue.userid
           JOIN {context} ctx ON ctx.contextlevel = :chartcoursecontext AND ctx.instanceid = e.courseid
           JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
           JOIN {role} r ON r.id = ra.roleid AND r.archetype = :chartstudentrole
          WHERE tc.trilhaid = :trilhaid AND u.deleted = 0 AND u.suspended = 0 {$trailstudentwhere}",
        $trailstudentparams + ['chartcoursecontext' => CONTEXT_COURSE, 'chartstudentrole' => 'student']
    );
    $trailcertificatewhere = '';
    $trailcertificateparams = ['trilhaid' => $trail->id];
    if ($fromtimestamp) {
        $trailcertificatewhere .= ' AND cert.timecreated >= :chartcertificatefrom';
        $trailcertificateparams['chartcertificatefrom'] = $fromtimestamp;
    }
    if ($totimestamp) {
        $trailcertificatewhere .= ' AND cert.timecreated <= :chartcertificateto';
        $trailcertificateparams['chartcertificateto'] = $totimestamp;
    }
    $trailcertificates = (int)$DB->count_records_sql(
        "SELECT COUNT(cert.id)
           FROM {local_isgcorp_certificate} cert
           JOIN {local_isgcorp_trilha_course} tc ON tc.courseid = cert.courseid
          WHERE tc.trilhaid = :trilhaid {$trailcertificatewhere}",
        $trailcertificateparams
    );
    $maxstudents = max($maxstudents, $trailstudents);
    $maxcertificates = max($maxcertificates, $trailcertificates);
    $chartdata[] = [$trail, $trailstudents, $trailcertificates];
}

echo $OUTPUT->header();
echo '<div class="isg-dashboard">';
echo '<div class="isg-admin-page-head"><div><h2>' . get_string('dashboardtitle', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('dashboardsubtitle', 'local_isgcorp') . '</p></div>';
echo html_writer::link(
    new moodle_url('/local/isgcorp/certificate_audit.php'),
    get_string('opencertificateaudit', 'local_isgcorp'),
    ['class' => 'btn btn-outline-primary']
);
echo '</div>';
echo '<form method="get" class="isg-dashboard-filters">';
echo '<label>' . get_string('dashboardperiodfrom', 'local_isgcorp') . '<input class="form-control" type="date" name="datefrom" value="' . s($datefrom) . '"></label>';
echo '<label>' . get_string('dashboardperiodto', 'local_isgcorp') . '<input class="form-control" type="date" name="dateto" value="' . s($dateto) . '"></label>';
$trailoptions = [0 => get_string('dashboardalltrails', 'local_isgcorp')];
foreach ($trails as $trail) {
    $trailoptions[$trail->id] = format_string($trail->name);
}
echo '<label>' . get_string('assessmenttrail', 'local_isgcorp') . html_writer::select($trailoptions, 'trilhaid', $trailid, false, ['class' => 'form-control']) . '</label>';
echo '<button class="btn btn-primary" type="submit">' . get_string('dashboardfilter', 'local_isgcorp') . '</button>';
echo html_writer::link(new moodle_url('/local/isgcorp/dashboard.php'), get_string('dashboardclear', 'local_isgcorp'), ['class' => 'btn btn-outline-secondary']);
echo '</form>';

$metrics = [
    [get_string('dashboardstudents', 'local_isgcorp'), $students, '&#128101;'],
    [get_string('dashboardtrails', 'local_isgcorp'), $trailcount, '&#127891;'],
    [get_string('dashboardcourses', 'local_isgcorp'), $coursecount, '&#128218;'],
    [get_string('dashboardlessons', 'local_isgcorp'), $lessons, '&#9654;'],
    [get_string('dashboardcertificates', 'local_isgcorp'), $certificates, '&#127942;'],
];
echo '<div class="isg-dashboard-metrics">';
foreach ($metrics as [$label, $value, $icon]) {
    echo '<section class="isg-dashboard-metric"><span>' . $icon . '</span><strong>' . $value . '</strong><small>' . $label . '</small></section>';
}
echo '</div>';

echo '<div class="isg-dashboard-charts">';
foreach ([
    [get_string('dashboardstudentsbytrail', 'local_isgcorp'), 1, $maxstudents],
    [get_string('dashboardcertificatesbytrail', 'local_isgcorp'), 2, $maxcertificates],
] as [$title, $index, $maximum]) {
    echo '<section class="isg-dashboard-chart"><h3>' . $title . '</h3>';
    if (!$chartdata) {
        echo '<p>' . get_string('dashboardnodata', 'local_isgcorp') . '</p>';
    }
    foreach ($chartdata as $row) {
        $value = $row[$index];
        $width = $value > 0 ? max(4, round(($value / $maximum) * 100)) : 0;
        echo '<div class="isg-dashboard-bar-row"><div><span>' . format_string($row[0]->name) . '</span><strong>' . $value . '</strong></div>';
        echo '<div class="isg-dashboard-bar"><i style="width:' . $width . '%"></i></div></div>';
    }
    echo '</section>';
}
echo '</div></div>';
echo $OUTPUT->footer();

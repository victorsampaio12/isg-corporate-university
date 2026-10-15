<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_isgcorp_certificate_audit');
require_capability('local/isgcorp:manage', context_system::instance());

$search = optional_param('search', '', PARAM_TEXT);
$datefrom = optional_param('datefrom', '', PARAM_RAW_TRIMMED);
$dateto = optional_param('dateto', '', PARAM_RAW_TRIMMED);
$export = optional_param('export', 0, PARAM_BOOL);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;

$normalisedate = static function(string $date): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
};
$datefrom = $normalisedate($datefrom);
$dateto = $normalisedate($dateto);

$where = ['log.isinitial = 0'];
$params = [];
if ($search !== '') {
    $term = '%' . $DB->sql_like_escape($search) . '%';
    $searchfields = [
        'u.firstname',
        'u.lastname',
        $DB->sql_concat('u.firstname', "' '", 'u.lastname'),
        'u.username',
        'u.email',
        'log.oldvalue',
        'log.newvalue',
    ];
    $likes = [];
    foreach ($searchfields as $index => $field) {
        $paramname = 'search' . $index;
        $likes[] = $DB->sql_like($field, ':' . $paramname, false);
        $params[$paramname] = $term;
    }
    $where[] = '(' . implode(' OR ', $likes) . ')';
}
if ($datefrom !== '') {
    $where[] = 'log.timecreated >= :datefrom';
    $params['datefrom'] = strtotime($datefrom . ' 00:00:00');
}
if ($dateto !== '') {
    $where[] = 'log.timecreated <= :dateto';
    $params['dateto'] = strtotime($dateto . ' 23:59:59');
}

$fromsql = "FROM {local_isgcorp_certname_log} log
            JOIN {user} u ON u.id = log.userid
            JOIN {user} actor ON actor.id = log.changedby
           WHERE " . implode(' AND ', $where);
$selectsql = "SELECT log.id, log.userid, log.changedby, log.oldvalue, log.newvalue, log.timecreated,
                    u.firstname AS studentfirstname, u.lastname AS studentlastname,
                    u.username AS studentusername, u.email AS studentemail,
                    actor.firstname AS actorfirstname, actor.lastname AS actorlastname";
$ordersql = ' ORDER BY log.timecreated DESC, log.id DESC';

$total = (int) $DB->count_records_sql('SELECT COUNT(log.id) ' . $fromsql, $params);
$records = $DB->get_records_sql(
    $selectsql . ' ' . $fromsql . $ordersql,
    $params,
    $export ? 0 : $page * $perpage,
    $export ? 0 : $perpage
);

$displayname = static function(stdClass $record, string $prefix): string {
    return fullname((object) [
        'firstname' => $record->{$prefix . 'firstname'},
        'lastname' => $record->{$prefix . 'lastname'},
    ]);
};
$csvsafe = static function(string $value): string {
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
};

if ($export) {
    require_once($CFG->libdir . '/csvlib.class.php');

    $csv = new csv_export_writer();
    $csv->set_filename('auditoria-nomes-certificado-' . userdate(time(), '%Y-%m-%d'));
    $csv->add_data([
        get_string('certificateauditstudent', 'local_isgcorp'),
        get_string('certificateauditchangedby', 'local_isgcorp'),
        get_string('certificateauditoldvalue', 'local_isgcorp'),
        get_string('certificateauditnewvalue', 'local_isgcorp'),
        get_string('certificateauditdate', 'local_isgcorp'),
    ]);
    foreach ($records as $record) {
        $csv->add_data([
            $csvsafe($displayname($record, 'student')),
            $csvsafe($displayname($record, 'actor')),
            $csvsafe((string) $record->oldvalue),
            $csvsafe((string) $record->newvalue),
            userdate($record->timecreated),
        ]);
    }
    $csv->download_file();
    exit;
}

$filterparams = array_filter([
    'search' => $search,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
], static fn($value): bool => $value !== '');
$baseurl = new moodle_url('/local/isgcorp/certificate_audit.php', $filterparams);
$exporturl = new moodle_url('/local/isgcorp/certificate_audit.php', $filterparams + ['export' => 1]);

echo $OUTPUT->header();
echo '<div class="isg-dashboard isg-certificate-audit">';
echo '<div class="isg-admin-page-head"><div>';
echo '<h2>' . get_string('certificateaudittitle', 'local_isgcorp') . '</h2>';
echo '<p>' . get_string('certificateauditsubtitle', 'local_isgcorp') . '</p>';
echo '</div>';
echo html_writer::link(
    new moodle_url('/local/isgcorp/dashboard.php'),
    '&larr; ' . get_string('certificateauditback', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary']
);
echo '</div>';

echo '<form method="get" class="isg-dashboard-filters">';
echo '<label class="isg-audit-search">' . get_string('certificateauditsearch', 'local_isgcorp');
echo '<input class="form-control" type="search" name="search" value="' . s($search) . '" placeholder="' .
    s(get_string('certificateauditsearchplaceholder', 'local_isgcorp')) . '"></label>';
echo '<label>' . get_string('dashboardperiodfrom', 'local_isgcorp');
echo '<input class="form-control" type="date" name="datefrom" value="' . s($datefrom) . '"></label>';
echo '<label>' . get_string('dashboardperiodto', 'local_isgcorp');
echo '<input class="form-control" type="date" name="dateto" value="' . s($dateto) . '"></label>';
echo '<button class="btn btn-primary" type="submit">' . get_string('dashboardfilter', 'local_isgcorp') . '</button>';
echo html_writer::link(
    new moodle_url('/local/isgcorp/certificate_audit.php'),
    get_string('dashboardclear', 'local_isgcorp'),
    ['class' => 'btn btn-outline-secondary']
);
echo html_writer::link(
    $exporturl,
    get_string('certificateauditexport', 'local_isgcorp'),
    ['class' => 'btn btn-outline-primary']
);
echo '</form>';

if (!$records) {
    echo html_writer::div(
        get_string('certificateauditnoresults', 'local_isgcorp'),
        'alert alert-info'
    );
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable isg-audit-table';
    $table->head = [
        get_string('certificateauditstudent', 'local_isgcorp'),
        get_string('certificateauditchangedby', 'local_isgcorp'),
        get_string('certificateauditoldvalue', 'local_isgcorp'),
        get_string('certificateauditnewvalue', 'local_isgcorp'),
        get_string('certificateauditdate', 'local_isgcorp'),
    ];
    foreach ($records as $record) {
        $studentname = $displayname($record, 'student');
        $actorname = $displayname($record, 'actor');
        $student = html_writer::link(
            new moodle_url('/user/profile.php', ['id' => $record->userid]),
            s($studentname)
        );
        $student .= html_writer::tag('small', s($record->studentemail));
        $oldvalue = trim((string) $record->oldvalue);
        $newvalue = trim((string) $record->newvalue);
        $table->data[] = [
            html_writer::div($student, 'isg-audit-user'),
            s($actorname),
            $oldvalue === '' ? html_writer::tag('em', get_string('certificateauditnotset', 'local_isgcorp')) : s($oldvalue),
            $newvalue === '' ? html_writer::tag('em', get_string('certificateauditnotset', 'local_isgcorp')) : s($newvalue),
            userdate($record->timecreated),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive isg-audit-table-wrap');
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo '</div>';
echo $OUTPUT->footer();


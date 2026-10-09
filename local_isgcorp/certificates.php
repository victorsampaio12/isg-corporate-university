<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Lists learning path certificates issued to the current user.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = 8;
$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/isgcorp/certificates.php', ['page' => $page]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycertificates', 'local_isgcorp'));

$sql = "SELECT ci.id, ci.timecreated, ci.code, t.id AS trilhaid, t.name AS certname, t.level
          FROM {local_isgcorp_certificate} ci
          JOIN {local_isgcorp_trilha} t ON t.id = ci.trilhaid
         WHERE ci.userid = :userid
      ORDER BY ci.timecreated DESC";
$allcerts = $DB->get_records_sql($sql, ['userid' => $USER->id]);
$totalcount = count($allcerts);
$advancedcount = 0;
$lastyear = null;

foreach ($allcerts as $certificate) {
    if ($lastyear === null) {
        $lastyear = userdate($certificate->timecreated, '%Y');
    }
    if ($certificate->level === 'avancado') {
        $advancedcount++;
    }
}

echo $OUTPUT->header();
echo '<div class="isg-trilhas-page">';
echo '<div class="isg-cert-hero">';
echo '<span class="isg-cert-eyebrow">' . get_string('certeyebrow', 'local_isgcorp') . '</span>';
echo '<h1 class="isg-cert-hero-title">' . get_string('certherotitle', 'local_isgcorp') . '</h1>';
echo '<p class="isg-cert-hero-subtitle">' . get_string('certherosubtitle', 'local_isgcorp') . '</p>';
echo '</div>';

echo '<div class="isg-stat-grid mb-4">';
$stats = [
    ['&#127942;', $totalcount, get_string('certcount', 'local_isgcorp')],
    ['&#128200;', $advancedcount, get_string('certadvanced', 'local_isgcorp')],
    ['&#128197;', $lastyear ?? '&mdash;', get_string('certlast', 'local_isgcorp')],
];
foreach ($stats as [$icon, $value, $label]) {
    echo '<div class="isg-stat-card"><div class="isg-stat-icon">' . $icon . '</div><div>';
    echo '<div class="isg-stat-value">' . $value . '</div>';
    echo '<div class="isg-stat-sub">' . $label . '</div></div></div>';
}
echo '</div>';

echo '<h2 class="isg-section-title">' . get_string('mycertificates', 'local_isgcorp') . '</h2>';
if (!$allcerts) {
    echo html_writer::div(get_string('nocertificates', 'local_isgcorp'), 'isg-empty');
} else {
    $pagedcerts = array_slice(array_values($allcerts), $page * $perpage, $perpage);
    echo '<div class="isg-cert-grid">';
    foreach ($pagedcerts as $certificate) {
        $downloadurl = new moodle_url('/local/isgcorp/certificate.php', ['trilhaid' => $certificate->trilhaid]);
        $date = userdate($certificate->timecreated, get_string('certdateformat', 'local_isgcorp'));

        echo '<div class="isg-cert-card">';
        echo '<div class="isg-cert-top"><span class="isg-cert-label">';
        echo get_string('certificatelabel', 'local_isgcorp');
        echo '</span><span class="isg-cert-badge">&#127942;</span></div>';
        echo '<div class="isg-cert-title">' . format_string($certificate->certname) . '</div>';
        echo '<div class="isg-cert-date">' . get_string('completedon', 'local_isgcorp', $date) . '</div>';
        $downloadlabel = get_string('download', 'local_isgcorp');
        echo '<a class="isg-cert-download" href="' . $downloadurl->out() . '" title="' . $downloadlabel;
        echo '" aria-label="' . $downloadlabel . '">';
        echo $OUTPUT->pix_icon('t/download', $downloadlabel) . '</a>';
        echo '</div>';
    }
    echo '</div>';

    $totalpages = (int)ceil($totalcount / $perpage);
    if ($totalpages > 1) {
        echo '<div class="isg-pagination">';
        for ($pagenumber = 0; $pagenumber < $totalpages; $pagenumber++) {
            $url = new moodle_url('/local/isgcorp/certificates.php', ['page' => $pagenumber]);
            $active = ($pagenumber === $page) ? 'active' : '';
            echo '<a class="isg-page-link ' . $active . '" href="' . $url->out() . '">';
            echo ($pagenumber + 1) . '</a>';
        }
        echo '</div>';
    }
}

echo '</div>';
echo $OUTPUT->footer();

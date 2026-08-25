<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * "Meus certificados" — lista os certificados emitidos pro usuário
 * via mod_customcert (plugin de terceiros). Se esse plugin não
 * estiver instalado, mostra uma mensagem explicando em vez de
 * quebrar a página.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();

$page = optional_param('page', 0, PARAM_INT);
$perpage = 8;

$PAGE->set_url(new moodle_url('/local/isgcorp/certificates.php', ['page' => $page]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycertificates', 'local_isgcorp'));

global $USER, $DB;

echo $OUTPUT->header();

echo '<div class="isg-trilhas-page">';

echo '<div class="isg-cert-hero">';
echo     '<span class="isg-cert-eyebrow">' . get_string('certeyebrow', 'local_isgcorp') . '</span>';
echo     '<h1 class="isg-cert-hero-title">' . get_string('certherotitle', 'local_isgcorp') . '</h1>';
echo     '<p class="isg-cert-hero-subtitle">' . get_string('certherosubtitle', 'local_isgcorp') . '</p>';
echo '</div>';

$dbman = $DB->get_manager();
$table = new xmldb_table('customcert_issues');

if (!$dbman->table_exists($table)) {
    echo html_writer::div(get_string('customcertnotinstalled', 'local_isgcorp'), 'isg-empty');
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Busca todos os certificados do usuário (sem paginação ainda,
// pra calcular os totais dos cards de estatística).
$sql = "SELECT ci.id, ci.timecreated, cc.name AS certname, cc.course AS courseid, cm.id AS cmid
          FROM {customcert_issues} ci
          JOIN {customcert} cc ON cc.id = ci.customcertid
          JOIN {course_modules} cm ON cm.instance = cc.id
          JOIN {modules} m ON m.id = cm.module AND m.name = 'customcert'
         WHERE ci.userid = :userid
      ORDER BY ci.timecreated DESC";

$allcerts = $DB->get_records_sql($sql, ['userid' => $USER->id]);

$totalcount = count($allcerts);
$lastyear = null;
$advancedcount = 0;

// "Certificações avançadas" = certificados de cursos que pertencem
// a uma trilha de nível "avançado" (dado real, cruzado com nossa
// própria tabela de trilhas — não é um conceito nativo do
// mod_customcert).
foreach ($allcerts as $cert) {
    if ($lastyear === null) {
        $lastyear = userdate($cert->timecreated, '%Y');
    }
    $trilhalevel = $DB->get_field_sql(
        "SELECT t.level
           FROM {local_isgcorp_trilha_course} tc
           JOIN {local_isgcorp_trilha} t ON t.id = tc.trilhaid
          WHERE tc.courseid = :courseid AND t.level = 'avancado'",
        ['courseid' => $cert->courseid]
    );
    if ($trilhalevel) {
        $advancedcount++;
    }
}

// Cards de estatística.
echo '<div class="isg-stat-grid mb-4">';

echo '<div class="isg-stat-card">';
echo     '<div class="isg-stat-icon">&#127942;</div>';
echo     '<div><div class="isg-stat-value">' . $totalcount . '</div><div class="isg-stat-sub">' . get_string('certcount', 'local_isgcorp') . '</div></div>';
echo '</div>';

echo '<div class="isg-stat-card">';
echo     '<div class="isg-stat-icon">&#128200;</div>';
echo     '<div><div class="isg-stat-value">' . $advancedcount . '</div><div class="isg-stat-sub">' . get_string('certadvanced', 'local_isgcorp') . '</div></div>';
echo '</div>';

echo '<div class="isg-stat-card">';
echo     '<div class="isg-stat-icon">&#128197;</div>';
echo     '<div><div class="isg-stat-value">' . ($lastyear ?? '&mdash;') . '</div><div class="isg-stat-sub">' . get_string('certlast', 'local_isgcorp') . '</div></div>';
echo '</div>';

echo '</div>';

echo '<h2 class="isg-section-title">' . get_string('mycertificates', 'local_isgcorp') . '</h2>';

if (empty($allcerts)) {
    echo html_writer::div(get_string('nocertificates', 'local_isgcorp'), 'isg-empty');
} else {
    // Pagina o array já carregado (dataset pequeno o suficiente
    // pra isso ser tranquilo; se crescer muito, trocamos por
    // LIMIT/OFFSET direto no SQL).
    $paged = array_slice(array_values($allcerts), $page * $perpage, $perpage);

    echo '<div class="isg-cert-grid">';
    foreach ($paged as $cert) {
        $downloadurl = new moodle_url('/mod/customcert/view.php', ['id' => $cert->cmid, 'downloadown' => 1]);
        $date = userdate($cert->timecreated, get_string('certdateformat', 'local_isgcorp'));

        echo '<div class="isg-cert-card">';
        echo     '<div class="isg-cert-top">';
        echo         '<span class="isg-cert-label">' . get_string('certificatelabel', 'local_isgcorp') . '</span>';
        echo         '<span class="isg-cert-badge">&#127942;</span>';
        echo     '</div>';
        echo     '<div class="isg-cert-title">' . format_string($cert->certname) . '</div>';
        echo     '<div class="isg-cert-date">' . get_string('completedon', 'local_isgcorp', $date) . '</div>';
        echo     '<a class="isg-cert-download" href="' . $downloadurl->out() . '" title="' . get_string('download', 'local_isgcorp') . '">&#11015;</a>';
        echo '</div>';
    }
    echo '</div>';

    // Paginação simples.
    $totalpages = (int) ceil($totalcount / $perpage);
    if ($totalpages > 1) {
        echo '<div class="isg-pagination">';
        for ($p = 0; $p < $totalpages; $p++) {
            $url = new moodle_url('/local/isgcorp/certificates.php', ['page' => $p]);
            $active = ($p === $page) ? 'active' : '';
            echo '<a class="isg-page-link ' . $active . '" href="' . $url->out() . '">' . ($p + 1) . '</a>';
        }
        echo '</div>';
    }
}

echo '</div>';

echo $OUTPUT->footer();

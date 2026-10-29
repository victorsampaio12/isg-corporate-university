<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$trilhaid = optional_param('trilhaid', 0, PARAM_INT);
// Links antigos apontavam para o curso; resolve a trilha que o contem.
$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$trilhaid && $courseid && ($coursetrilha = local_isgcorp_get_trilha_by_courseid($courseid))) {
    $trilhaid = (int) $coursetrilha->id;
}
$trilha = $DB->get_record('local_isgcorp_trilha', ['id' => $trilhaid], '*', MUST_EXIST);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/isgcorp/certificate.php', ['trilhaid' => $trilhaid]));

$issue = local_isgcorp_get_or_create_trilha_certificate($trilhaid, (int)$USER->id);
$hours = local_isgcorp_get_trilha_course_hours($trilhaid);
$hourslabel = rtrim(rtrim(number_format($hours, 1, ',', ''), '0'), ',') . 'h';
$date = userdate($issue->timecreated, '%d de %B de %Y');
$certificatename = local_isgcorp_get_certificate_name((int)$USER->id);
$background = __DIR__ . '/pix/certificate-background.png';
$backgroundcontent = null;
$theme = theme_config::load('isgcorp');
$configuredbackground = $theme->setting_file_url('certificatebackground', 'certificatebackground');
if ($configuredbackground) {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        context_system::instance()->id,
        'theme_isgcorp',
        'certificatebackground',
        0,
        'id DESC',
        false
    );
    if ($file = reset($files)) {
        $backgroundcontent = $file->get_content();
    }
}

if ($backgroundcontent === null && !is_readable($background)) {
    throw new moodle_exception('filenotfound', 'error');
}

$pdf = new pdf('L', 'mm', [270, 180], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage('L', [270, 180]);
$pdf->Image($backgroundcontent !== null ? '@' . $backgroundcontent : $background, 0, 0, 270, 180);

$pdf->SetTextColor(47, 42, 42);
$pdf->SetFont('helvetica', '', 11.5);
$pdf->SetXY(45, 64);
$statement = get_string('certificateintro', 'local_isgcorp') . ' <strong>' . s($certificatename) . '</strong> ' . get_string('certificatecompletiontext', 'local_isgcorp');
$pdf->writeHTMLCell(142, 12, '', '', $statement, 0, 1, false, true, 'L', true);
$pdf->SetFont('helvetica', 'B', 27);
$pdf->SetXY(45, 80);
$pdf->MultiCell(142, 14, format_string($trilha->name), 0, 'L', false, 1);

$pdf->SetFont('helvetica', '', 19);
$pdf->SetXY(45, 103);
$pdf->Cell(48, 10, 'Concluido em', 0, 0, 'L');
$pdf->SetTextColor(170, 0, 0);
$pdf->SetFont('helvetica', 'B', 19);
$pdf->SetXY(93, 103);
$pdf->Cell(94, 10, $date, 0, 0, 'L');

$pdf->SetTextColor(20, 20, 20);
$pdf->SetFont('helvetica', 'B', 17);
$pdf->SetXY(63, 122);
$pdf->Cell(30, 10, $hourslabel, 0, 0, 'L');

$pdf->SetTextColor(90, 90, 90);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY(185, 166);
$pdf->Cell(48, 5, 'Codigo: ' . $issue->code, 0, 0, 'R');

$filename = clean_filename('Certificado - ' . format_string($trilha->name) . '.pdf');
$pdf->Output($filename, 'D');
exit;

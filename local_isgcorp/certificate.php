<?php
// This file is part of Moodle - http://moodle.org/.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$trilhaid = required_param('trilhaid', PARAM_INT);
$trilha = $DB->get_record('local_isgcorp_trilha', ['id' => $trilhaid, 'visible' => 1], '*', MUST_EXIST);

if (!local_isgcorp_user_can_access_trilha($trilhaid, (int) $USER->id)) {
    throw new required_capability_exception(context_system::instance(), 'moodle/course:view', 'nopermissions', '');
}

$issue = local_isgcorp_get_or_create_certificate($trilhaid, (int) $USER->id);
$hours = local_isgcorp_get_trilha_course_hours($trilhaid);
$hourslabel = rtrim(rtrim(number_format($hours, 1, ',', ''), '0'), ',') . 'h';
$date = userdate($issue->timecreated, '%d de %B de %Y');
$background = __DIR__ . '/pix/certificate-background.png';

if (!is_readable($background)) {
    throw new moodle_exception('filenotfound', 'error');
}

$pdf = new pdf('L', 'mm', [270, 180], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage('L', [270, 180]);
$pdf->Image($background, 0, 0, 270, 180, 'PNG');

$pdf->SetTextColor(47, 42, 42);
$pdf->SetFont('helvetica', 'B', 34);
$pdf->SetXY(45, 69);
$pdf->MultiCell(142, 18, format_string($trilha->name), 0, 'L', false, 1);

$pdf->SetFont('helvetica', '', 19);
$pdf->SetXY(45, 95);
$pdf->Cell(48, 10, 'Concluido em', 0, 0, 'L');
$pdf->SetTextColor(170, 0, 0);
$pdf->SetFont('helvetica', 'B', 19);
$pdf->SetXY(93, 95);
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

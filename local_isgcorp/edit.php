<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Cria ou edita uma trilha, sua imagem de capa e suas associações
 * de curso.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/form/trilha_form.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/isgcorp:manage', $context);

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/isgcorp/edit.php', ['id' => $id]));
$PAGE->set_context($context);
admin_externalpage_setup('local_isgcorp_manage');

$filemanageroptions = [
    'maxfiles' => 1,
    'subdirs' => 0,
    'accepted_types' => ['web_image'],
];

$trilha = null;
$courseids = [];

if ($id) {
    $trilha = $DB->get_record('local_isgcorp_trilha', ['id' => $id], '*', MUST_EXIST);
    $courseids = $DB->get_fieldset_select(
        'local_isgcorp_trilha_course',
        'courseid',
        'trilhaid = ?',
        [$id]
    );
} else {
    $trilha = new stdClass();
    $trilha->id = null;
}

$mform = new \local_isgcorp\form\trilha_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/isgcorp/manage.php'));

} else if ($data = $mform->get_data()) {
    $record = new stdClass();
    $record->name = $data->name;
    $record->description = $data->description_editor['text'] ?? '';
    $record->descriptionformat = $data->description_editor['format'] ?? FORMAT_HTML;
    $record->level = $data->level;
    $record->category = $data->category ?? '';
    $record->estimatedhours = (int) $data->estimatedhours;
    $record->visible = !empty($data->visible) ? 1 : 0;
    $record->timemodified = time();
    $record->usermodified = $USER->id;

    if (!empty($data->id)) {
        $record->id = $data->id;
        $DB->update_record('local_isgcorp_trilha', $record);
        $trilhaid = $record->id;
    } else {
        $record->sortorder = 0;
        $record->timecreated = time();
        $trilhaid = $DB->insert_record('local_isgcorp_trilha', $record);
    }

    // A imagem só pode ser movida do rascunho pro definitivo depois
    // que sabemos o id final da trilha (para inserts novos).
    file_postupdate_standard_filemanager(
        $data,
        'coverimage',
        $filemanageroptions,
        $context,
        'local_isgcorp',
        'coverimage',
        $trilhaid
    );

    // Recria as associações de curso do zero a cada salvamento.
    $DB->delete_records('local_isgcorp_trilha_course', ['trilhaid' => $trilhaid]);
    $selectedcourses = $data->courseids ?? [];
    $sort = 0;
    foreach ($selectedcourses as $courseid) {
        $link = new stdClass();
        $link->trilhaid = $trilhaid;
        $link->courseid = (int) $courseid;
        $link->sortorder = $sort++;
        $DB->insert_record('local_isgcorp_trilha_course', $link);
    }

    redirect(
        new moodle_url('/local/isgcorp/manage.php'),
        get_string('trilhasaved', 'local_isgcorp'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );

} else {
    $trilha->courseids = $courseids;
    if (!empty($trilha->description)) {
        $trilha->description_editor = [
            'text' => $trilha->description,
            'format' => $trilha->descriptionformat,
        ];
    }
    $trilha = file_prepare_standard_filemanager(
        $trilha,
        'coverimage',
        $filemanageroptions,
        $context,
        'local_isgcorp',
        'coverimage',
        $trilha->id
    );
    $mform->set_data($trilha);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetrilhas', 'local_isgcorp'));
$mform->display();
echo $OUTPUT->footer();

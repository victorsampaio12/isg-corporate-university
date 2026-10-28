<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Apaga uma trilha e suas associações de curso.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/isgcorp:manage', $context);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}

$id = required_param('id', PARAM_INT);
require_sesskey();

$DB->get_record('local_isgcorp_trilha', ['id' => $id], '*', MUST_EXIST);

$DB->delete_records('local_isgcorp_trilha_course', ['trilhaid' => $id]);
$DB->delete_records('local_isgcorp_trilha_cert', ['trilhaid' => $id]);
$DB->delete_records('local_isgcorp_trilha', ['id' => $id]);
get_file_storage()->delete_area_files($context->id, 'local_isgcorp', 'coverimage', $id);

redirect(
    new moodle_url('/local/isgcorp/manage.php'),
    get_string('trilhadeleted', 'local_isgcorp'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);

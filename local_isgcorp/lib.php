<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Funções de apoio do local_isgcorp — busca de trilhas e cálculo
 * de progresso, reaproveitadas pela listagem pública e pelo
 * bloco do dashboard.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Lista as trilhas cadastradas.
 *
 * @param bool $onlyvisible Se true, retorna só as trilhas marcadas como visíveis.
 * @return array
 */
function local_isgcorp_get_trilhas(bool $onlyvisible = true): array {
    global $DB;

    $conditions = $onlyvisible ? ['visible' => 1] : [];

    return $DB->get_records('local_isgcorp_trilha', $conditions, 'sortorder ASC, id ASC');
}

/**
 * Retorna os cursos (objetos completos) associados a uma trilha,
 * na ordem definida no cadastro.
 *
 * @param int $trilhaid
 * @return array
 */
function local_isgcorp_get_trilha_courses(int $trilhaid): array {
    global $DB;

    $sql = "SELECT c.*
              FROM {course} c
              JOIN {local_isgcorp_trilha_course} tc ON tc.courseid = c.id
             WHERE tc.trilhaid = :trilhaid
          ORDER BY tc.sortorder ASC";

    return $DB->get_records_sql($sql, ['trilhaid' => $trilhaid]);
}

/**
 * Progresso médio do usuário numa trilha: média do percentual de
 * conclusão de cada curso que a compõe. Cursos sem rastreamento de
 * conclusão habilitado são ignorados no cálculo (não contam nem a
 * favor, nem contra).
 *
 * @param int $trilhaid
 * @param int $userid
 * @return int|null Percentual de 0 a 100, ou null se não houver
 *                   nenhum curso com rastreamento na trilha.
 */
function local_isgcorp_get_trilha_progress(int $trilhaid, int $userid): ?int {
    $courses = local_isgcorp_get_trilha_courses($trilhaid);

    if (empty($courses)) {
        return null;
    }

    $sum = 0;
    $counted = 0;

    foreach ($courses as $course) {
        try {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        } catch (\Throwable $e) {
            $percent = null;
        }
        if ($percent !== null) {
            $sum += $percent;
            $counted++;
        }
    }

    if ($counted === 0) {
        return null;
    }

    return (int) round($sum / $counted);
}

/**
 * URL da imagem de capa de uma trilha, se houver uma cadastrada.
 *
 * @param int $trilhaid
 * @return \moodle_url|null
 */
function local_isgcorp_get_trilha_cover_url(int $trilhaid): ?\moodle_url {
    $context = \context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_isgcorp', 'coverimage', $trilhaid, 'itemid', false);

    if (empty($files)) {
        return null;
    }

    $file = reset($files);

    return \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'local_isgcorp',
        'coverimage',
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * Serve a imagem de capa de uma trilha através de pluginfile.php.
 * Requerido pelo Moodle sempre que um plugin armazena arquivos via
 * File API (upload no formulário de trilha).
 */
function local_isgcorp_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== 'coverimage') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_isgcorp', 'coverimage', $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

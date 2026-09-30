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

require_once($CFG->libdir . '/completionlib.php');

/**
 * Verifica se a tabela de progresso manual por aula ja existe.
 *
 * Isso evita fatal caso o codigo seja carregado antes do upgrade do
 * plugin terminar de criar a tabela nova.
 *
 * @return bool
 */
function local_isgcorp_manual_tracking_available(): bool {
    global $CFG, $DB;

    static $available = null;

    if ($available === null) {
        if (!class_exists('xmldb_table')) {
            require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        }
        $available = $DB->get_manager()->table_exists(new xmldb_table('local_isgcorp_lesson_progress'));
    }

    return $available;
}

/**
 * Verifica se a tabela de rastreamento manual ja suporta cmid.
 *
 * Isso protege o codigo enquanto o upgrade de banco ainda nao rodou.
 *
 * @return bool
 */
function local_isgcorp_manual_tracking_supports_cmid(): bool {
    global $CFG, $DB;

    static $available = null;

    if ($available === null) {
        if (!local_isgcorp_manual_tracking_available()) {
            $available = false;
            return $available;
        }

        if (!class_exists('xmldb_field')) {
            require_once($CFG->libdir . '/xmldb/xmldb_field.php');
        }

        $table = new xmldb_table('local_isgcorp_lesson_progress');
        $field = new xmldb_field('cmid');
        $available = $DB->get_manager()->field_exists($table, $field);
    }

    return $available;
}

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

    $totalcourses = count($courses);
    $completedcourses = 0;

    foreach ($courses as $course) {
        $progressdata = local_isgcorp_get_course_progress_data($course, $userid);
        if (($progressdata['percent'] ?? 0) >= 100) {
            $completedcourses++;
        }
    }

    return (int) round(($completedcourses / $totalcourses) * 100);
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
 * Retorna uma imagem de overview de curso, se existir.
 *
 * @param int $courseid
 * @return \moodle_url|null
 */
function local_isgcorp_get_course_overview_image_url(int $courseid): ?\moodle_url {
    $context = \context_course::instance($courseid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'itemid', false);

    foreach ($files as $file) {
        if (strpos($file->get_mimetype(), 'image/') !== 0) {
            continue;
        }

        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'course',
            'overviewfiles',
            0,
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    return null;
}

/**
 * Resolve a melhor imagem disponivel para representar a trilha.
 *
 * Prioridade:
 * 1. capa cadastrada na propria trilha
 * 2. imagem de overview do primeiro curso que tiver imagem
 * 3. banner padrao do tema
 *
 * @param stdClass $trilha
 * @param array|null $courses
 * @return \moodle_url|null
 */
function local_isgcorp_get_trilha_visual_url(\stdClass $trilha, ?array $courses = null): ?\moodle_url {
    global $OUTPUT;

    $coverurl = local_isgcorp_get_trilha_cover_url((int) $trilha->id);
    if ($coverurl) {
        return $coverurl;
    }

    $courses = $courses ?? local_isgcorp_get_trilha_courses((int) $trilha->id);
    foreach ($courses as $course) {
        $coursecover = local_isgcorp_get_course_overview_image_url((int) $course->id);
        if ($coursecover) {
            return $coursecover;
        }
    }

    return $OUTPUT->image_url('trilha-onboarding-banner', 'theme_isgcorp');
}

/**
 * Retorna a primeira trilha vinculada a um curso.
 *
 * @param int $courseid
 * @return \stdClass|null
 */
function local_isgcorp_get_trilha_by_courseid(int $courseid): ?\stdClass {
    global $DB;

    $sql = "SELECT t.*
              FROM {local_isgcorp_trilha} t
              JOIN {local_isgcorp_trilha_course} tc ON tc.trilhaid = t.id
             WHERE tc.courseid = :courseid
          ORDER BY tc.sortorder ASC, t.id ASC";

    return $DB->get_record_sql($sql, ['courseid' => $courseid]) ?: null;
}

/**
 * URL customizada da pagina de curso da trilha.
 *
 * @param int $courseid
 * @param int|null $trilhaid
 * @return \moodle_url
 */
function local_isgcorp_get_course_page_url(int $courseid, ?int $trilhaid = null): \moodle_url {
    $params = ['courseid' => $courseid];
    if ($trilhaid) {
        $params['trilhaid'] = $trilhaid;
    }

    return new \moodle_url('/local/isgcorp/course.php', $params);
}

/**
 * URL customizada da pagina de aula da trilha.
 *
 * @param int $courseid
 * @param int $cmid
 * @param int|null $trilhaid
 * @return \moodle_url
 */
function local_isgcorp_get_lesson_page_url(int $courseid, int $cmid, ?int $trilhaid = null): \moodle_url {
    $params = [
        'courseid' => $courseid,
        'cmid' => $cmid,
    ];
    if ($trilhaid) {
        $params['trilhaid'] = $trilhaid;
    }

    return new \moodle_url('/local/isgcorp/lesson.php', $params);
}

/**
 * Converte HTML para texto simples.
 *
 * @param string $html
 * @param int $maxlen
 * @return string
 */
function local_isgcorp_extract_plain_text(string $html, int $maxlen = 0): string {
    $cleanhtml = preg_replace('/<a\b[^>]*class\s*=\s*(["\'])[^"\']*\bmediafallbacklink\b[^"\']*\1[^>]*>.*?<\/a>/is', '', $html);
    if ($cleanhtml !== null) {
        $html = $cleanhtml;
    }

    // Arquivos de mídia são conteúdo, não uma descrição legível da aula.
    $cleanhtml = preg_replace('/<(video|audio)\b[^>]*>.*?<\/\1>/is', '', $html);
    if ($cleanhtml !== null) {
        $html = $cleanhtml;
    }

    $cleanhtml = preg_replace('/@@PLUGINFILE@@\/[^\s<]+/iu', '', $html);
    if ($cleanhtml !== null) {
        $html = $cleanhtml;
    }

    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

    if ($maxlen > 0 && core_text::strlen($text) > $maxlen) {
        return rtrim(core_text::substr($text, 0, $maxlen - 1)) . '...';
    }

    return $text;
}

/**
 * Retorna se uma aula customizada ja foi concluida pelo usuario.
 *
 * @param int $userid
 * @param int $courseid
 * @param int $sectionid
 * @param int $cmid
 * @return bool
 */
function local_isgcorp_is_lesson_completed(int $userid, int $courseid, int $sectionid, int $cmid = 0): bool {
    global $DB;

    if (!local_isgcorp_manual_tracking_available()) {
        return false;
    }

    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
        'completed' => 1,
    ];

    if ($cmid > 0 && local_isgcorp_manual_tracking_supports_cmid()) {
        $params['cmid'] = $cmid;
    } else {
        $params['sectionid'] = $sectionid;
    }

    return $DB->record_exists('local_isgcorp_lesson_progress', $params);
}

/**
 * Salva a conclusao manual de uma aula customizada.
 *
 * @param int $userid
 * @param int $courseid
 * @param int $sectionid
 * @param bool $completed
 * @param int $cmid
 * @return void
 */
function local_isgcorp_set_lesson_completion(
    int $userid,
    int $courseid,
    int $sectionid,
    bool $completed,
    int $cmid = 0
): void {
    global $DB;

    if (!local_isgcorp_manual_tracking_available()) {
        return;
    }

    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
    ];

    if ($cmid > 0 && local_isgcorp_manual_tracking_supports_cmid()) {
        $params['cmid'] = $cmid;
    } else {
        $params['sectionid'] = $sectionid;
    }

    $record = $DB->get_record('local_isgcorp_lesson_progress', $params);

    $now = time();

    if ($record) {
        $record->completed = $completed ? 1 : 0;
        $record->sectionid = $sectionid;
        if (local_isgcorp_manual_tracking_supports_cmid()) {
            $record->cmid = $cmid > 0 ? $cmid : null;
        }
        $record->timemodified = $now;
        $record->timecompleted = $completed ? $now : 0;
        $DB->update_record('local_isgcorp_lesson_progress', $record);
        return;
    }

    $record = (object) [
        'userid' => $userid,
        'courseid' => $courseid,
        'sectionid' => $sectionid,
        'completed' => $completed ? 1 : 0,
        'timecreated' => $now,
        'timemodified' => $now,
        'timecompleted' => $completed ? $now : 0,
    ];
    if (local_isgcorp_manual_tracking_supports_cmid()) {
        $record->cmid = $cmid > 0 ? $cmid : null;
    }
    $DB->insert_record('local_isgcorp_lesson_progress', $record);
}

/**
 * Determina um rotulo amigavel para o tipo principal do conteudo.
 *
 * @param string $modname
 * @param string $bodyhtml
 * @param string|null $actionurl
 * @return string
 */
function local_isgcorp_get_content_kind_label(string $modname, string $bodyhtml = '', ?string $actionurl = null): string {
    $haystack = core_text::strtolower($bodyhtml . ' ' . ($actionurl ?? ''));

    if (strpos($haystack, '<video') !== false
        || strpos($haystack, 'youtube.com') !== false
        || strpos($haystack, 'youtu.be') !== false
        || strpos($haystack, 'vimeo.com') !== false
        || preg_match('/\.(mp4|webm|ogv|mov)(?:[?&#"\']|$)/i', $haystack)) {
        return get_string('contenttypevideo', 'local_isgcorp');
    }

    if ($modname === 'resource' || strpos($haystack, '.pdf') !== false) {
        return get_string('contenttypedocument', 'local_isgcorp');
    }

    if ($modname === 'url') {
        return get_string('contenttypelink', 'local_isgcorp');
    }

    if ($modname === 'forum') {
        return get_string('contenttypeactivity', 'local_isgcorp');
    }

    if ($modname === 'label' || $modname === 'page' || $modname === 'book' || $modname === 'lesson') {
        return get_string('contenttypelesson', 'local_isgcorp');
    }

    return local_isgcorp_get_module_display_name($modname);
}

/**
 * Determina o CTA principal de um conteudo.
 *
 * @param string $modname
 * @param string $bodyhtml
 * @param string|null $actionurl
 * @return string
 */
function local_isgcorp_get_content_action_label(string $modname, string $bodyhtml = '', ?string $actionurl = null): string {
    $type = local_isgcorp_get_content_kind_label($modname, $bodyhtml, $actionurl);

    if ($type === get_string('contenttypevideo', 'local_isgcorp')) {
        return get_string('watchcontent', 'local_isgcorp');
    }

    if ($type === get_string('contenttypedocument', 'local_isgcorp')) {
        return get_string('opendocument', 'local_isgcorp');
    }

    return get_string('opencontent', 'local_isgcorp');
}

/**
 * Retorna o conteudo principal de um modulo que sera tratado como aula.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param int $userid
 * @return array
 */
function local_isgcorp_get_course_module_content_item(\stdClass $course, \cm_info $cm, int $userid): array {
    global $DB;

    $bodyhtml = '';
    $actionurl = $cm->has_view() && !empty($cm->url) ? $cm->url->out(false) : null;
    $title = trim(local_isgcorp_extract_plain_text((string) $cm->get_formatted_name()));

    switch ($cm->modname) {
        case 'label':
            $record = $DB->get_record('label', ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
            $bodyhtml = format_module_intro('label', $record, $cm->id);
            $actionurl = null;
            $title = '';
            break;

        case 'page':
            $record = $DB->get_record(
                'page',
                ['id' => $cm->instance],
                'id, intro, introformat, content, contentformat, revision',
                MUST_EXIST
            );
            if (!empty($record->content)) {
                $content = file_rewrite_pluginfile_urls(
                    $record->content,
                    'pluginfile.php',
                    $cm->context->id,
                    'mod_page',
                    'content',
                    $record->revision
                );
                $bodyhtml = format_text($content, $record->contentformat, [
                    'context' => $cm->context,
                    'noclean' => true,
                    'overflowdiv' => true,
                ]);
            } else {
                $bodyhtml = format_module_intro('page', $record, $cm->id);
            }
            break;

        case 'resource':
            $record = $DB->get_record('resource', ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
            $bodyhtml = format_module_intro('resource', $record, $cm->id);
            break;

        case 'url':
            $record = $DB->get_record('url', ['id' => $cm->instance], 'id, intro, introformat, externalurl', MUST_EXIST);
            $bodyhtml = format_module_intro('url', $record, $cm->id);
            $actionurl = $record->externalurl ?: $actionurl;
            break;

        case 'forum':
            $record = $DB->get_record('forum', ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
            $bodyhtml = format_module_intro('forum', $record, $cm->id);
            break;

        case 'book':
            $record = $DB->get_record('book', ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
            $bodyhtml = format_module_intro('book', $record, $cm->id);
            break;

        case 'lesson':
            $record = $DB->get_record('lesson', ['id' => $cm->instance], 'id, intro, introformat', MUST_EXIST);
            $bodyhtml = format_module_intro('lesson', $record, $cm->id);
            break;
    }

    return [
        'cmid' => (int) $cm->id,
        'modname' => $cm->modname,
        'modlabel' => local_isgcorp_get_module_display_name($cm->modname),
        'contentlabel' => local_isgcorp_get_content_kind_label($cm->modname, $bodyhtml, $actionurl),
        'title' => $title,
        'bodyhtml' => $bodyhtml,
        'previewplain' => local_isgcorp_extract_plain_text($bodyhtml, 180),
        'actionurl' => $actionurl,
        'actionlabel' => local_isgcorp_get_content_action_label($cm->modname, $bodyhtml, $actionurl),
    ];
}

/**
 * Resolve o melhor nome de exibicao para a aula.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param object|null $sectioninfo
 * @param array $contentitem
 * @param bool $singleinsection
 * @return string
 */
function local_isgcorp_resolve_lesson_name(
    \stdClass $course,
    \cm_info $cm,
    ?object $sectioninfo,
    array $contentitem,
    bool $singleinsection
): string {
    $coursecontext = \context_course::instance($course->id);
    $sectionname = $sectioninfo
        ? trim(format_string($sectioninfo->name ?? '', true, ['context' => $coursecontext]))
        : '';
    $cmname = trim(local_isgcorp_extract_plain_text((string) $cm->get_formatted_name()));
    $genericname = trim(local_isgcorp_extract_plain_text(local_isgcorp_get_module_display_name($cm->modname)));
    $cmhasrealname = $cmname !== '' && core_text::strtolower($cmname) !== core_text::strtolower($genericname);

    if ($cm->modname === 'label' && $sectionname !== '') {
        return $sectionname;
    }

    if ($singleinsection && $sectionname !== '' && !$cmhasrealname) {
        return $sectionname;
    }

    if ($cmhasrealname) {
        return $cmname;
    }

    if ($sectionname !== '') {
        return $sectionname;
    }

    if (!empty($contentitem['title'])) {
        return trim(local_isgcorp_extract_plain_text((string) $contentitem['title']));
    }

    if (!empty($contentitem['previewplain'])) {
        return local_isgcorp_extract_plain_text((string) $contentitem['previewplain'], 80);
    }

    return get_string('trailsectionlabel', 'local_isgcorp') . ' ' . $cm->id;
}

/**
 * Estado de progresso da aula.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @param int $userid
 * @return array
 */
function local_isgcorp_get_lesson_progress_state(\stdClass $course, \cm_info $cm, int $userid): array {
    $completioninfo = new \completion_info($course);
    $tracking = $completioninfo->is_enabled($cm);

    if ($tracking != COMPLETION_TRACKING_NONE) {
        $data = $completioninfo->get_data($cm, false, $userid);
        $state = (int) ($data->completionstate ?? COMPLETION_INCOMPLETE);

        return [
            'tracked' => true,
            'completed' => in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
            'viewed' => (int) ($data->viewed ?? COMPLETION_NOT_VIEWED) === COMPLETION_VIEWED,
            'source' => $tracking == COMPLETION_TRACKING_MANUAL ? 'native-manual' : 'native-auto',
            'cancomplete' => $tracking == COMPLETION_TRACKING_MANUAL,
        ];
    }

    if (local_isgcorp_manual_tracking_available()) {
        $completed = local_isgcorp_is_lesson_completed(
            $userid,
            (int) $course->id,
            (int) $cm->section,
            local_isgcorp_manual_tracking_supports_cmid() ? (int) $cm->id : 0
        );

        return [
            'tracked' => true,
            'completed' => $completed,
            'viewed' => $completed,
            'source' => 'custom-manual',
            'cancomplete' => true,
        ];
    }

    return [
        'tracked' => false,
        'completed' => false,
        'viewed' => false,
        'source' => 'none',
        'cancomplete' => false,
    ];
}

/**
 * Marca a aula como visualizada para acionar conclusao por visualizacao.
 *
 * @param stdClass $course
 * @param int $cmid
 * @param int $userid
 * @return void
 */
function local_isgcorp_mark_lesson_viewed(\stdClass $course, int $cmid, int $userid): void {
    $modinfo = get_fast_modinfo($course, $userid);
    if (!isset($modinfo->cms[$cmid])) {
        return;
    }

    $cm = $modinfo->cms[$cmid];
    $completioninfo = new \completion_info($course);
    $completioninfo->set_module_viewed($cm, $userid);
}

/**
 * Alterna a conclusao da aula.
 *
 * @param stdClass $course
 * @param int $cmid
 * @param int $userid
 * @return void
 */
function local_isgcorp_toggle_lesson_completion(\stdClass $course, int $cmid, int $userid): void {
    $modinfo = get_fast_modinfo($course, $userid);
    if (!isset($modinfo->cms[$cmid])) {
        return;
    }

    $cm = $modinfo->cms[$cmid];
    $completioninfo = new \completion_info($course);
    $tracking = $completioninfo->is_enabled($cm);

    if ($tracking == COMPLETION_TRACKING_MANUAL) {
        $data = $completioninfo->get_data($cm, false, $userid);
        $state = (int) ($data->completionstate ?? COMPLETION_INCOMPLETE);
        $completed = in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
        $completioninfo->update_state($cm, $completed ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE, $userid);
        return;
    }

    if ($tracking == COMPLETION_TRACKING_NONE && local_isgcorp_manual_tracking_available()) {
        $cmidvalue = local_isgcorp_manual_tracking_supports_cmid() ? (int) $cm->id : 0;
        $completed = local_isgcorp_is_lesson_completed($userid, (int) $course->id, (int) $cm->section, $cmidvalue);
        local_isgcorp_set_lesson_completion($userid, (int) $course->id, (int) $cm->section, !$completed, $cmidvalue);
    }
}

/**
 * Blocos de conteudo da aula customizada.
 *
 * @param stdClass $course
 * @param int $cmid
 * @param int $userid
 * @return array
 */
function local_isgcorp_get_lesson_content_items(\stdClass $course, int $cmid, int $userid): array {
    $modinfo = get_fast_modinfo($course, $userid);
    if (!isset($modinfo->cms[$cmid])) {
        return [];
    }

    $cm = $modinfo->cms[$cmid];
    if (!$cm->uservisible || $cm->deletioninprogress) {
        return [];
    }

    return [local_isgcorp_get_course_module_content_item($course, $cm, $userid)];
}

/**
 * Lista as aulas de um curso usando atividades e recursos reais.
 *
 * @param stdClass $course
 * @param int $userid
 * @param int|null $trilhaid
 * @return array
 */
function local_isgcorp_get_course_lessons(\stdClass $course, int $userid, ?int $trilhaid = null): array {
    $modinfo = get_fast_modinfo($course, $userid);
    $lessons = [];
    $position = 1;

    foreach ($modinfo->sections as $sectionnum => $cmids) {
        if ((int) $sectionnum === 0) {
            continue;
        }

        $sectioninfo = $modinfo->get_section_info((int) $sectionnum);
        $visiblecmids = [];

        foreach ($cmids as $candidatecmid) {
            if (!isset($modinfo->cms[$candidatecmid])) {
                continue;
            }

            $candidatecm = $modinfo->cms[$candidatecmid];
            if (!$candidatecm->uservisible || $candidatecm->deletioninprogress) {
                continue;
            }

            $visiblecmids[] = $candidatecmid;
        }

        foreach ($visiblecmids as $cmid) {
            $cm = $modinfo->cms[$cmid];
            $contentitem = local_isgcorp_get_course_module_content_item($course, $cm, $userid);
            $progressstate = local_isgcorp_get_lesson_progress_state($course, $cm, $userid);

            $lessons[] = [
                'position' => $position++,
                'cmid' => (int) $cm->id,
                'sectionid' => (int) $cm->section,
                'sectionnum' => (int) $sectionnum,
                'name' => local_isgcorp_resolve_lesson_name($course, $cm, $sectioninfo, $contentitem, count($visiblecmids) === 1),
                'summaryplain' => $contentitem['previewplain'],
                'contentlabel' => $contentitem['contentlabel'],
                'actionlabel' => $contentitem['actionlabel'],
                'contentcount' => 1,
                'completed' => $progressstate['completed'],
                'tracked' => $progressstate['tracked'],
                'viewed' => $progressstate['viewed'],
                'cancomplete' => $progressstate['cancomplete'],
                'progresssource' => $progressstate['source'],
                'cmurl' => $contentitem['actionurl'],
                'url' => local_isgcorp_get_lesson_page_url((int) $course->id, (int) $cm->id, $trilhaid),
            ];
        }
    }

    return $lessons;
}

/**
 * Resumo do progresso manual de um curso baseado nas aulas.
 *
 * @param stdClass $course
 * @param int $userid
 * @param int|null $trilhaid
 * @return array|null
 */
function local_isgcorp_get_manual_course_progress_summary(\stdClass $course, int $userid, ?int $trilhaid = null): ?array {
    $lessons = local_isgcorp_get_course_lessons($course, $userid, $trilhaid);

    if (empty($lessons)) {
        return null;
    }

    $total = count($lessons);
    $completed = count(array_filter($lessons, fn($lesson) => !empty($lesson['completed'])));

    return [
        'total' => $total,
        'completed' => $completed,
        'percent' => (int) round(($completed / $total) * 100),
        'lessons' => $lessons,
    ];
}

/**
 * Dados de progresso e status de um curso para um usuario.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array
 */
function local_isgcorp_get_course_progress_data(\stdClass $course, int $userid): array {
    $manualsummary = local_isgcorp_get_manual_course_progress_summary($course, $userid);
    if ($manualsummary !== null) {
        $percent = $manualsummary['percent'];
    } else {
        try {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        } catch (\Throwable $e) {
            $percent = null;
        }
    }

    if ($percent !== null) {
        $percent = (int) round($percent);
    }

    if ($percent === null) {
        return [
            'percent' => null,
            'statuslabel' => get_string('statusunavailable', 'local_isgcorp'),
            'statusclass' => 'neutral',
        ];
    }

    if ($percent <= 0) {
        return [
            'percent' => 0,
            'statuslabel' => get_string('statusnotstarted', 'local_isgcorp'),
            'statusclass' => 'neutral',
        ];
    }

    if ($percent < 100) {
        return [
            'percent' => $percent,
            'statuslabel' => get_string('statusinprogress', 'local_isgcorp'),
            'statusclass' => 'progress',
        ];
    }

    return [
        'percent' => 100,
        'statuslabel' => get_string('statuscomplete', 'local_isgcorp'),
        'statusclass' => 'complete',
    ];
}

/**
 * Nome amigavel do tipo de atividade.
 *
 * @param string $modname
 * @return string
 */
function local_isgcorp_get_module_display_name(string $modname): string {
    $component = 'mod_' . $modname;
    $stringmanager = get_string_manager();

    if ($stringmanager->string_exists('pluginname', $component)) {
        return get_string('pluginname', $component);
    }

    if ($stringmanager->string_exists('modulename', $component)) {
        return get_string('modulename', $component);
    }

    return ucfirst($modname);
}

/**
 * Retorna o outline navegavel das atividades visiveis do curso.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array
 */
function local_isgcorp_get_course_activity_outline(\stdClass $course, int $userid, ?int $trilhaid = null): array {
    $lessonsummary = local_isgcorp_get_manual_course_progress_summary($course, $userid, $trilhaid);
    if ($lessonsummary !== null) {
        $items = [];

        foreach ($lessonsummary['lessons'] as $lesson) {
            $items[] = [
                'name' => $lesson['name'],
                'url' => $lesson['url'],
                'modname' => 'cm',
                'modlabel' => $lesson['contentlabel'],
                'tracked' => !empty($lesson['tracked']),
                'completed' => !empty($lesson['completed']),
                'viewed' => !empty($lesson['viewed']),
            ];
        }

        return [
            'items' => $items,
            'trackedcount' => $lessonsummary['total'],
            'completedcount' => $lessonsummary['completed'],
        ];
    }

    return [
        'items' => [],
        'trackedcount' => 0,
        'completedcount' => 0,
    ];
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

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
 * Icone em linha (SVG, traco fino, herda a cor do texto), no estilo Lucide.
 * Substitui os emojis das paginas para manter um visual limpo e monocromatico.
 *
 * @param string $name Nome do icone (ver $paths).
 * @param string $class Classes extras do elemento svg.
 * @return string HTML do svg, ou '' se o nome nao existir.
 */
function local_isgcorp_icon(string $name, string $class = ''): string {
    static $paths = [
        'search' => '<circle cx="11" cy="11" r="7.5"/><path d="m20.5 20.5-4.2-4.2"/>',
        'book-open' => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
        'level' => '<path d="M5 20v-5"/><path d="M12 20V10"/><path d="M19 20V4"/>',
        'clock' => '<circle cx="12" cy="12" r="9.5"/><path d="M12 6.5V12l3.5 2"/>',
        'award' => '<circle cx="12" cy="8.5" r="6"/><path d="m15.48 13.4 1.52 8.6-5-3-5 3 1.52-8.6"/>',
        'trending-up' => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M16 2.5v4"/><path d="M8 2.5v4"/><path d="M3 10h18"/>',
        'graduation-cap' => '<path d="M21.42 10.92a1 1 0 0 0-.02-1.84L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.83l8.57 3.91a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
        'library' => '<path d="m16 6 4 14"/><path d="M12 6v14"/><path d="M8 8v12"/><path d="M4 4v16"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9.5"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'file-text' => '<path d="M14.5 2.5H6a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2.5V8h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        'pie-chart' => '<path d="M21.2 15.9A10 10 0 1 1 8 2.8"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'play' => '<path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z"/>',
        'star' => '<path d="M11.52 2.3a.53.53 0 0 1 .96 0l2.31 4.68a2.12 2.12 0 0 0 1.6 1.16l5.16.76a.53.53 0 0 1 .3.9l-3.74 3.64a2.12 2.12 0 0 0-.61 1.88l.88 5.14a.53.53 0 0 1-.77.56l-4.62-2.43a2.12 2.12 0 0 0-1.97 0L6.4 21.02a.53.53 0 0 1-.77-.56l.88-5.14a2.12 2.12 0 0 0-.61-1.88L2.16 9.8a.53.53 0 0 1 .29-.9l5.17-.76a2.12 2.12 0 0 0 1.6-1.16z"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    $classes = trim('isg-icon isg-icon-' . $name . ' ' . $class);
    return '<svg class="' . s($classes) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/**
 * Garante o campo personalizado de curso "Carga horaria", usado na soma
 * de horas do certificado. Chamado na instalacao e no upgrade.
 */
function local_isgcorp_ensure_workload_field(): void {
    global $DB;

    $handler = \core_course\customfield\course_handler::create();
    $hoursfield = $DB->get_record('customfield_field', [
        'shortname' => 'isg_cargahoraria',
    ]);
    if (!$hoursfield) {
        $categoryrecord = (object) [
            'name' => 'Universidade Corporativa ISG',
            'component' => $handler->get_component(),
            'area' => $handler->get_area(),
            'itemid' => $handler->get_itemid(),
            'contextid' => $handler->get_configuration_context()->id,
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $category = \core_customfield\category_controller::create(0, $categoryrecord, $handler);
        $category->save();

        $fieldrecord = (object) [
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'name' => 'Carga horaria (horas)',
            'shortname' => 'isg_cargahoraria',
            'description' => 'Carga horaria usada na soma do certificado da trilha.',
            'descriptionformat' => FORMAT_PLAIN,
            'sortorder' => 0,
            'configdata' => json_encode(['defaultvalue' => '', 'displaysize' => 10, 'maxlength' => 10]),
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $field = \core_customfield\field_controller::create(0, $fieldrecord, $category);
        $field->save();
    }
}

/**
 * Ensures the optional certificate display name exists in user profiles.
 */
function local_isgcorp_ensure_certificate_profile_field(): void {
    global $DB;
    if ($DB->record_exists('user_info_field', ['shortname' => 'isg_certificatename'])) { return; }
    $category = $DB->get_record('user_info_category', ['name' => get_string('profilecategory', 'local_isgcorp')]);
    if (!$category) {
        $category = (object) ['name' => get_string('profilecategory', 'local_isgcorp'), 'sortorder' => (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {user_info_category}')];
        $category->id = $DB->insert_record('user_info_category', $category);
    }
    $DB->insert_record('user_info_field', (object) [
        'shortname' => 'isg_certificatename', 'name' => get_string('certificatenamefield', 'local_isgcorp'), 'datatype' => 'text',
        'description' => get_string('certificatenamefielddesc', 'local_isgcorp'), 'descriptionformat' => FORMAT_PLAIN,
        'categoryid' => $category->id, 'sortorder' => 0, 'required' => 0, 'locked' => 0, 'visible' => 2,
        'forceunique' => 0, 'signup' => 0, 'defaultdata' => '', 'defaultdataformat' => FORMAT_PLAIN,
        'param1' => '50', 'param2' => '255', 'param3' => '0', 'param4' => '', 'param5' => '',
    ]);
}

/**
 * Returns the preferred certificate name, falling back to the Moodle full name.
 */
function local_isgcorp_get_certificate_name(int $userid): string {
    global $DB;
    $sql = "SELECT uid.data FROM {user_info_data} uid JOIN {user_info_field} uif ON uif.id = uid.fieldid WHERE uid.userid = :userid AND uif.shortname = :shortname";
    $preferredname = trim((string)$DB->get_field_sql($sql, ['userid' => $userid, 'shortname' => 'isg_certificatename']));
    if ($preferredname !== '') { return $preferredname; }
    $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', MUST_EXIST);
    return fullname($user);
}

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
 * Soma a carga horaria cadastrada nos cursos de uma trilha.
 *
 * @param int $trilhaid
 * @return float
 */
function local_isgcorp_get_trilha_course_hours(int $trilhaid): float {
    global $DB;

    $sql = "SELECT cd.value
              FROM {local_isgcorp_trilha_course} tc
              JOIN {customfield_field} f ON f.shortname = :shortname
              JOIN {customfield_category} cat ON cat.id = f.categoryid
                   AND cat.component = :component AND cat.area = :area
         LEFT JOIN {customfield_data} cd ON cd.fieldid = f.id AND cd.instanceid = tc.courseid
             WHERE tc.trilhaid = :trilhaid";
    $values = $DB->get_fieldset_sql($sql, [
        'shortname' => 'isg_cargahoraria',
        'component' => 'core_course',
        'area' => 'course',
        'trilhaid' => $trilhaid,
    ]);

    return array_reduce($values, function(float $total, $value): float {
        $normalised = str_replace(',', '.', trim((string) $value));
        return $total + (is_numeric($normalised) ? (float) $normalised : 0);
    }, 0.0);
}

/**
 * Returns the workload configured for a course.
 */
function local_isgcorp_get_course_hours(int $courseid): float {
    global $DB;

    $sql = "SELECT cd.value
              FROM {customfield_field} f
              JOIN {customfield_category} cat ON cat.id = f.categoryid
         LEFT JOIN {customfield_data} cd ON cd.fieldid = f.id AND cd.instanceid = :courseid
             WHERE f.shortname = :shortname
               AND cat.component = :component
               AND cat.area = :area";
    $value = $DB->get_field_sql($sql, [
        'courseid' => $courseid,
        'shortname' => 'isg_cargahoraria',
        'component' => 'core_course',
        'area' => 'course',
    ]);
    $normalised = str_replace(',', '.', trim((string)$value));

    return is_numeric($normalised) ? (float)$normalised : 0.0;
}

/**
 * Finds and normalises the first visible quiz used as the course assessment.
 */
function local_isgcorp_get_course_assessment(int $courseid): ?\stdClass {
    global $DB;

    $sql = "SELECT q.*, cm.id AS cmid
              FROM {quiz} q
              JOIN {modules} m ON m.name = 'quiz'
              JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = q.id
             WHERE q.course = :courseid AND cm.deletioninprogress = 0
          ORDER BY cm.id ASC";
    $quiz = $DB->get_record_sql($sql, ['courseid' => $courseid], IGNORE_MULTIPLE);
    if (!$quiz) {
        return null;
    }

    $changed = false;
    if ((int)$quiz->attempts !== 3) {
        $quiz->attempts = 3;
        $changed = true;
    }
    if ((int)$quiz->grademethod !== 1) {
        // The best of the three attempts determines whether the student passed.
        $quiz->grademethod = 1;
        $changed = true;
    }
    if ($changed) {
        $quiz->timemodified = time();
        $DB->update_record('quiz', $quiz);
        require_once(__DIR__ . '/../../mod/quiz/lib.php');
        quiz_grade_item_update($quiz);
    }

    $gradeitem = $DB->get_record('grade_items', [
        'courseid' => $courseid,
        'itemmodule' => 'quiz',
        'iteminstance' => $quiz->id,
    ]);
    $passinggrade = (float)$quiz->grade * 0.7;
    if ($gradeitem && abs((float)$gradeitem->gradepass - $passinggrade) > 0.00001) {
        $gradeitem->gradepass = $passinggrade;
        $gradeitem->timemodified = time();
        $DB->update_record('grade_items', $gradeitem);
    }

    return $quiz;
}

/**
 * Returns assessment availability and result for a student.
 */
function local_isgcorp_get_course_assessment_state(\stdClass $course, int $userid): array {
    global $DB;

    $summary = local_isgcorp_get_manual_course_progress_summary($course, $userid);
    $lessonscomplete = $summary !== null && $summary['total'] > 0 && $summary['completed'] === $summary['total'];
    $quiz = local_isgcorp_get_course_assessment((int)$course->id);
    if (!$quiz) {
        return [
            'quiz' => null,
            'lessonscomplete' => $lessonscomplete,
            'questioncount' => 0,
            'configured' => false,
            'attemptsused' => 0,
            'attemptsremaining' => 3,
            'grade' => null,
            'percentage' => null,
            'passed' => false,
            'available' => false,
        ];
    }

    $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
    $attemptsused = $DB->count_records_select(
        'quiz_attempts',
        'quiz = :quizid AND userid = :userid AND preview = 0',
        ['quizid' => $quiz->id, 'userid' => $userid]
    );
    $grade = $DB->get_field('quiz_grades', 'grade', ['quiz' => $quiz->id, 'userid' => $userid]);
    $grade = $grade === false ? null : (float)$grade;
    $configured = $questioncount > 0;
    $percentage = $grade !== null && (float)$quiz->grade > 0
        ? ($grade / (float)$quiz->grade) * 100
        : null;
    $passed = $percentage !== null && $percentage >= 70.0;

    return [
        'quiz' => $quiz,
        'lessonscomplete' => $lessonscomplete,
        'questioncount' => $questioncount,
        'configured' => $configured,
        'attemptsused' => $attemptsused,
        'attemptsremaining' => max(0, 3 - $attemptsused),
        'grade' => $grade,
        'percentage' => $percentage,
        'passed' => $passed,
        'available' => $lessonscomplete && $configured && !$passed && $attemptsused < 3,
    ];
}

/**
 * Creates a final assessment with the ISG defaults and returns its course module.
 */
function local_isgcorp_create_course_assessment(int $courseid, string $name): \stdClass {
    global $CFG, $DB;

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    if (local_isgcorp_get_course_assessment($courseid)) {
        throw new moodle_exception('assessmentalreadyexists', 'local_isgcorp');
    }

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/course/modlib.php');
    require_once($CFG->dirroot . '/mod/quiz/lib.php');

    $section = course_create_section($course);
    course_update_section($course, $section, ['name' => get_string('courseassessment', 'local_isgcorp')]);
    [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'quiz', (int)$section->section);

    $moduleinfo->name = clean_param($name, PARAM_TEXT);
    // add_moduleinfo() le o idnumber da atividade; sem ele o PHP avisa "Undefined property".
    $moduleinfo->cmidnumber = '';
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 0;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->attempts = 3;
    $moduleinfo->attemptonlast = 0;
    $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
    $moduleinfo->grade = 10;
    $moduleinfo->sumgrades = 0;
    $moduleinfo->decimalpoints = 1;
    $moduleinfo->questiondecimalpoints = -1;
    $moduleinfo->questionsperpage = 1;
    $moduleinfo->navmethod = QUIZ_NAVMETHOD_FREE;
    $moduleinfo->shuffleanswers = 1;
    $moduleinfo->quizpassword = '';
    $moduleinfo->subnet = '';
    $moduleinfo->browsersecurity = '';
    $moduleinfo->delay1 = 0;
    $moduleinfo->delay2 = 0;
    $moduleinfo->showuserpicture = 0;
    $moduleinfo->showblocks = 0;

    foreach (['during', 'immediately', 'open', 'closed'] as $period) {
        foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
                'rightanswer', 'overallfeedback'] as $reviewoption) {
            $moduleinfo->{$reviewoption . $period} = $period === 'during' && $reviewoption === 'overallfeedback' ? 0 : 1;
        }
    }

    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    local_isgcorp_get_course_assessment($courseid);

    return get_coursemodule_from_id('quiz', (int)$moduleinfo->coursemodule, $courseid, false, MUST_EXIST);
}

/**
 * Indica se o usuario concluiu todos os modulos (cursos) de uma trilha.
 *
 * Um modulo conta como concluido quando todas as aulas foram vistas e,
 * se ele tiver avaliacao, o aluno foi aprovado nela.
 *
 * @param int $trilhaid
 * @param int $userid
 * @return bool
 */
function local_isgcorp_is_trilha_complete(int $trilhaid, int $userid): bool {
    $courses = local_isgcorp_get_trilha_courses($trilhaid);
    if (empty($courses)) {
        return false;
    }

    foreach ($courses as $course) {
        if ((local_isgcorp_get_course_progress_data($course, $userid)['percent'] ?? 0) < 100) {
            return false;
        }
    }

    return true;
}

/**
 * Proximo modulo da trilha que o usuario ainda nao concluiu.
 *
 * @param int $trilhaid
 * @param int $userid
 * @param int $currentcourseid Modulo atual, ignorado na busca.
 * @return stdClass|null
 */
function local_isgcorp_get_next_trilha_course(int $trilhaid, int $userid, int $currentcourseid = 0): ?\stdClass {
    foreach (local_isgcorp_get_trilha_courses($trilhaid) as $course) {
        if ((int) $course->id === $currentcourseid) {
            continue;
        }
        if ((local_isgcorp_get_course_progress_data($course, $userid)['percent'] ?? 0) < 100) {
            return $course;
        }
    }

    return null;
}

/**
 * Retorna o certificado da trilha, emitindo-o se o usuario ja concluiu
 * todos os modulos.
 *
 * @param int $trilhaid
 * @param int $userid
 * @return stdClass
 */
function local_isgcorp_get_or_create_trilha_certificate(int $trilhaid, int $userid): \stdClass {
    global $DB;

    $conditions = ['userid' => $userid, 'trilhaid' => $trilhaid];
    $issue = $DB->get_record('local_isgcorp_trilha_cert', $conditions);
    if ($issue) {
        return $issue;
    }

    if (!local_isgcorp_is_trilha_complete($trilhaid, $userid)) {
        throw new moodle_exception('certificateincomplete', 'local_isgcorp');
    }

    do {
        $code = strtoupper(random_string(12));
    } while ($DB->record_exists('local_isgcorp_trilha_cert', ['code' => $code]));

    $issue = (object) $conditions;
    $issue->code = $code;
    $issue->timecreated = time();
    $issue->id = $DB->insert_record('local_isgcorp_trilha_cert', $issue);

    return $issue;
}

/**
 * Emite os certificados de trilha ja conquistados pelo usuario e ainda
 * nao registrados.
 *
 * @param int $userid
 * @return void
 */
function local_isgcorp_sync_user_certificates(int $userid): void {
    global $DB;

    foreach (local_isgcorp_get_user_trilhas($userid) as $trilha) {
        if ($DB->record_exists('local_isgcorp_trilha_cert', ['userid' => $userid, 'trilhaid' => $trilha->id])) {
            continue;
        }
        if (local_isgcorp_is_trilha_complete((int) $trilha->id, $userid)) {
            local_isgcorp_get_or_create_trilha_certificate((int) $trilha->id, $userid);
        }
    }
}

/**
 * Lista as trilhas que o usuario pode cursar.
 *
 * Gestores enxergam o catalogo completo. Para alunos, todos os cursos da
 * trilha precisam estar visiveis e com matricula ativa, evitando apresentar
 * uma jornada que terminaria em uma tela de acesso bloqueado.
 *
 * @param int $userid
 * @param bool $onlyvisible
 * @return array
 */
function local_isgcorp_get_user_trilhas(int $userid, bool $onlyvisible = true): array {
    $trilhas = local_isgcorp_get_trilhas($onlyvisible);
    $systemcontext = \context_system::instance();

    if (is_siteadmin($userid) || has_capability('local/isgcorp:manage', $systemcontext, $userid)) {
        return $trilhas;
    }

    return array_filter($trilhas, function($trilha) use ($userid) {
        $courses = local_isgcorp_get_trilha_courses((int) $trilha->id);
        if (empty($courses)) {
            return false;
        }

        foreach ($courses as $course) {
            if (empty($course->visible)
                    || !is_enrolled(\context_course::instance((int) $course->id), $userid, '', true)) {
                return false;
            }
        }

        return true;
    });
}

/**
 * Verifica se uma trilha esta disponivel para o usuario.
 *
 * @param int $trilhaid
 * @param int $userid
 * @return bool
 */
function local_isgcorp_user_can_access_trilha(int $trilhaid, int $userid): bool {
    return isset(local_isgcorp_get_user_trilhas($userid, true)[$trilhaid]);
}

/** idnumber da coorte com todos os colaboradores, inscrita nos cursos das trilhas. */
const LOCAL_ISGCORP_COHORT_IDNUMBER = 'isg_colaboradores';

/**
 * Coorte "Colaboradores ISG" (contexto de sistema).
 *
 * @param bool $create Cria a coorte se ainda nao existir.
 * @return \stdClass|null
 */
function local_isgcorp_get_collaborators_cohort(bool $create = false): ?\stdClass {
    global $CFG, $DB;

    $cohort = $DB->get_record('cohort', ['idnumber' => LOCAL_ISGCORP_COHORT_IDNUMBER]);
    if ($cohort || !$create) {
        return $cohort ?: null;
    }

    require_once($CFG->dirroot . '/cohort/lib.php');
    $cohort = (object) [
        'contextid' => \context_system::instance()->id,
        'name' => 'Colaboradores ISG',
        'idnumber' => LOCAL_ISGCORP_COHORT_IDNUMBER,
        'description' => 'Todos os colaboradores. Quem esta nesta coorte e inscrito nos cursos das trilhas.',
        'descriptionformat' => FORMAT_HTML,
        'visible' => 1,
        'component' => '',
    ];
    $cohort->id = cohort_add_cohort($cohort);

    return $cohort;
}

/**
 * Coloca o usuario na coorte dos colaboradores (ignora visitante, excluidos e administradores).
 *
 * @param int $userid
 * @return bool true se o usuario foi adicionado agora.
 */
function local_isgcorp_add_to_collaborators_cohort(int $userid): bool {
    global $CFG, $DB;

    $cohort = local_isgcorp_get_collaborators_cohort();
    $user = $DB->get_record('user', ['id' => $userid], 'id, deleted');
    if (!$cohort || !$user || $user->deleted || isguestuser($userid) || is_siteadmin($userid)) {
        return false;
    }
    if ($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid])) {
        return false;
    }

    require_once($CFG->dirroot . '/cohort/lib.php');
    cohort_add_member($cohort->id, $userid);

    return true;
}

/**
 * Garante que cada curso das trilhas tem a sincronizacao com a coorte dos
 * colaboradores (papel Estudante). Os membros da coorte sao inscritos na hora.
 *
 * @return int[] ids dos cursos que ganharam o metodo agora.
 */
function local_isgcorp_sync_trilha_cohort_enrolments(): array {
    global $DB;

    $cohort = local_isgcorp_get_collaborators_cohort();
    $plugin = enrol_get_plugin('cohort');
    $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
    if (!$cohort || !$plugin || !$studentroleid || !enrol_is_enabled('cohort')) {
        return [];
    }

    $added = [];
    $courseids = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {local_isgcorp_trilha_course}');
    foreach ($courseids as $courseid) {
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || $DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'cohort',
                'customint1' => $cohort->id])) {
            continue;
        }
        $plugin->add_instance($course, [
            'customint1' => $cohort->id,
            'roleid' => $studentroleid,
            'customint2' => 0,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);
        $added[] = (int) $courseid;
    }

    return $added;
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

    return $DB->get_record_sql($sql, ['courseid' => $courseid], IGNORE_MULTIPLE) ?: null;
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

    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

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
        ? trim(format_string($sectioninfo->name ?? '', true, ['context' => $coursecontext, 'escape' => false]))
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

            // The final quiz is an assessment, not a lesson. Keeping it out avoids circular progress.
            if ($candidatecm->modname === 'quiz') {
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

    // Completing the lessons unlocks the assessment; only a passing grade completes the course.
    if ($manualsummary !== null && $percent >= 100) {
        $assessmentstate = local_isgcorp_get_course_assessment_state($course, $userid);
        if ($assessmentstate['quiz'] && !$assessmentstate['passed']) {
            $percent = 99;
        }
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

/**
 * Loads the shared confirmation modal used by destructive admin actions.
 */
function local_isgcorp_require_delete_confirmation(): void {
    global $PAGE;

    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $PAGE->requires->js_amd_inline(<<<'JS'
require(['core/notification'], function(Notification) {
    document.addEventListener('click', function(event) {
        var trigger = event.target.closest('[data-isg-delete]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        Notification.deleteCancel(
            trigger.dataset.title,
            trigger.dataset.message,
            trigger.dataset.confirm,
            function() {
                var form = document.getElementById(trigger.dataset.formId);
                if (form) {
                    form.submit();
                }
            },
            function() {},
            {triggerElement: trigger}
        );
    });
});
JS
    );
}

/**
 * Renders a delete button and its protected POST form.
 */
function local_isgcorp_delete_action(string $action, array $params, string $message, string $formid): string {
    local_isgcorp_require_delete_confirmation();

    $form = html_writer::start_tag('form', [
        'id' => $formid,
        'method' => 'post',
        'action' => (new moodle_url($action))->out(false),
        'class' => 'd-none',
    ]);
    foreach (['sesskey' => sesskey()] + $params as $name => $value) {
        $form .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $name,
            'value' => $value,
        ]);
    }
    $form .= html_writer::end_tag('form');

    $button = html_writer::tag('button', get_string('delete'), [
        'type' => 'button',
        'class' => 'btn btn-link text-danger p-0',
        'data-isg-delete' => '1',
        'data-form-id' => $formid,
        'data-title' => get_string('confirmdeleteheading', 'local_isgcorp'),
        'data-message' => $message,
        'data-confirm' => get_string('delete'),
    ]);

    return $button . $form;
}

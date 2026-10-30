<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Configura a coorte "Colaboradores ISG": cria a coorte, ativa a sincronizacao
 * de coorte, liga a coorte a todos os cursos das trilhas (papel Estudante) e
 * coloca nela os usuarios ja cadastrados (menos visitante e administradores).
 * Pode rodar de novo: o que ja existe e mantido.
 *
 * Uso: sudo -u www-data php local/isgcorp/cli/setup_colaboradores.php [--run]
 * Sem --run so mostra o que seria feito.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/isgcorp/lib.php');

[$options] = cli_get_params(['run' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Uso: php local/isgcorp/cli/setup_colaboradores.php [--run]');
    exit(0);
}
$run = !empty($options['run']);
\core\session\manager::set_user(get_admin());

$cohort = local_isgcorp_get_collaborators_cohort();
$admins = array_map('intval', explode(',', $CFG->siteadmins));
[$notin, $params] = $DB->get_in_or_equal(array_merge($admins, [(int) $CFG->siteguest]), SQL_PARAMS_NAMED, 'u', false);
$userids = $DB->get_fieldset_sql("SELECT id FROM {user} WHERE deleted = 0 AND id $notin ORDER BY id", $params);
$members = $cohort ? $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', [$cohort->id]) : [];
$missing = array_diff($userids, $members);

$courses = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.shortname, c.fullname
       FROM {local_isgcorp_trilha_course} tc
       JOIN {course} c ON c.id = tc.courseid
   ORDER BY c.id");
$linked = $cohort ? $DB->get_fieldset_select('enrol', 'courseid', "enrol = 'cohort' AND customint1 = ?", [$cohort->id]) : [];

cli_writeln('Coorte "Colaboradores ISG": ' . ($cohort ? "ja existe (id {$cohort->id})" : 'sera criada'));
cli_writeln('Sincronizacao de coorte: ' . (enrol_is_enabled('cohort') ? 'ja ativa' : 'sera ativada'));
cli_writeln('Usuarios a adicionar na coorte: ' . count($missing) . ' (de ' . count($userids)
    . ' cadastrados; administradores e visitante ficam de fora)');
cli_writeln('Cursos das trilhas: ' . count($courses));
foreach ($courses as $course) {
    cli_writeln('   ' . (in_array($course->id, $linked) ? 'ja ligado ' : 'sera ligado') . "  {$course->shortname} - {$course->fullname}");
}

if (!$run) {
    cli_writeln("\n(simulacao: rode com --run para aplicar)");
    exit(0);
}

$cohort = local_isgcorp_get_collaborators_cohort(true);

if (!enrol_is_enabled('cohort')) {
    $enabled = array_filter(explode(',', $CFG->enrol_plugins_enabled));
    $enabled[] = 'cohort';
    set_config('enrol_plugins_enabled', implode(',', array_unique($enabled)));
    core_plugin_manager::reset_caches();
    cli_writeln('Sincronizacao de coorte ativada.');
}

// Membros primeiro: ao ligar cada curso, a sincronizacao ja inscreve todos de uma vez.
$added = 0;
foreach ($missing as $userid) {
    $added += local_isgcorp_add_to_collaborators_cohort((int) $userid) ? 1 : 0;
}
cli_writeln("Usuarios adicionados na coorte: $added");

$newcourses = local_isgcorp_sync_trilha_cohort_enrolments();
cli_writeln('Cursos ligados agora: ' . count($newcourses));

// Confere: quantos colaboradores ja enxergam cada trilha.
$total = $DB->count_records('cohort_members', ['cohortid' => $cohort->id]);
foreach (local_isgcorp_get_trilhas(true) as $trilha) {
    $trilhacourses = local_isgcorp_get_trilha_courses((int) $trilha->id);
    $ok = 0;
    foreach ($DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', [$cohort->id]) as $userid) {
        $ok += isset(local_isgcorp_get_user_trilhas((int) $userid, true)[$trilha->id]) ? 1 : 0;
    }
    cli_writeln("Trilha {$trilha->name} (" . count($trilhacourses) . " cursos): $ok de $total colaboradores com acesso");
}

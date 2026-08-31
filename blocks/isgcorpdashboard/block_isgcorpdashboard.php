<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Painel unificado da Universidade Corporativa ISG.
 *
 * Substitui os blocos separados (isgcorphero, isgcorpcourses,
 * isgcorpevents) por um único bloco que desenha a página inteira
 * em grade, do jeito que o protótipo HTML aprovado definiu.
 *
 * Motivo da consolidação: a página "My Moodle" empilha blocos um
 * embaixo do outro, cada um em sua própria caixa com borda — não
 * existe grade/colunas nativas entre blocos separados. Um bloco só
 * nos dá controle total do HTML/CSS interno.
 *
 * @package    block_isgcorpdashboard
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_isgcorpdashboard extends block_base {

    const MAXCOURSES = 4;
    const MAXTRILHAS = 5;
    const MAXEVENTS = 3;
    const LOOKAHEADDAYS = 60;

    public function init() {
        $this->title = get_string('pluginname', 'block_isgcorpdashboard');
    }

    public function applicable_formats() {
        return ['my' => true];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $USER, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->ensure_local_isgcorp_loaded();

        $this->content = new stdClass();
        $this->content->footer = '';

        $html = '<div class="isg-dashboard">';
        $html .= $this->render_hero();
        $html .= $this->render_courses();
        $html .= $this->render_trilhas();
        $html .= $this->render_stats();
        $html .= $this->render_progress_and_events();
        $html .= '</div>';

        $this->content->text = $html;

        return $this->content;
    }

    /**
     * Carrega o lib.php do plugin local quando ele estiver disponivel.
     *
     * O bloco usa varias funcoes procedurais do local_isgcorp para
     * montar URLs e progresso. Sem esse require explicito, o bloco
     * pode cair de forma intermitente no fallback padrao do Moodle.
     *
     * @return bool
     */
    protected function ensure_local_isgcorp_loaded(): bool {
        global $CFG;

        if (function_exists('local_isgcorp_get_trilhas')) {
            return true;
        }

        $libpath = $CFG->dirroot . '/local/isgcorp/lib.php';
        if (!is_readable($libpath)) {
            return false;
        }

        require_once($libpath);

        return function_exists('local_isgcorp_get_trilhas');
    }

    /**
     * Banner de boas-vindas.
     */
    protected function render_hero(): string {
        $destinationurl = $this->ensure_local_isgcorp_loaded()
            ? new moodle_url('/local/isgcorp/index.php')
            : new moodle_url('/course/index.php');

        $html = '<div class="isg-hero">';
        $html .=     '<h1>' . get_string('welcometitle', 'block_isgcorpdashboard') . '</h1>';
        $html .=     '<p>' . get_string('welcomesubtitle', 'block_isgcorpdashboard') . '</p>';
        $html .=     '<a class="isg-hero-cta" href="' . $destinationurl->out() . '">';
        $html .=         get_string('ctatext', 'block_isgcorpdashboard') . ' &rarr;';
        $html .=     '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * "Continue aprendendo" — cursos matriculados com progresso.
     */
    protected function render_courses(): string {
        global $USER;

        $courses = enrol_get_users_courses(
            $USER->id,
            true,
            ['id', 'fullname', 'visible'],
            'visible DESC'
        );
        $courses = array_slice($courses, 0, self::MAXCOURSES, true);

        $html = '<section class="isg-section">';
        $html .= '<h2 class="isg-section-title">' . get_string('continuelearning', 'block_isgcorpdashboard') . '</h2>';

        if (empty($courses)) {
            $html .= '<div class="isg-empty">' . get_string('emptycourses', 'block_isgcorpdashboard') . '</div>';
            $html .= '</section>';
            return $html;
        }

        $html .= '<div class="isg-course-grid">';

        foreach ($courses as $course) {
            $progressdata = $this->resolve_course_progress_data($course, (int) $USER->id);
            $percent = $progressdata['percent'];
            $statuslabel = $progressdata['statuslabel'];
            $barclass = $this->resolve_progress_bar_class($progressdata['statusclass']);
            $courseurl = $this->resolve_course_target_url($course);
            $barwidth = $percent ?? 0;

            $html .= '<a class="isg-course-card" href="' . $courseurl->out() . '">';
            $html .=     '<span class="isg-course-status">' . $statuslabel . '</span>';
            $html .=     '<span class="isg-course-title">' . format_string($course->fullname) . '</span>';
            $html .=     '<div class="isg-course-progress-track">';
            $html .=         '<div class="isg-course-progress-fill ' . $barclass . '" style="width:' . $barwidth . '%"></div>';
            $html .=     '</div>';
            if ($percent !== null) {
                $html .= '<span class="isg-course-progress-label ' . $barclass . '-text">' . $percent . '% ' . get_string('concluded', 'block_isgcorpdashboard') . '</span>';
            }
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Resolve a URL que o card de curso deve abrir no dashboard.
     *
     * @param stdClass $course
     * @return moodle_url
     */
    protected function resolve_course_target_url(stdClass $course): moodle_url {
        if ($this->ensure_local_isgcorp_loaded() && function_exists('local_isgcorp_get_course_page_url')) {
            return local_isgcorp_get_course_page_url((int) $course->id);
        }

        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    /**
     * Busca o progresso do curso usando a camada customizada quando existir.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array
     */
    protected function resolve_course_progress_data(stdClass $course, int $userid): array {
        if ($this->ensure_local_isgcorp_loaded() && function_exists('local_isgcorp_get_course_progress_data')) {
            return local_isgcorp_get_course_progress_data($course, $userid);
        }

        $percent = null;
        try {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
        } catch (\Throwable $e) {
            $percent = null;
        }

        if ($percent !== null) {
            $percent = (int) round($percent);
        }

        if ($percent === null) {
            return [
                'percent' => null,
                'statuslabel' => get_string('statusunavailable', 'block_isgcorpdashboard'),
                'statusclass' => 'neutral',
            ];
        }

        if ($percent <= 0) {
            return [
                'percent' => 0,
                'statuslabel' => get_string('statusnotstarted', 'block_isgcorpdashboard'),
                'statusclass' => 'neutral',
            ];
        }

        if ($percent < 100) {
            return [
                'percent' => $percent,
                'statuslabel' => get_string('statusinprogress', 'block_isgcorpdashboard'),
                'statusclass' => 'progress',
            ];
        }

        return [
            'percent' => 100,
            'statuslabel' => get_string('statuscomplete', 'block_isgcorpdashboard'),
            'statusclass' => 'complete',
        ];
    }

    /**
     * Traduz a classe semantica de status para a barra visual.
     *
     * @param string $statusclass
     * @return string
     */
    protected function resolve_progress_bar_class(string $statusclass): string {
        if ($statusclass === 'complete') {
            return 'isg-bar-green';
        }

        if ($statusclass === 'progress') {
            return 'isg-bar-red';
        }

        return 'isg-bar-gray';
    }

    /**
     * "Seu progresso geral" — cards de estatística. Mostramos só
     * o que dá pra calcular com dado real do Moodle: cursos ativos
     * (matrícula) e certificados conquistados (se o plugin
     * mod_customcert estiver instalado). "Horas de aprendizagem" e
     * "Pontuação" ficaram de fora de propósito — não existe
     * rastreamento nativo disso, e prefiro não inventar número.
     */
    protected function render_stats(): string {
        global $USER;

        $courses = enrol_get_users_courses($USER->id, true, ['id']);
        $activecourses = count($courses);

        $html = '<section class="isg-section">';
        $html .= '<h2 class="isg-section-title">' . get_string('overallprogress', 'block_isgcorpdashboard') . '</h2>';
        $html .= '<div class="isg-stat-grid">';

        $html .= '<div class="isg-stat-card">';
        $html .=     '<div class="isg-stat-icon">&#128214;</div>';
        $html .=     '<div>';
        $html .=         '<div class="isg-stat-label">' . get_string('statactivecourses', 'block_isgcorpdashboard') . '</div>';
        $html .=         '<div class="isg-stat-value">' . $activecourses . '</div>';
        $html .=         '<div class="isg-stat-sub">' . get_string('statactivecoursessub', 'block_isgcorpdashboard') . '</div>';
        $html .=     '</div>';
        $html .= '</div>';

        $certcount = $this->get_certificate_count($USER->id);
        if ($certcount !== null) {
            $html .= '<div class="isg-stat-card">';
            $html .=     '<div class="isg-stat-icon">&#127942;</div>';
            $html .=     '<div>';
            $html .=         '<div class="isg-stat-label">' . get_string('statcertificates', 'block_isgcorpdashboard') . '</div>';
            $html .=         '<div class="isg-stat-value">' . $certcount . '</div>';
            $html .=         '<div class="isg-stat-sub">' . get_string('statcertificatessub', 'block_isgcorpdashboard') . '</div>';
            $html .=     '</div>';
            $html .= '</div>';
        }

        $hours = $this->get_learning_hours($USER->id);
        if ($hours !== null) {
            $html .= '<div class="isg-stat-card">';
            $html .=     '<div class="isg-stat-icon">&#128337;</div>';
            $html .=     '<div>';
            $html .=         '<div class="isg-stat-label">' . get_string('stathours', 'block_isgcorpdashboard') . '</div>';
            $html .=         '<div class="isg-stat-value">' . $hours . 'h</div>';
            $html .=         '<div class="isg-stat-sub">' . get_string('stathourssub', 'block_isgcorpdashboard') . '</div>';
            $html .=     '</div>';
            $html .= '</div>';
        }

        $points = $this->get_total_points($USER->id);
        if ($points !== null) {
            $html .= '<div class="isg-stat-card">';
            $html .=     '<div class="isg-stat-icon">&#11088;</div>';
            $html .=     '<div>';
            $html .=         '<div class="isg-stat-label">' . get_string('statpoints', 'block_isgcorpdashboard') . '</div>';
            $html .=         '<div class="isg-stat-value">' . $points . '</div>';
            $html .=         '<div class="isg-stat-sub">' . get_string('statpointssub', 'block_isgcorpdashboard') . '</div>';
            $html .=     '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Conta certificados emitidos pro usuário via mod_customcert,
     * SE esse plugin estiver instalado. Retorna null se não
     * estiver — sinal pra quem chama de "sem essa informação",
     * diferente de "zero certificados".
     */
    protected function get_certificate_count(int $userid): ?int {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('customcert_issues');

        if (!$dbman->table_exists($table)) {
            return null;
        }

        return $DB->count_records('customcert_issues', ['userid' => $userid]);
    }

    /**
     * Estimativa de horas de aprendizagem, calculada a partir dos
     * logs de acesso do Moodle (mdl_logstore_standard_log): soma o
     * tempo entre eventos consecutivos do usuário, descartando
     * intervalos maiores que 20 minutos (assume que o usuário saiu
     * ou ficou inativo nesse meio tempo). É uma ESTIMATIVA, não uma
     * medição exata de tempo de estudo — deixamos isso claro no
     * texto da interface também.
     *
     * Limitado aos últimos 90 dias por performance.
     */
    protected function get_learning_hours(int $userid): ?float {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('logstore_standard_log');

        if (!$dbman->table_exists($table)) {
            return null;
        }

        $since = time() - (90 * DAYSECS);

        $sql = "SELECT timecreated
                  FROM {logstore_standard_log}
                 WHERE userid = :userid AND timecreated > :since
              ORDER BY timecreated ASC";
        $timestamps = $DB->get_fieldset_sql($sql, ['userid' => $userid, 'since' => $since]);

        if (count($timestamps) < 2) {
            return null;
        }

        $gapcap = 20 * MINSECS;
        $totalseconds = 0;

        for ($i = 1; $i < count($timestamps); $i++) {
            $delta = $timestamps[$i] - $timestamps[$i - 1];
            if ($delta > 0 && $delta <= $gapcap) {
                $totalseconds += $delta;
            }
        }

        if ($totalseconds === 0) {
            return null;
        }

        return round($totalseconds / 3600, 1);
    }

    /**
     * Soma das notas finais obtidas pelo usuário em todas as
     * atividades avaliadas de todos os cursos. Isso é uma medida
     * real do gradebook do Moodle — NÃO é "pontuação de
     * gamificação" (o Moodle não tem isso nativamente), por isso o
     * rótulo na interface é claro sobre o que é.
     */
    protected function get_total_points(int $userid): ?float {
        global $DB;

        $sql = "SELECT SUM(gg.finalgrade) AS total
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid
                   AND gi.itemtype = 'mod'
                   AND gg.finalgrade IS NOT NULL";

        $result = $DB->get_record_sql($sql, ['userid' => $userid]);

        if (!$result || $result->total === null) {
            return null;
        }

        return round((float) $result->total, 1);
    }

    /**
     * Painel "Progresso" (anel + métricas) lado a lado com
     * "Próximos eventos", igual ao protótipo aprovado.
     */
    protected function render_progress_and_events(): string {
        $html = '<section class="isg-section">';
        $html .= '<div class="isg-split">';
        $html .= '<div class="isg-panel">' . $this->render_progress_panel() . '</div>';
        $html .= '<div class="isg-panel">' . $this->render_events() . '</div>';
        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Conteúdo do painel "Progresso": anel de conclusão geral +
     * detalhamento por Trilhas e Cursos. "Certificações" e "Horas
     * de estudo" ficaram de fora — sem denominador claro pra
     * calcular uma fração honesta pra elas ainda.
     */
    protected function render_progress_panel(): string {
        global $USER;

        // Cursos: conta quantos dos matriculados têm 100% de conclusão.
        $courses = enrol_get_users_courses($USER->id, true, ['id', 'fullname']);
        $totalcourses = count($courses);
        $completedcourses = 0;
        $coursepercents = [];

        foreach ($courses as $course) {
            $progressdata = $this->resolve_course_progress_data($course, (int) $USER->id);
            $percent = $progressdata['percent'];
            if ($percent !== null) {
                $coursepercents[] = $percent;
                if ($percent >= 100) {
                    $completedcourses++;
                }
            }
        }

        // Trilhas: conta quantas das visíveis estão 100% concluídas
        // (média de conclusão dos cursos que a compõem).
        $totaltrilhas = 0;
        $completedtrilhas = 0;
        if ($this->ensure_local_isgcorp_loaded()) {
            $trilhas = local_isgcorp_get_trilhas(true);
            $totaltrilhas = count($trilhas);
            foreach ($trilhas as $trilha) {
                $tprogress = local_isgcorp_get_trilha_progress($trilha->id, $USER->id);
                if ($tprogress !== null && $tprogress >= 100) {
                    $completedtrilhas++;
                }
            }
        }

        // Conclusão geral = média simples dos percentuais de curso
        // com rastreamento disponível. Sem dado nenhum, mostramos
        // um estado vazio em vez de inventar um número.
        $overallpercent = null;
        if (!empty($coursepercents)) {
            $overallpercent = (int) round(array_sum($coursepercents) / count($coursepercents));
        }

        $html = '<div class="isg-panel-head">';
        $html .=     '<h3>' . get_string('progresspaneltitle', 'block_isgcorpdashboard') . '</h3>';
        $html .= '</div>';

        if ($overallpercent === null && $totaltrilhas === 0) {
            $html .= '<div class="isg-empty">' . get_string('emptyprogress', 'block_isgcorpdashboard') . '</div>';
            return $html;
        }

        $donutpercent = $overallpercent ?? 0;

        $html .= '<div class="isg-donut-row">';
        $html .=     '<div class="isg-donut" style="background: conic-gradient(#770104 0% ' . $donutpercent . '%, #e9e7e6 ' . $donutpercent . '% 100%);">';
        $html .=         '<div class="isg-donut-inner">';
        if ($overallpercent !== null) {
            $html .=         '<div class="isg-donut-pct">' . $overallpercent . '%</div>';
        } else {
            $html .=         '<div class="isg-donut-pct">&mdash;</div>';
        }
        $html .=         '<div class="isg-donut-label">' . get_string('overallcompletion', 'block_isgcorpdashboard') . '</div>';
        $html .=     '</div>';
        $html .= '</div>';

        $html .= '<div class="isg-metric-list">';

        if ($totaltrilhas > 0) {
            $trilhapct = (int) round(($completedtrilhas / $totaltrilhas) * 100);
            $html .= $this->render_metric_row(
                get_string('metrictrilhas', 'block_isgcorpdashboard'),
                $trilhapct,
                get_string('metricoutof', 'block_isgcorpdashboard', (object) ['done' => $completedtrilhas, 'total' => $totaltrilhas])
            );
        }

        if ($totalcourses > 0) {
            $coursepct = (int) round(($completedcourses / $totalcourses) * 100);
            $html .= $this->render_metric_row(
                get_string('metriccursos', 'block_isgcorpdashboard'),
                $coursepct,
                get_string('metricoutof', 'block_isgcorpdashboard', (object) ['done' => $completedcourses, 'total' => $totalcourses])
            );
        }

        $html .= '</div>'; // fecha isg-metric-list
        $html .= '</div>'; // fecha isg-donut-row

        return $html;
    }

    /**
     * Uma linha de métrica dentro do painel de progresso (ex:
     * "Trilhas — 3 de 5 concluídas — 60%").
     */
    protected function render_metric_row(string $label, int $percent, string $sublabel): string {
        $html = '<div class="isg-metric-row">';
        $html .=     '<div class="isg-metric-top"><span>' . $label . '</span><span>' . $percent . '%</span></div>';
        $html .=     '<div class="isg-course-progress-track">';
        $html .=         '<div class="isg-course-progress-fill isg-bar-red" style="width:' . $percent . '%"></div>';
        $html .=     '</div>';
        $html .=     '<div class="isg-stat-sub" style="margin-top:4px">' . $sublabel . '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * "Próximos eventos" — eventos do calendário do usuário.
     */
    protected function render_events(): string {
        global $USER;

        $html = '<section class="isg-section">';
        $html .= '<div class="isg-panel-head">';
        $html .=     '<h3>' . get_string('upcomingevents', 'block_isgcorpdashboard') . '</h3>';
        $calurl = new moodle_url('/calendar/view.php');
        $html .=     '<a class="isg-link-more" href="' . $calurl->out() . '">' . get_string('viewcalendar', 'block_isgcorpdashboard') . '</a>';
        $html .= '</div>';

        $events = [];
        try {
            $courses = enrol_get_users_courses($USER->id, true, ['id']);
            $courseids = array_keys($courses);
            $courseids[] = SITEID;

            $tstart = time();
            $tend = time() + (self::LOOKAHEADDAYS * DAYSECS);

            $rawevents = calendar_get_events($tstart, $tend, $USER->id, false, $courseids, true, true);
            $rawevents = is_array($rawevents) ? array_values($rawevents) : [];
            usort($rawevents, function($a, $b) {
                return $a->timestart <=> $b->timestart;
            });
            $events = array_slice($rawevents, 0, self::MAXEVENTS);
        } catch (\Throwable $e) {
            $events = [];
        }

        if (empty($events)) {
            $html .= '<div class="isg-empty">' . get_string('emptyevents', 'block_isgcorpdashboard') . '</div>';
            $html .= '</section>';
            return $html;
        }

        foreach ($events as $event) {
            $day = userdate($event->timestart, '%d');
            $mon = userdate($event->timestart, '%b');
            $time = userdate($event->timestart, '%H:%M');
            $name = format_string($event->name);
            $location = !empty($event->location) ? format_string($event->location) : '';

            $html .= '<div class="isg-event-row">';
            $html .=     '<div class="isg-event-date">' . strtoupper($mon) . '<span class="isg-event-day">' . $day . '</span></div>';
            $html .=     '<div>';
            $html .=         '<div class="isg-event-time">' . $time . '</div>';
            $html .=         '<div class="isg-event-title">' . $name . '</div>';
            if ($location !== '') {
                $html .= '<div class="isg-event-place">' . $location . '</div>';
            }
            $html .=     '</div>';
            $html .= '</div>';
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * "Trilhas recomendadas para você" — reaproveita os dados do
     * plugin local_isgcorp. Se esse plugin não estiver instalado
     * por qualquer motivo, a seção simplesmente não aparece, em vez
     * de quebrar a página inteira.
     */
    protected function render_trilhas(): string {
        if (!$this->ensure_local_isgcorp_loaded()) {
            return '';
        }

        $trilhas = local_isgcorp_get_trilhas(true);
        $trilhas = array_slice($trilhas, 0, self::MAXTRILHAS, true);

        $html = '<section class="isg-section">';
        $html .= '<h2 class="isg-section-title">' . get_string('recommendedtrilhas', 'block_isgcorpdashboard') . '</h2>';

        if (empty($trilhas)) {
            $html .= '<div class="isg-empty">' . get_string('emptytrilhas', 'block_isgcorpdashboard') . '</div>';
            $html .= '</section>';
            return $html;
        }

        $html .= '<div class="isg-trilha-grid isg-trilha-grid-compact">';

        foreach ($trilhas as $trilha) {
            $viewurl = new moodle_url('/local/isgcorp/view.php', ['id' => $trilha->id]);
            $levelstring = get_string('level' . $trilha->level, 'local_isgcorp');
            $coverurl = local_isgcorp_get_trilha_cover_url($trilha->id);

            $html .= '<a class="isg-trilha-card" href="' . $viewurl->out() . '">';
            if ($coverurl) {
                $html .= '<div class="isg-trilha-cover" style="background-image:url(' . $coverurl->out() . ')"></div>';
            } else {
                $html .= '<div class="isg-trilha-cover isg-trilha-cover-' . (($trilha->id % 5) + 1) . '"></div>';
            }
            $html .=     '<div class="isg-trilha-body">';
            $html .=         '<div class="isg-trilha-title">' . format_string($trilha->name) . '</div>';
            $html .=         '<div class="isg-trilha-desc">' . format_text($trilha->description, $trilha->descriptionformat) . '</div>';
            $html .=         '<div class="isg-trilha-meta">';
            $html .=             '<span>' . $levelstring . '</span>';
            $html .=             '<span>&#9201; ' . $trilha->estimatedhours . 'h</span>';
            $html .=         '</div>';
            $html .=     '</div>';
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }
}

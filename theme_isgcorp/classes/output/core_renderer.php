<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Renderer customizado do theme_isgcorp.
 *
 * Estratégia: NÃO sobrescrevemos os layouts do Boost (drawers.php),
 * que são complexos e cheios de JS de abrir/fechar gaveta. Em vez
 * disso, "grudamos" nossa sidebar fixa em toda página usando o
 * hook padrão standard_top_of_body_html(), que o Moodle já chama
 * automaticamente logo após a tag <body>. Isso é bem mais seguro:
 * se algo der errado aqui, no pior caso a sidebar não aparece —
 * o resto do Moodle continua funcionando normalmente.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_isgcorp\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Chamado automaticamente pelo Moodle logo após a tag <body>
     * em toda página do site.
     *
     * @return string
     */
    public function standard_top_of_body_html() {
        $html = parent::standard_top_of_body_html();
        $html .= $this->render_isg_sidebar();
        return $html;
    }

    /**
     * Monta o contexto e renderiza o template da sidebar.
     *
     * @return string
     */
    protected function render_isg_sidebar() {
        global $CFG, $PAGE;

        // Detecta em qual item de menu estamos, pra marcar como "ativo".
        $currenturl = $PAGE->url ? $PAGE->url->out_as_local_url(false) : '';
        $isdashboard = (strpos($currenturl, '/my/') !== false) || (strpos($currenturl, '/index.php') !== false && $currenturl !== '/course/index.php');
        $istrilhas = (strpos($currenturl, '/local/isgcorp/') !== false);

        $icons = $this->get_isg_sidebar_icons();

        $context = [
            'wwwroot' => $CFG->wwwroot,
            'sitename' => format_string($PAGE->course->fullname ?? get_string('pluginname', 'theme_isgcorp')),
            'items' => [
                [
                    'label' => get_string('navinicio', 'theme_isgcorp'),
                    'url' => $CFG->wwwroot . '/my/',
                    'icon' => $icons['home'],
                    'active' => $isdashboard,
                ],
                [
                    'label' => get_string('navtrilhas', 'theme_isgcorp'),
                    'url' => $CFG->wwwroot . '/local/isgcorp/index.php',
                    'icon' => $icons['trilhas'],
                    'active' => $istrilhas,
                ],
                [
                    'label' => get_string('navcertificados', 'theme_isgcorp'),
                    // Placeholder até decidirmos a página de certificados.
                    'url' => '#',
                    'icon' => $icons['certificados'],
                    'active' => false,
                ],
            ],
        ];

        // Como escondemos o menu "Home / Dashboard / My courses /
        // Site administration" do Boost no topbar (ver post.scss),
        // precisamos deixar um jeito rápido de admins chegarem no
        // Site administration — só aparece pra quem tem permissão.
        if (is_siteadmin()) {
            $context['items'][] = [
                'label' => get_string('navadmin', 'theme_isgcorp'),
                'url' => $CFG->wwwroot . '/admin/search.php',
                'icon' => $icons['admin'],
                'active' => (strpos($currenturl, '/admin/') !== false),
            ];
        }

        return $this->render_from_template('theme_isgcorp/sidebar', $context);
    }

    /**
     * Ícones SVG de linha usados na sidebar. Ficam num método à
     * parte só pra não poluir render_isg_sidebar() com um bloco
     * grande de markup.
     *
     * @return array
     */
    protected function get_isg_sidebar_icons(): array {
        $common = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        return [
            'home' => '<svg ' . $common . '><path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V9.5Z"/></svg>',
            'trilhas' => '<svg ' . $common . '><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
            'certificados' => '<svg ' . $common . '><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/></svg>',
            'admin' => '<svg ' . $common . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82A1.65 1.65 0 0 0 3 12.09H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>',
        ];
    }
}

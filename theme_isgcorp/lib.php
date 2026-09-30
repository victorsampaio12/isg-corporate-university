<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Funcoes de apoio do theme_isgcorp.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Indica se o usuario precisa das ferramentas administrativas do tema.
 *
 * @return bool
 */
function theme_isgcorp_is_privileged_user(): bool {
    return is_siteadmin()
        || has_capability('local/isgcorp:manage', \context_system::instance());
}

/**
 * Monta o SCSS completo do tema.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_isgcorp_get_main_scss_content($theme) {
    global $CFG;

    $pre = file_get_contents($CFG->dirroot . '/theme/isgcorp/scss/pre.scss');
    $boostpresetfile = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    $boost = file_get_contents($boostpresetfile);
    $post = file_get_contents($CFG->dirroot . '/theme/isgcorp/scss/post.scss');

    return $pre . "\n" . $boost . "\n" . $post;
}

/**
 * Icones SVG usados na navegacao customizada.
 *
 * @return array
 */
function theme_isgcorp_get_navigation_icons(): array {
    $common = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    return [
        'home' => '<svg ' . $common . '><path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V9.5Z"/></svg>',
        'trilhas' => '<svg ' . $common . '><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
        'certificados' => '<svg ' . $common . '><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 21l5-3 5 3-1.5-8.5"/></svg>',
        'admin' => '<svg ' . $common . '><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82A1.65 1.65 0 0 0 3 12.09H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>',
    ];
}

/**
 * Normaliza uma URL local para comparacoes de menu.
 *
 * @param string $currenturl
 * @return string
 */
function theme_isgcorp_get_navigation_path(string $currenturl): string {
    $path = parse_url($currenturl, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        return $path;
    }

    return $currenturl !== '' ? $currenturl : '/';
}

/**
 * Itens compartilhados entre a sidebar desktop e o drawer mobile.
 *
 * @param string $currenturl
 * @return array
 */
function theme_isgcorp_get_navigation_items(string $currenturl = ''): array {
    global $CFG;

    $path = theme_isgcorp_get_navigation_path($currenturl);
    $icons = theme_isgcorp_get_navigation_icons();

    $items = [
        [
            'key' => 'dashboard',
            'label' => get_string('navinicio', 'theme_isgcorp'),
            'url' => $CFG->wwwroot . '/my/',
            'icon' => $icons['home'],
            'active' => (strpos($path, '/my/') === 0) || in_array($path, ['/', '/index.php'], true),
        ],
        [
            'key' => 'trilhas',
            'label' => get_string('navtrilhas', 'theme_isgcorp'),
            'url' => $CFG->wwwroot . '/local/isgcorp/index.php',
            'icon' => $icons['trilhas'],
            'active' => in_array($path, [
                '/local/isgcorp/index.php',
                '/local/isgcorp/view.php',
                '/local/isgcorp/course.php',
                '/local/isgcorp/lesson.php',
            ], true),
        ],
        [
            'key' => 'certificados',
            'label' => get_string('navcertificados', 'theme_isgcorp'),
            'url' => $CFG->wwwroot . '/local/isgcorp/certificates.php',
            'icon' => $icons['certificados'],
            'active' => $path === '/local/isgcorp/certificates.php',
        ],
    ];

    if (is_siteadmin()) {
        $items[] = [
            'key' => 'admin',
            'label' => get_string('navadmin', 'theme_isgcorp'),
            'url' => $CFG->wwwroot . '/admin/search.php',
            'icon' => $icons['admin'],
            'active' => strpos($path, '/admin/') === 0,
        ];
    }

    return $items;
}

/**
 * Estrutura esperada pelo drawer mobile do Boost.
 *
 * @param string $currenturl
 * @return array
 */
function theme_isgcorp_get_mobile_primary_nav(string $currenturl = ''): array {
    $mobileitems = [];

    foreach (theme_isgcorp_get_navigation_items($currenturl) as $item) {
        $mobileitems[] = [
            'text' => '<span class="isg-mobile-nav-link"><span class="isg-sidebar-icon">' . $item['icon']
                . '</span><span>' . s($item['label']) . '</span></span>',
            'url' => $item['url'],
            'isactive' => !empty($item['active']),
            'classes' => ['isg-mobile-nav-item'],
        ];
    }

    return $mobileitems;
}

/**
 * Mantem o drawer direito do Boost apenas para administradores.
 *
 * @return bool
 */
function theme_isgcorp_should_show_blockdrawer(): bool {
    return isloggedin() && !isguestuser() && is_siteadmin();
}

/**
 * Mantem no menu do aluno apenas os atalhos essenciais.
 *
 * @param array $usermenu
 * @return array
 */
function theme_isgcorp_filter_student_user_menu(array $usermenu): array {
    if (theme_isgcorp_is_privileged_user() || empty($usermenu['items'])) {
        return $usermenu;
    }

    $allowedpaths = [
        '/user/profile.php',
        '/user/preferences.php',
        '/login/logout.php',
    ];

    $items = array_filter($usermenu['items'], function($item) use ($allowedpaths) {
        if (!empty($item->submenulink)) {
            return true;
        }
        if (empty($item->url)) {
            return false;
        }

        $url = $item->url instanceof \moodle_url ? $item->url->out_as_local_url(false) : (string) $item->url;
        $path = parse_url($url, PHP_URL_PATH);
        return in_array($path, $allowedpaths, true);
    });

    $usermenu['items'] = array_values($items);
    if (!empty($usermenu['items'])) {
        $lastindex = count($usermenu['items']) - 1;
        $usermenu['items'][$lastindex]->divider = true;
    }

    return $usermenu;
}

/**
 * Esconde o cabecalho nativo do Boost quando a pagina ja tem header
 * customizado proprio.
 *
 * @param moodle_page $page
 * @return bool
 */
function theme_isgcorp_should_hide_full_header(\moodle_page $page): bool {
    $currenturl = $page->url ? $page->url->out_as_local_url(false) : '';
    $path = theme_isgcorp_get_navigation_path($currenturl);

    return in_array($path, [
        '/my/',
        '/my/index.php',
        '/local/isgcorp/index.php',
        '/local/isgcorp/view.php',
        '/local/isgcorp/course.php',
        '/local/isgcorp/lesson.php',
        '/local/isgcorp/certificates.php',
    ], true);
}

/**
 * Esconde a navegacao secundaria do Moodle nas paginas customizadas
 * de curso/aula.
 *
 * @param moodle_page $page
 * @return bool
 */
function theme_isgcorp_should_hide_secondary_navigation(\moodle_page $page): bool {
    $currenturl = $page->url ? $page->url->out_as_local_url(false) : '';
    $path = theme_isgcorp_get_navigation_path($currenturl);

    return in_array($path, ['/local/isgcorp/course.php', '/local/isgcorp/lesson.php'], true);
}

/**
 * Serve arquivos da area de configuracoes do tema.
 */
function theme_isgcorp_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'logo') {
        $theme = theme_config::load('isgcorp');
        return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

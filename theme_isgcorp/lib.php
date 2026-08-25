<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Funções de apoio do theme_isgcorp.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Monta o SCSS completo: primeiro o pré-SCSS (variáveis, para
 * sobrescrever o que o Boost usa), depois o SCSS do Boost em si,
 * e por fim o pós-SCSS (nossos estilos customizados, que podem
 * usar as variáveis do Bootstrap já compiladas).
 *
 * @param theme_config $theme
 * @return string
 */
function theme_isgcorp_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    // 1. Variáveis customizadas (cores da marca ISG) — entram
    //    ANTES do Boost, para que o Bootstrap use nossas cores.
    $pre = file_get_contents($CFG->dirroot . '/theme/isgcorp/scss/pre.scss');

    // 2. O SCSS principal do Boost (não mexemos nele).
    $boostpresetfile = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    $boost = file_get_contents($boostpresetfile);

    // 3. Nossos estilos customizados por cima (sidebar, cards, etc).
    $post = file_get_contents($CFG->dirroot . '/theme/isgcorp/scss/post.scss');

    return $pre . "\n" . $boost . "\n" . $post;
}

/**
 * Serve arquivos da área de configurações do tema (ex: logo
 * customizado enviado pelo admin via settings.php).
 */
function theme_isgcorp_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'logo') {
        $theme = theme_config::load('isgcorp');
        return $theme->setting_file_serve('logo', $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

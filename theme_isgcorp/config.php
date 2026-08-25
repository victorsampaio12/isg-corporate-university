<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Configuration for theme_isgcorp — tema filho do Boost
 * para a Universidade Corporativa ISG.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'isgcorp';

// Herda tudo do Boost por padrão; só sobrescrevemos o necessário.
$THEME->parents = ['boost'];

// Onde procurar por overrides de layout e templates deste tema.
$THEME->layouts = [
    // Por enquanto reaproveita os layouts do Boost.
    // Quando criarmos a sidebar customizada, apontamos
    // 'columns2' (layout padrão logado) para o nosso próprio
    // arquivo em layout/columns2.php.
];

// SCSS: como o tema gera o CSS final.
$THEME->scss = function($theme) {
    return theme_isgcorp_get_main_scss_content($theme);
};

// Permite configurar cor de marca, logo, etc. via settings.php.
$THEME->usefallback = true;
$THEME->doctype = 'html5';

// Reaproveita os ícones/JS do Boost.
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];

// Diz ao Moodle que este tema pode ser usado tanto no desktop quanto
// em telas menores (o Boost já cuida do responsivo via Bootstrap).
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Painel de administração do theme_isgcorp em
 * Site administration > Appearance > Themes > ISG Corp.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings = new theme_boost_admin_settingspage_tabs('themesettingisgcorp', get_string('configtitle', 'theme_isgcorp'));

    // ---- Aba: Geral ----
    $page = new admin_settingpage('theme_isgcorp_general', get_string('generalsettings', 'theme_isgcorp'));

    // Logo da Universidade Corporativa ISG.
    $name = 'theme_isgcorp/logo';
    $title = get_string('logo', 'theme_isgcorp');
    $description = get_string('logo_desc', 'theme_isgcorp');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Cor primária da marca (vermelho ISG por padrão).
    $name = 'theme_isgcorp/brandcolor';
    $title = get_string('brandcolor', 'theme_isgcorp');
    $description = get_string('brandcolor_desc', 'theme_isgcorp');
    $default = '#770104';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version details for theme_isgcorp.
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026082400;        // Formato: YYYYMMDDXX (data de build).
$plugin->requires  = 2024042200;        // Versão mínima do Moodle (4.5).
$plugin->component = 'theme_isgcorp';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// Este tema depende do Boost, o tema pai.
$plugin->dependencies = [
    'theme_boost' => 2024042200,
];

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Funções auxiliares do bloco de dashboard ISG.
 *
 * @package    block_isgcorpdashboard
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Aplica o comportamento esperado da plataforma:
 * login obrigatório e dashboard padrão fixo com o bloco ISG.
 *
 * @return void
 */
function block_isgcorpdashboard_apply_site_defaults(): void {
    global $CFG;

    require_once($CFG->dirroot . '/my/lib.php');

    set_config('forcelogin', 1);
    set_config('forcedefaultmymoodle', 1);

    if (!block_isgcorpdashboard_is_available()) {
        return;
    }

    block_isgcorpdashboard_sync_system_dashboard();
    my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index');
}

/**
 * Confirma se o tipo de bloco já está registrado no Moodle.
 *
 * @return bool
 */
function block_isgcorpdashboard_is_available(): bool {
    global $DB;

    return $DB->record_exists('block', ['name' => 'isgcorpdashboard']);
}

/**
 * Garante que o dashboard padrão do sistema use o bloco unificado.
 *
 * @return void
 */
function block_isgcorpdashboard_sync_system_dashboard(): void {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/my/lib.php');

    $systempage = my_get_page(null, MY_PAGE_PRIVATE);
    if (!$systempage) {
        return;
    }

    $systemcontext = \context_system::instance();
    $instances = $DB->get_records(
        'block_instances',
        [
            'parentcontextid' => $systemcontext->id,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $systempage->id,
        ],
        'defaultweight ASC, id ASC'
    );

    $dashboardinstance = null;
    foreach ($instances as $instance) {
        if ($instance->blockname === 'isgcorpdashboard' && $dashboardinstance === null) {
            $dashboardinstance = $instance;
            continue;
        }

        blocks_delete_instance($instance);
    }

    if ($dashboardinstance) {
        $dashboardinstance->showinsubcontexts = 0;
        $dashboardinstance->requiredbytheme = 0;
        $dashboardinstance->defaultregion = 'content';
        $dashboardinstance->defaultweight = 0;
        $dashboardinstance->timemodified = time();
        $DB->update_record('block_instances', $dashboardinstance);
        \context_block::instance($dashboardinstance->id);
    } else {
        $now = time();
        $dashboardinstance = (object) [
            'blockname' => 'isgcorpdashboard',
            'parentcontextid' => $systemcontext->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $systempage->id,
            'defaultregion' => 'content',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $dashboardinstance->id = $DB->insert_record('block_instances', $dashboardinstance);
        \context_block::instance($dashboardinstance->id);

        if ($block = block_instance('isgcorpdashboard', $dashboardinstance)) {
            $block->instance_create();
        }
    }

    $DB->delete_records('block_positions', [
        'contextid' => $systemcontext->id,
        'pagetype' => 'my-index',
        'subpage' => $systempage->id,
    ]);
}

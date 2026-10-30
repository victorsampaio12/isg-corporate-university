<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Instala (ou atualiza) o pacote de idioma Portugues - Brasil. Sem ele, o
 * site configurado em pt_br mostra os textos do Moodle (e o certificado) em
 * ingles. O servidor precisa acessar download.moodle.org.
 *
 * Uso: sudo -u www-data php local/isgcorp/cli/instalar_ptbr.php
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

\core\session\manager::set_user(get_admin());
core_php_time_limit::raise();

$controller = new \tool_langimport\controller();
$installed = array_keys(get_string_manager()->get_list_of_translations(true));
if (in_array('pt_br', $installed, true)) {
    cli_writeln('pt_br ja instalado; atualizando...');
    $controller->update_all_installed_languages();
} else {
    $controller->install_languagepacks(['pt_br']);
}
foreach ($controller->info as $message) {
    cli_writeln("ok: $message");
}
foreach ($controller->errors as $message) {
    cli_writeln("ERRO: $message");
}
get_string_manager()->reset_caches();
cli_writeln('Idiomas instalados: ' . implode(', ', array_keys(get_string_manager()->get_list_of_translations(true))));

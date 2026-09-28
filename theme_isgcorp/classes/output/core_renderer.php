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
 * Estrategia:
 * - desktop: sidebar fixa injetada via standard_top_of_body_html()
 * - mobile: drawer do Boost reaproveitado com os mesmos links do
 *   tema via layout/drawers.php
 *
 * @package    theme_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_isgcorp\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Adiciona uma classe no body so quando a sidebar customizada
     * realmente vai ser renderizada.
     *
     * @param string|array $additionalclasses
     * @return string
     */
    public function body_attributes($additionalclasses = []) {
        if ($this->should_render_isg_sidebar()) {
            if (!is_array($additionalclasses)) {
                $additionalclasses = array_filter(explode(' ', $additionalclasses));
            }
            $additionalclasses[] = 'has-isg-sidebar';
        }

        return parent::body_attributes($additionalclasses);
    }

    /**
     * Chamado automaticamente pelo Moodle logo apos a tag <body>
     * em toda pagina do site.
     *
     * @return string
     */
    public function standard_top_of_body_html() {
        $html = parent::standard_top_of_body_html();
        if ($this->should_render_isg_sidebar()) {
            $html .= $this->render_isg_sidebar();
        }
        return $html;
    }

    /**
     * A sidebar customizada so faz sentido para usuarios reais ja
     * autenticados. Em login/visitante, deixamos o layout nativo.
     *
     * @return bool
     */
    protected function should_render_isg_sidebar(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * Personaliza o contexto da tela de login com a marca do tema
     * e esconde o bloco de acesso como convidado.
     *
     * @param \core_auth\output\login $form
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $SITE;

        $context = $form->export_for_template($this);
        $context->errorformatted = $this->error_text($context->error);

        $context->logourl = $this->image_url('brand-mark', 'theme_isgcorp')->out(false);
        $context->canloginasguest = false;
        $context->smallscreensonly = false;
        $context->sitename = format_string(
            $SITE->fullname,
            true,
            ['context' => \context_course::instance(SITEID), 'escape' => false]
        );

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Monta o contexto e renderiza o template da sidebar.
     *
     * @return string
     */
    protected function render_isg_sidebar() {
        global $CFG, $PAGE;

        $currenturl = $PAGE->url ? $PAGE->url->out_as_local_url(false) : '';

        $context = [
            'wwwroot' => $CFG->wwwroot,
            'logourl' => $this->image_url('brand-mark', 'theme_isgcorp')->out(),
            'sitename' => format_string($PAGE->course->fullname ?? get_string('pluginname', 'theme_isgcorp')),
            'items' => theme_isgcorp_get_navigation_items($currenturl),
        ];

        return $this->render_from_template('theme_isgcorp/sidebar', $context);
    }
}

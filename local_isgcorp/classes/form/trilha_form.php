<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_isgcorp\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Formulário de criação/edição de uma trilha de aprendizagem.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trilha_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('trilhaname', 'local_isgcorp'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('trilhadescription', 'local_isgcorp'));
        $mform->setType('description_editor', PARAM_RAW);

        $filemanageroptions = [
            'maxfiles' => 1,
            'subdirs' => 0,
            'accepted_types' => ['web_image'],
        ];
        $mform->addElement(
            'filemanager',
            'coverimage_filemanager',
            get_string('trilhacoverimage', 'local_isgcorp'),
            null,
            $filemanageroptions
        );

        $categories = [
            '' => get_string('categorianenhuma', 'local_isgcorp'),
            'tecnologia' => get_string('categoriatecnologia', 'local_isgcorp'),
            'lideranca' => get_string('categorialideranca', 'local_isgcorp'),
            'negocios' => get_string('categorianegocios', 'local_isgcorp'),
            'compliance' => get_string('categoriacompliance', 'local_isgcorp'),
            'saude' => get_string('categoriasaude', 'local_isgcorp'),
            'softskills' => get_string('categoriasoftskills', 'local_isgcorp'),
            'idiomas' => get_string('categoriaidiomas', 'local_isgcorp'),
            'outros' => get_string('categoriaoutros', 'local_isgcorp'),
        ];
        $mform->addElement('select', 'category', get_string('trilhacategory', 'local_isgcorp'), $categories);

        $levels = [
            'iniciante' => get_string('leveliniciante', 'local_isgcorp'),
            'intermediario' => get_string('levelintermediario', 'local_isgcorp'),
            'avancado' => get_string('levelavancado', 'local_isgcorp'),
        ];
        $mform->addElement('select', 'level', get_string('trilhalevel', 'local_isgcorp'), $levels);

        $mform->addElement('text', 'estimatedhours', get_string('trilhahours', 'local_isgcorp'), ['size' => 5]);
        $mform->setType('estimatedhours', PARAM_INT);
        $mform->setDefault('estimatedhours', 0);

        // Cursos que pertencem a essa trilha. Para um catálogo
        // pequeno/médio um <select multiple> é suficiente; se o
        // número de cursos crescer muito, isso pode ser trocado
        // por um autocomplete com busca via AJAX sem mudar o resto
        // do plugin.
        $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname ASC', 'id, fullname');
        unset($courses[SITEID]);
        $mform->addElement(
            'autocomplete',
            'courseids',
            get_string('trilhacourses', 'local_isgcorp'),
            $courses,
            ['multiple' => true]
        );

        $mform->addElement('advcheckbox', 'visible', get_string('trilhavisible', 'local_isgcorp'));
        $mform->setDefault('visible', 1);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}

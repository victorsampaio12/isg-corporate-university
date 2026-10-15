<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_isgcorp\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class assessment_form extends \moodleform {
    protected function definition(): void {
        global $DB;

        $mform = $this->_form;
        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname');
        $options = ['' => get_string('choose')];

        foreach ($courses as $course) {
            $trail = $DB->get_record_sql(
                "SELECT t.name
                   FROM {local_isgcorp_trilha} t
                   JOIN {local_isgcorp_trilha_course} tc ON tc.trilhaid = t.id
                  WHERE tc.courseid = :courseid
               ORDER BY t.sortorder, t.id",
                ['courseid' => $course->id],
                IGNORE_MULTIPLE
            );
            $label = format_string($course->fullname);
            if ($trail) {
                $label .= ' - ' . get_string('assessmenttrail', 'local_isgcorp') . ': ' . format_string($trail->name);
            }
            $options[$course->id] = $label;
        }

        $mform->addElement('select', 'courseid', get_string('assessmentcourse', 'local_isgcorp'), $options);
        $mform->setType('courseid', PARAM_INT);
        $mform->addRule('courseid', get_string('required'), 'required');
        $mform->addHelpButton('courseid', 'assessmentcourse', 'local_isgcorp');

        $mform->addElement('text', 'name', get_string('assessmentname', 'local_isgcorp'), ['size' => 54]);
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', get_string('courseassessment', 'local_isgcorp'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('static', 'rules', '', get_string('assessmentrules', 'local_isgcorp'));
        $this->add_action_buttons(true, get_string('createassessment', 'local_isgcorp'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['courseid']) && local_isgcorp_get_course_assessment((int)$data['courseid'])) {
            $errors['courseid'] = get_string('assessmentalreadyexists', 'local_isgcorp');
        }
        return $errors;
    }
}


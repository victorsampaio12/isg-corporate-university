<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_isgcorp;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the corporate learning flow.
 */
class observer {
    /**
     * Novo usuario (SSO, LDAP ou cadastro manual) entra na coorte dos colaboradores
     * e, com ela, nos cursos das trilhas.
     */
    public static function user_created(\core\event\user_created $event): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        try {
            local_isgcorp_add_to_collaborators_cohort((int) $event->objectid);
        } catch (\Throwable $exception) {
            // Nunca impedir o cadastro/login por causa da inscricao.
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Issues any learning-path certificate earned after a passing quiz attempt.
     *
     * Ouve attempt_submitted e attempt_graded: no Moodle 5.x a nota so e gravada
     * depois do envio (attempt_graded); no 4.5 ela ja existe no attempt_submitted.
     */
    public static function quiz_attempt_submitted(\core\event\base $event): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], 'id,quiz,userid');
        if (!$attempt) {
            return;
        }

        $assessment = local_isgcorp_get_course_assessment((int)$event->courseid);
        if (!$assessment || (int)$assessment->id !== (int)$attempt->quiz) {
            return;
        }

        try {
            local_isgcorp_sync_user_certificates((int)$attempt->userid);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'certificateincomplete') {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
        } catch (\Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

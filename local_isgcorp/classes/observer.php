<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_isgcorp;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the corporate learning flow.
 */
class observer {
    /**
     * Records changes to the preferred certificate name for administrative auditing.
     */
    public static function user_updated(\core\event\user_updated $event): void {
        global $DB;

        $table = new \xmldb_table('local_isgcorp_certname_log');
        if (!$DB->get_manager()->table_exists($table)) {
            return;
        }

        $sql = "SELECT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uid.userid = :userid AND uif.shortname = :shortname";
        $currentvalue = trim((string) $DB->get_field_sql($sql, [
            'userid' => $event->objectid,
            'shortname' => 'isg_certificatename',
        ]));

        $lastvalue = $DB->get_field_sql(
            "SELECT newvalue
               FROM {local_isgcorp_certname_log}
              WHERE userid = :userid
           ORDER BY id DESC",
            ['userid' => $event->objectid],
            IGNORE_MULTIPLE
        );
        $previousvalue = $lastvalue === false ? '' : trim((string) $lastvalue);

        if ($currentvalue === $previousvalue) {
            return;
        }

        $DB->insert_record('local_isgcorp_certname_log', (object) [
            'userid' => (int) $event->objectid,
            'changedby' => (int) $event->userid,
            'oldvalue' => $previousvalue,
            'newvalue' => $currentvalue,
            'isinitial' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Issues the course certificate immediately after a passing quiz attempt.
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/isgcorp/lib.php');
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], 'id,quiz,userid');
        if (!$attempt) { return; }
        $assessment = local_isgcorp_get_course_assessment((int)$event->courseid);
        if (!$assessment || (int)$assessment->id !== (int)$attempt->quiz) { return; }
        try {
            local_isgcorp_get_or_create_certificate((int)$event->courseid, (int)$attempt->userid);
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'certificateincomplete') { debugging($exception->getMessage(), DEBUG_DEVELOPER); }
        } catch (\Throwable $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

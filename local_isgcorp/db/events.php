<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\mod_quiz\\event\\attempt_submitted',
        'callback' => '\\local_isgcorp\\observer::quiz_attempt_submitted',
    ],
    [
        'eventname' => '\\core\\event\\user_created',
        'callback' => '\\local_isgcorp\\observer::user_created',
    ],
];

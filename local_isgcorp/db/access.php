<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Quem pode criar/editar/apagar trilhas e associar cursos a elas.
    'local/isgcorp:manage' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];

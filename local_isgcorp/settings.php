<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('root', new admin_category(
        'local_isgcorp_help_category',
        get_string('adminhelpsection', 'local_isgcorp')
    ), 'ai');
    $ADMIN->add('local_isgcorp_help_category', new admin_externalpage(
        'local_isgcorp_admin_guide',
        get_string('adminhelpopen', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/guide.php'),
        'local/isgcorp:manage'
    ));

    $ADMIN->add('root', new admin_category(
        'local_isgcorp_branding_category',
        get_string('brandingassets', 'local_isgcorp')
    ), 'ai');
    $brandingpage = new admin_settingpage(
        'local_isgcorp_branding',
        get_string('brandingmanage', 'local_isgcorp')
    );
    foreach ([
        ['logo', 'brandinglogo', 'brandinglogodesc', ['.png', '.jpg', '.jpeg', '.svg', '.webp']],
        ['certificatebackground', 'brandingcertificate', 'brandingcertificatedesc', ['.png', '.jpg', '.jpeg']],
        ['homebanner', 'brandingbanner', 'brandingbannerdesc', ['.png', '.jpg', '.jpeg', '.webp']],
    ] as [$filearea, $title, $description, $types]) {
        $setting = new admin_setting_configstoredfile(
            'theme_isgcorp/' . $filearea,
            get_string($title, 'local_isgcorp'),
            get_string($description, 'local_isgcorp'),
            $filearea,
            0,
            ['accepted_types' => $types, 'maxfiles' => 1]
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $brandingpage->add($setting);
    }
    $ADMIN->add('local_isgcorp_branding_category', $brandingpage);

    $coursescategory = $ADMIN->locate('courses');
    if ($coursescategory) {
        $coursescategory->visiblename = get_string('generalsettingsadmin', 'local_isgcorp');
    }

    $ADMIN->add('root', new admin_category(
        'local_isgcorp_training_category',
        get_string('trainingdashboard', 'local_isgcorp')
    ), 'courses');

    $ADMIN->add('local_isgcorp_training_category', new admin_externalpage(
        'local_isgcorp_training',
        get_string('trainingdashboard', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/training.php'),
        'local/isgcorp:manage'
    ));

    $ADMIN->add('local_isgcorp_training_category', new admin_externalpage(
        'local_isgcorp_dashboard',
        get_string('admindashboard', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/dashboard.php'),
        'local/isgcorp:manage'
    ), 'local_isgcorp_training_management');

    $ADMIN->add('local_isgcorp_training_category', new admin_category(
        'local_isgcorp_training_management',
        get_string('trainingmanagement', 'local_isgcorp')
    ));

    $ADMIN->add('local_isgcorp_training_management', new admin_externalpage(
        'local_isgcorp_manage',
        get_string('managetrilhas', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/manage.php'),
        'local/isgcorp:manage'
    ));

    $ADMIN->add('local_isgcorp_training_management', new admin_externalpage(
        'local_isgcorp_courses_manage',
        get_string('gotonativecourses', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/courses_manage.php'),
        'local/isgcorp:manage'
    ));

    $ADMIN->add('local_isgcorp_training_management', new admin_externalpage(
        'local_isgcorp_assessments_manage',
        get_string('manageassessments', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/assessments.php'),
        'local/isgcorp:manage'
    ));
    $ADMIN->add('local_isgcorp_training_management', new admin_externalpage(
        'local_isgcorp_assessment_create',
        get_string('createassessment', 'local_isgcorp'),
        new moodle_url('/local/isgcorp/assessment_create.php'),
        'local/isgcorp:manage',
        true
    ));

    foreach (['coursemgmt', 'addcategory', 'addnewcourse', 'tooluploadcourse'] as $duplicatednode) {
        $node = $ADMIN->locate($duplicatednode);
        if ($node) {
            $node->hidden = true;
        }
    }

    foreach (['grades', 'appearance'] as $hiddencategory) {
        $node = $ADMIN->locate($hiddencategory);
        if ($node) {
            $node->hidden = true;
        }
    }
}

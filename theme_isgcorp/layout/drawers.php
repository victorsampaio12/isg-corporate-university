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
 * Drawer layout do theme_isgcorp.
 *
 * Reaproveita a base do Boost, mas injeta a navegacao mobile
 * alinhada com a sidebar customizada e esconde o drawer direito
 * para perfis de aluno.
 *
 * @package   theme_isgcorp
 * @copyright 2026 Truly Tecnologia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}

$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();
$showblockdrawer = $hasblocks;
if (function_exists('theme_isgcorp_should_show_blockdrawer')) {
    $showblockdrawer = $hasblocks && theme_isgcorp_should_show_blockdrawer();
}
if (!$showblockdrawer) {
    $blockdraweropen = false;
    $forceblockdraweropen = false;
}

$secondarynavigation = false;
$overflow = '';
$hidefullheader = function_exists('theme_isgcorp_should_hide_full_header')
    && theme_isgcorp_should_hide_full_header($PAGE);
$hidesecondarynavigation = function_exists('theme_isgcorp_should_hide_secondary_navigation')
    && theme_isgcorp_should_hide_secondary_navigation($PAGE);
if (!$hidesecondarynavigation && $PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
if (function_exists('theme_isgcorp_filter_student_user_menu')) {
    $primarymenu['user'] = theme_isgcorp_filter_student_user_menu($primarymenu['user']);
}
$currenturl = $PAGE->url ? $PAGE->url->out_as_local_url(false) : '';
$mobileprimarynav = $primarymenu['mobileprimarynav'];
if (function_exists('theme_isgcorp_get_mobile_primary_nav') && isloggedin() && !isguestuser()) {
    $mobileprimarynav = theme_isgcorp_get_mobile_primary_nav($currenturl);
}

$buildregionmainsettings = !$hidefullheader
    && !$PAGE->include_region_main_settings_in_header_actions()
    && !$secondarynavigation;
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

if (!theme_isgcorp_is_privileged_user() && $PAGE->pagetype === 'user-profile') {
    $PAGE->set_button('');
}

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
if ($hidefullheader) {
    $headercontent = false;
    $overflow = '';
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'logourl' => $OUTPUT->image_url('brand-mark', 'theme_isgcorp')->out(false),
    'showfullheader' => !$hidefullheader,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'showblockdrawer' => $showblockdrawer,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $mobileprimarynav,
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
];

echo $OUTPUT->render_from_template('theme_boost/drawers', $templatecontext);

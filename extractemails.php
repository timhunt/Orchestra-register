<?php

// Orchestra Register is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Orchestra Register is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Orchestra Register. If not, see <http://www.gnu.org/licenses/>.

/**
 * A script to extract lists of email addresses, based on the attendance data.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/forms.php');
$or = new orchestra_register();

$currentuser = $or->get_current_user();
if (!$currentuser->can_edit_users()) {
    throw new permission_exception('You don\'t have permission to access users\' email addresses.');
}

$parts = $or->get_parts(true);
$allparts = array();
foreach ($parts as $sectionparts) {
    $allparts += $sectionparts;
}

$events = $or->get_events(true);
$eventoptions = array(0 => 'Do not restrict');
foreach ($events as $event) {
    $eventoptions[$event->id] = $event->name . ' ' . $event->get_nice_datetime(event::DATE_FORMAT, false);
}

$states = attendance::$symbols;

$defaultsettings = new stdClass();
$defaultsettings->part = array_keys($allparts);
$defaultsettings->attendance = array_keys($states);

$form = new form($or->url('extractemails.php', false, false), 'Get email addresses', false);
$form->add_field(new group_multi_select_field('part', 'Include only', $parts, count($parts) + count($allparts)));
$form->add_field(new select_field('event', 'Further restrict by attendance at', $eventoptions, 0));
$form->add_field(new multi_select_field('attendance', 'Include only', $states, count($states)));

$form->set_initial_data($defaultsettings);
$form->parse_request($or);

$emails = null;
switch ($form->get_outcome()) {
    case form::SUBMITTED:
        $settings = $form->get_submitted_data('stdClass');

        $players = $or->load_selected_players($settings->part, $settings->event, $settings->attendance);

        if (empty($players)) {
            $emails = array('-none-');

        } else {
            $emails = array();
            foreach ($players as $player) {
                $emails[] = $player->email;
            }
        }
}

$output = $or->get_output();
$output->header('Get a list of email addresses');
echo '<p><a href="' . $or->url('') . '">Back to the register</a></p>';
echo $form->output($output);
$output->call_to_js('init_extract_emails_page');

if (!is_null($emails)) {
    echo '<h2>Requested emails</h2>';
    echo '<textarea id="extractedemails" readonly="readonly" cols="80" rows="25">' .
            implode('; ', $emails) . '</textarea>';
}

$output->footer();

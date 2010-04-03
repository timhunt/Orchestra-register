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
 * A page to edit or show an event.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/form.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_events()) {
    throw new permission_exception('You don\'t have permission to edit events.');
}

$eventid = $or->get_param('id', request::TYPE_INT, 0, false);

if ($eventid) {
    $event = $or->get_event($eventid);
    $title = 'Edit an event';
    $submitlabel = 'Save changes';
    $url = $or->url('event.php?id=' . $eventid, false, false);

} else {
    $event = new event();
    $event->timestart = strtotime('next Friday 12:45 ');
    $event->timeend = strtotime('next Friday 13:45');
    $title = 'Add an event';
    $submitlabel = 'Create event';
    $url = $or->url('event.php', false, false);
}

$event->date = strftime('%d %B %Y', $event->timestart);
$event->start = strftime('%H:%M', $event->timestart);
$event->end = strftime('%H:%M', $event->timeend);

$form = new form($url, $submitlabel);
$form->add_field(new text_field('name', 'Event name', request::TYPE_RAW));
$form->add_field(new text_field('venue', 'Venue', request::TYPE_RAW));
$form->add_field(new date_field('date', 'Date'));
$form->add_field(new time_field('start', 'Time start'));
$form->add_field(new time_field('end', 'Time end'));
$form->add_field(new text_field('description', 'Description', request::TYPE_RAW));
$form->set_required_fields('name', 'venue', 'date', 'start', 'end');

$form->set_initial_data($event);
$form->parse_request($or);
if ($form->get_field_value('start') > $form->get_field_value('end')) {
    $form->set_field_error('end', 'The end time must be after the start time');
}

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('events.php');

    case form::SUBMITTED:
        $newevent = $form->get_submitted_data('event');
        $newevent->timestart = strtotime($newevent->date . ' ' . $newevent->start);
        $newevent->timeend = strtotime($newevent->date . ' ' . $newevent->end);

        if ($eventid) {
            $newevent->id = $eventid;
            $or->update_event($newevent);
            $or->log('edit event ' . $newevent->id);

        } else {
            $or->create_event($newevent);
            $or->log('add event ' . $newevent->id);
        }

        $or->redirect('events.php');
}

$output = $or->get_output();
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_event_page');
$output->footer();

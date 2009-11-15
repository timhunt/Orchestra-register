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
 * Export the list of events in ical format.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib.php');
$or = new orchestra_register();
$events = $or->get_events();

function ical_foramt_timestamp($timestamp) {
    return gmstrftime('%Y%m%dT%H%M%SZ', $timestamp);
}

function output_ical_property($name, $value) {
    echo $name , ':', $value, "\n";
}

function output_event_as_ical(event $event, orchestra_register $or) {
    output_ical_property('BEGIN', 'VEVENT');
    output_ical_property('DTSTAMP', ical_foramt_timestamp($event->timemodified));
    output_ical_property('UID', $or->get_event_guid($event));
    output_ical_property('DTSTART', ical_foramt_timestamp($event->timestart));
    output_ical_property('DTEND', ical_foramt_timestamp($event->timeend));
    output_ical_property('CATEGORIES', 'REHEARSAL');
    output_ical_property('SUMMARY', $event->name);
    output_ical_property('DESCRIPTION', $event->description);
    output_ical_property('END', 'VEVENT');
}

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="orchestra.ics"');

output_ical_property('BEGIN', 'VCALENDAR');
output_ical_property('PRODID', '-//Tim Hunt//Orchestra Register Version 1.0//EN');
output_ical_property('VERSION', '2.0');

foreach ($events as $event) {
    output_event_as_ical($event, $or);
}

output_ical_property('END', 'VCALENDAR');


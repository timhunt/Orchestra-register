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
 * Class holding data about one player in a rehearsal series.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player {
    public $id;
    public $seriesid;
    public $part;
    public $section;

    public $firstname;
    public $lastname;
    public $email;
    public $authkey;
    public $username;
    public $pwhash;
    public $pwsalt;
    public $role = user::PLAYER;

    public $attendance = array(); // $eventid => attendance.

    public function get_attendance($event) {
        if (!array_key_exists($event->id, $this->attendance)) {
            $attendance = new attendance();
            $attendance->eventid = $event->id;
            $attendance->userid = $this->id;
            $attendance->seriesid = $this->seriesid;
            $this->attendance[$event->id] = $attendance;
        }
        return $this->attendance[$event->id];
    }
    public function get_name() {
        return $this->firstname . ' ' . $this->lastname;
    }
    public function get_public_name() {
        return $this->firstname . ' ' . substr($this->lastname, 0, 1);
    }
}

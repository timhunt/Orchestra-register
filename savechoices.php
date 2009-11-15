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
 * Save any changed attendance statuses.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib/lib.php');
$or = new orchestra_register();
$events = $or->get_events();
$players = $or->get_players();
$user = $or->get_current_user();
$or->load_attendance();

foreach ($players as $player) {
    if (!$user->can_edit_attendance($player)) {
        continue;
    }

    foreach ($events as $event) {
        $attendance = $player->get_attendance($event);
        $newattendance = $or->get_param($attendance->get_field_name(), request::TYPE_ATTENDANCE);
        if ($newattendance && $newattendance != $attendance &&
                $newattendance != attendance::NOTREQUIRED && $attendance != attendance::NOTREQUIRED) {
            $or->set_attendance($player, $event, $newattendance);
        }
    }
}

$or->redirect('');

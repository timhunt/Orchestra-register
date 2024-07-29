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

require_once(__DIR__ . '/setup.php');
$or = new orchestra_register();

$includepast = $or->get_param('past', request::TYPE_BOOL, false, false);
$backto = $or->get_param('back', request::TYPE_INT, 0, false);

$events = $or->get_events($includepast);
$players = $or->get_players();
$user = $or->get_current_user();
$or->load_attendance();

$or->require_sesskey();

$canchangenotrequired = $user->can_edit_events();

foreach ($players as $player) {
    if (!$user->can_edit_attendance($player)) {
        continue;
    }

    foreach ($events as $event) {
        $attendance = $player->get_attendance($event);
        $newattendance = $or->get_param($attendance->get_field_name(), request::TYPE_ATTENDANCE);
        if ($newattendance && has_really_changed($newattendance, $attendance->status, $canchangenotrequired)) {
            $or->set_attendance($player, $event, $newattendance);
            $or->log('changed attendance for player ' . $player->id . ' at event ' .
                    $event->id . ' to ' . $newattendance);
        }
    }
}

$url = '';
$params = [];
if ($backto) {
    $url = 'player.php';
    if ($backto != $user->id) {
        $params[] = 'id=' . $backto;
    }
}
if ($includepast) {
    $params[] = 'past=1';
}
if ($params) {
    $url .= '?' . implode('&', $params);
}
$or->redirect($url);


function has_really_changed(string $newattendance, string $oldattendance, bool $canchangenotrequired): bool {
    if ($newattendance == 'nochange' || $newattendance == $oldattendance) {
        return false;
    }
    if (!$canchangenotrequired &&
            ($newattendance == attendance::NOTREQUIRED || $oldattendance == attendance::NOTREQUIRED)) {
        return false;
    }
    return true;
}

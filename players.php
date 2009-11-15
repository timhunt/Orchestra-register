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
 * A page to show admins a list of each players' magic URL.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib/lib.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_players()) {
    throw new permission_exception('You don\'t have permission to edit the list of players.');
}

$players = $or->get_players();

$output = new html_output();
$output->header($or, 'Get players\' edit URLs');
echo '<ul>';
foreach ($players as $player) {
    echo '<li>', $player->get_name(), ' (', $player->part, ')',
            ' <input type="text" size="80" readonly="readonly" value="', $or->url('?t=' . $player->authkey, false),
            '" />', "</li>\n";
}
echo '</ul>';
echo '<p><a href="' . $or->url('') . '">Back to the register</a></p>';
$output->call_to_js('init_edit_players_page');
$output->footer($or);

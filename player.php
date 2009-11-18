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
 * A page to edit or show a player.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib/core.php');
require_once(dirname(__FILE__) . '/lib/form.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_players()) {
    throw new permission_exception('You don\'t have permission to edit the list of players.');
}

$playerid = $or->get_param('id', request::TYPE_INT, 0, false);

if ($playerid) {
    $player = $or->get_player($playerid);
    $title = 'Edit a player';
    $submitlabel = 'Save changes';
    $url = $or->url('player.php?id=' . $playerid, false, false);

} else {
    $player = new player();
    $title = 'Add a player';
    $submitlabel = 'Create player';
    $url = $or->url('player.php', false, false);
}

$parts = $or->get_parts();

$assignableroles = $user->assignable_roles($playerid);

$form = new form($url, $submitlabel);
$form->add_field(new text_field('firstname', 'First name', request::TYPE_RAW));
$form->add_field(new text_field('lastname', 'Last name', request::TYPE_RAW));
$form->add_field(new text_field('email', 'Email', request::TYPE_EMAIL));
$form->add_field(new group_select_field('part', 'Part', $parts));
if ($assignableroles) {
    $form->add_field(new select_field('role', 'Role', $assignableroles));
    $form->get_field('role')->set_note('Controls what this person is allowed to do');
}
if ($user->can_set_passwords()) {
    $form->add_field(new password_field('changepw', 'New password', request::TYPE_RAW));
    $form->add_field(new password_field('confirmchangepw', 'Comfirm new password', request::TYPE_RAW));
    $form->get_field('changepw')->set_note('Leave blank for no change');
}
$form->set_required_fields('firstname', 'lastname', 'email');

$form->set_initial_data($player);
$form->parse_request($or);
if ($user->can_set_passwords() && $form->get_field_value('changepw') != $form->get_field_value('confirmchangepw')) {
    $form->set_field_error('changepw', '');
    $form->set_field_error('confirmchangepw', 'The two passwords did not match');
}

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('players.php');

    case form::SUBMITTED:
        $newplayer = $form->get_submitted_data('player');

        if ($playerid) {
            $newplayer->id = $playerid;
            if (!$assignableroles) {
                $newplayer->role = $player->role;
            }
            $or->update_player($newplayer);

        } else {
            $or->create_player($newplayer);
        }

        if ($user->can_set_passwords() && ($newpassword = $form->get_field_value('changepw'))) {
            $or->set_player_password($newplayer->id, $newpassword);
        }
        $or->redirect('players.php');
}

$output = new html_output($or);
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_player_page');
$output->footer();

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

require_once(dirname(__FILE__) . '/lib/lib.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_players()) {
    throw new permission_exception('You don\'t have permission to edit the list of players.');
}

if ($or->get_param('cancel', request::TYPE_BOOL, false)) {
    $or->redirect('players.php');

} else if ($id = $or->get_param('save', request::TYPE_INT, 0)) {
    // TODO save
    $or->redirect('players.php');

}

$playerid = $or->get_param('id', request::TYPE_INT, 0, false);

if ($playerid) {
    $player = $or->get_player($playerid);
    $title = 'Edit a player';
    $submitlabel = 'Save changes';

} else {
    $player = new player();
    $title = 'Add a player';
    $submitlabel = 'Create player';
}

$parts = $or->get_parts();

$assignableroles = $user->assignable_roles();
if (!array_key_exists($player->role, $assignableroles)) {
    $assignableroles = false;
}

$output = new html_output($title);
$output->header($or, $title);

?>
<form action="<?php echo $or->url('player.php'); ?>" method="post">
<div>
<?php
echo $output->text_field('First name', 'firstname', $player->firstname);
echo $output->text_field('Last name', 'lastname', $player->lastname);
echo $output->text_field('Email', 'email', $player->email);
echo $output->form_field('Part', $output->group_select('part', $parts));
if ($assignableroles) {
    echo $output->form_field('Role', $output->select('role', $user->assignable_roles(), $player->role),
            'Controls what this person is allowed to do');
}
echo $output->password_field('New password', 'newpassword', '', 'Leave blank for no change');
echo $output->password_field('Comfirm new password', 'confirmnewpassword', '');
?>
<p><input type="submit" name="save" value="<?php echo $submitlabel; ?>" />
<input type="submit" name="cancel" value="Cancel" /></p>
</div>
</form>
<?php
$output->call_to_js('init_edit_player_page');
$output->footer($or);

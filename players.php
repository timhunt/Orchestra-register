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
 * A page to show admins a list of users, with options to detele/undelete and
 * edit. Also shows the magic URL for users to edit their attendance.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib/core.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_players()) {
    throw new permission_exception('You don\'t have permission to edit the list of players.');
}

if ($id = $or->get_param('delete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $player = $or->get_player($id);
    if ($player && $player->deleted == 0) {
        $or->delete_player($player);
    }
    $or->redirect('players.php');

} else if ($id = $or->get_param('undelete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $player = $or->get_player($id, true);
    if ($player && $player->deleted == 1) {
        $or->undelete_player($player);
    }
    $or->redirect('players.php');

}

$players = $or->get_players(true);

$output = new html_output($or);
$output->header('Edit players');

?>
<p><a href="<?php echo $or->url('player.php'); ?>">Add another player</a></p>
<p><a href="<?php echo $or->url(''); ?>">Back to the register</a></p>
<table>
<thead>
<tr class="headingrow">
<th>Section</th>
<th>Part</th>
<th>Name</th>
<th>Email</th>
<th>Actions</th>
<th>URL</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($players as $player) {
    $actions = array();
    if ($player->deleted) {
        $actions[] = $output->action_button($or->url('players.php', false),
                array('undelete' => $player->id), 'Un-delete');
        $extrarowclass = ' deleted';
        $readonly = 'disabled="disabled"';
    } else {
        $actions[] = '<a href="' . $or->url('player.php?id=' . $player->id, false) . '">Edit</a>';
        $actions[] = $output->action_button($or->url('players.php', false),
                array('delete' => $player->id), 'Delete');
        $extrarowclass = '';
        $readonly = 'readonly="readonly"';
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?><?php echo $extrarowclass; ?>">
<td><?php echo $player->section; ?></td>
<td><?php echo $player->part; ?></td>
<th><?php echo $player->get_name(); ?></th>
<td><?php echo $player->email; ?></td>
<td><?php echo implode("\n", $actions); ?></td>
<td><input type="text" size="60" <?php echo $readonly; ?> value="<?php echo $or->url('?t=' . $player->authkey, false); ?>" /></td>
</tr>
<?php
}
?>
</tbody>
</table>
<p><a href="<?php echo $or->url('player.php'); ?>">Add another player</a></p>
<p><a href="<?php echo $or->url(''); ?>">Back to the register</a></p>
<?php
$output->call_to_js('init_edit_players_page');
$output->footer();

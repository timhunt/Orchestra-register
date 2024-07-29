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
 * Script for editing the players in a series of rehearsals,
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');

$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_players()) {
    throw new permission_exception('You don\'t have permission to edit players.');
}

$players = $or->get_players(true);
$series = $or->get_series_list();
$parts = $or->get_parts(true);

if ($or->get_param('save', request::TYPE_BOOL, false)) {
    $or->require_sesskey();

    foreach ($players as $player) {
        $newpart = $or->get_param('player' . $player->id, request::TYPE_RAW);

        if (!$or->is_valid_part($newpart)) {
            continue;
        }

        if ($newpart === '0') {
            $newpart = null;
        }
        if ($newpart == $player->part) {
            continue;
        }

        $or->set_player_part($player, $newpart);
        if (!$newpart) {
            $newpart = 'Not playing';
        }
        $or->log('Changed part for user ' . $player->id . ' in series ' .
                $or->get_current_seriesid() . ' to ' . $newpart);
    }

    $or->redirect('players.php');
}

$output = $or->get_output();
$output->header('Edit players for ' . $series[$or->get_current_seriesid()]->name);
echo $output->links_to_other_series($series, 'players.php');

?>
<p><a href="<?php echo $or->url('users.php'); ?>">Edit the list of users</a></p>
<?php echo $output->back_link(); ?>

<form action="<?php echo $or->url('players.php'); ?>" method="post">
<div>

<?php
echo $output->sesskey_input();
?>

<p><input type="submit" name="save" value="Save changes" /></p>

<table id="players">
<thead>
<tr class="headingrow">
<th>Name</th>
<th>Part</th>
<th>URL</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($players as $player) {
    if (empty($player->part)) {
        $player->part = 0;
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<th><label for="player<?php echo $player->id ?>"><?php echo $output->player_link($player, null, true); ?></label></th>
<td><?php
    echo $output->group_select('player' . $player->id, $parts, $player->part);
    ?>
</td>
<td><input type="text" size="40" readonly="readonly" value="<?php echo $or->url(
            '?t=' . $user->authkey, false, $or->get_config()->defaultseriesid); ?>" /></td>
</tr>
    <?php
}
?>
</tbody>
</table>
<p><input type="submit" name="save" value="Save changes" /></p>

</div>
</form>

<p><a href="<?php echo $or->url('users.php'); ?>">Edit the list of users</a></p>
<?php
echo $output->back_link();
$output->call_to_js('init_edit_players_page', [array_keys($players)]);
$output->footer();

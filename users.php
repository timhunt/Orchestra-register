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

require_once(__DIR__ . '/setup.php');
$or = new orchestra_register();

$currentuser = $or->get_current_user();
if (!$currentuser->can_edit_users()) {
    throw new permission_exception('You don\'t have permission to edit users.');
}

$users = $or->get_users(true);
$series = $or->get_series_list();
$roles = user::get_all_roles();

$output = $or->get_output();
$output->header('Edit users');

?>
<p><a href="<?php echo $or->url('edituser.php'); ?>">Add another user</a></p>
<?php
echo $output->links_to_other_series($series, 'players.php', false, true, 'Edit the players for');
echo $output->back_link();

?>
<table>
<thead>
<tr class="headingrow">
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Actions</th>
<th>URL</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($users as $user) {
    $actions = [];
    if ($user->role == user::DISABLED) {
        $extrarowclass = ' deleted';
        $readonly = 'disabled="disabled"';
    } else {
        $extrarowclass = '';
        $readonly = 'readonly="readonly"';
    }
    $actions[] = '<a href="' . $or->url('edituser.php?id=' . $user->id, false) . '">Edit</a>';
    $actions[] = '<a href="' . $or->emails()->forgotten_url_mailto($user) . '">Recover URL email</a>';
    $role = '';
    if ($user->role != user::PLAYER) {
        $role = $roles[$user->role];
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?><?php echo $extrarowclass; ?>">
<th><?php echo htmlspecialchars($user->get_name()); ?></th>
<td><?php echo htmlspecialchars($user->email); ?></td>
<td><?php echo htmlspecialchars($role); ?></td>
<td><?php echo implode("\n", $actions); ?></td>
<td><input type="text" size="20" <?php echo $readonly; ?> value="<?php echo $or->url(
            '?t=' . $user->authkey, false, $or->get_config()->defaultseriesid); ?>" aria-label="Edit URL for <?php
            echo htmlspecialchars($user->get_name()); ?>"/></td>
</tr>
<?php
}
?>
</tbody>
</table>
<p><a href="<?php echo $or->url('edituser.php'); ?>">Add another user</a></p>
<?php
echo $output->links_to_other_series($series, 'players.php', false, true, 'Edit the players for');
echo $output->back_link();
$output->call_to_js('init_edit_users_page');
$output->footer();

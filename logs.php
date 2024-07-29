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
 * A page to view the logs.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const LOG_PAGE_SIZE = 100;

require_once(__DIR__ . '/setup.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_view_logs()) {
    throw new permission_exception('You don\'t have permission to view the logs.');
}

$numpages = ceil($or->get_num_logs() / LOG_PAGE_SIZE);

$page = $or->get_param('page', request::TYPE_INT, 1, false);
if ($page > $numpages) {
    $page = $numpages;
}

$topactions = new actions();
$topactions->add($or->url(''), 'Back to the register');
$topactions->add($or->url('logs.php?page=' . ($page - 1)), '< Previous page', $page > 1);

$bottomactions = new actions();
$bottomactions->add($or->url('logs.php?page=' . ($page + 1)), 'Next page >', $page < $numpages);
$bottomactions->add($or->url(''), 'Back to the register');

$logs = $or->load_logs(($page - 1) * LOG_PAGE_SIZE, LOG_PAGE_SIZE);

$output = $or->get_output();
$output->header('Logs page ' . $page);
echo $topactions->output($output);

?>
<table class="logs">
<thead>
<tr class="headingrow">
<th>Time</th>
<th>User</th>
<th>Authentication</th>
<th>IP address</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($logs as $log) {
    if ($log->firstname) {
        $name = $log->firstname . ' ' . $log->lastname . ' (' . $log->email . ')';
    } else {
        $name = '-';
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td><?php echo strftime('%Y-%m-%d %H:%M:%S', $log->timestamp); ?></td>
<td><?php echo htmlspecialchars($name); ?></td>
<td><?php echo htmlspecialchars(user::auth_name($log->authlevel)); ?></td>
<td><?php echo htmlspecialchars($log->ipaddress); ?></td>
<td><?php echo htmlspecialchars($log->action); ?></td>
</tr>
    <?php
}
?>
</tbody>
</table>
<?php
echo $bottomactions->output($output);
$output->call_to_js('init_logs_page');
$output->footer();

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
 * A page to show admins a list of rehearsal series, with options to
 * detele/undelete and edit them.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_series()) {
    throw new permission_exception('You don\'t have permission to edit rehearsal series.');
}

if ($id = $or->get_param('delete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $series = $or->get_series($id);
    if ($series && $series->deleted == 0) {
        $or->delete_series($series);
        $or->log('delete series ' . $id);
    }
    $or->redirect('series.php');

} else if ($id = $or->get_param('undelete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $series = $or->get_series($id, true);
    if ($series && $series->deleted == 1) {
        $or->undelete_series($series);
        $or->log('undelete series ' . $id);
    }
    $or->redirect('series.php');
}

$serieslist = $or->get_series_list(true);

$output = $or->get_output();
$output->header('Edit rehearsal series');

?>
<p><a href="<?php echo $or->url('editseries.php'); ?>">Add another rehearsal series</a></p>
<p><a href="<?php echo $or->url(''); ?>">Back to the register</a></p>
<table>
<thead>
<tr class="headingrow">
<th>Name</th>
<th>Description</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($serieslist as $series) {
    $actions = array();
    if ($series->deleted) {
        $actions[] = $output->action_button($or->url('series.php', false),
                array('undelete' => $series->id), 'Restore');
        $extrarowclass = ' deleted';

    } else {
        $actions[] = '<a href="' . $or->url('editseries.php?id=' . $series->id, false) . '">Edit</a>';
        if ($series->id != $or->get_config()->defaultseriesid) {
            $actions[] = $output->action_button($or->url('series.php', false),
                    array('delete' => $series->id), 'Archive');
        } else {
            $actions[] = '<div class="defaultmarker">Current default</div>';
        }
        $extrarowclass = '';
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?><?php echo $extrarowclass; ?>">
<th><?php echo htmlspecialchars($series->name); ?></th>
<td><?php echo htmlspecialchars($series->description); ?></td>
<td><?php echo implode("\n", $actions); ?></td>
</tr>
<?php
}
?>
</tbody>
</table>
<p><a href="<?php echo $or->url('editseries.php'); ?>">Add another rehearsal series</a></p>
<p><a href="<?php echo $or->url(''); ?>">Back to the register</a></p>
<?php
$output->call_to_js('init_edit_series_list_page');
$output->footer();

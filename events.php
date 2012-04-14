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
 * A page to show admins a list of events in the current rehearsal series,
 * with options to detele/undelete and edit them.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_events()) {
    throw new permission_exception('You don\'t have permission to edit events.');
}

if ($id = $or->get_param('delete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $event = $or->get_event($id);
    if ($event && $event->deleted == 0) {
        $or->delete_event($event);
        $or->log('delete event ' . $id);
    }
    $or->redirect('events.php');

} else if ($id = $or->get_param('undelete', request::TYPE_INT, 0)) {
    $or->require_sesskey();
    $event = $or->get_event($id, true);
    if ($event && $event->deleted == 1) {
        $or->undelete_event($event);
        $or->log('undelete event ' . $id);
    }
    $or->redirect('events.php');

}

$series = $or->get_series_list();
$events = $or->get_events(true, true);

$output = $or->get_output();
$output->header('Edit events for ' . $series[$or->get_current_seriesid()]->name);
echo $output->links_to_other_series($series, 'events.php');

?>
<p><a href="<?php echo $or->url('editevent.php'); ?>">Add another event</a></p>
<?php echo $output->back_link(); ?>
<table>
<thead>
<tr class="headingrow">
<th>Event name</th>
<th>Venue</th>
<th>Date</th>
<th>Time start</th>
<th>Time end</th>
<th>Actions</th>
<th>Description</th>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($events as $event) {
    $actions = array();
    if ($event->deleted) {
        $actions[] = $output->action_button($or->url('events.php', false),
                array('undelete' => $event->id), 'Un-delete');
        $extrarowclass = ' deleted';

    } else {
        $actions[] = '<a href="' . $or->url('editevent.php?id=' . $event->id, false) . '">Edit</a>';
        $actions[] = $output->action_button($or->url('events.php', false),
                array('delete' => $event->id), 'Delete');
        $extrarowclass = '';
    }
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?><?php echo $extrarowclass; ?>">
<th><?php echo $output->event_link($event); ?></th>
<td><?php echo htmlspecialchars($event->venue); ?></td>
<td><?php echo strftime(event::DATE_FORMAT, $event->timestart); ?></td>
<td><?php echo strftime(event::TIME_FORMAT, $event->timestart); ?></td>
<td><?php echo strftime(event::TIME_FORMAT, $event->timeend); ?></td>
<td><?php echo implode("\n", $actions); ?></td>
<td><?php echo htmlspecialchars($event->description); ?></td>
</tr>
<?php
}
?>
</tbody>
</table>
<p><a href="<?php echo $or->url('editevent.php'); ?>">Add another event</a></p>
<?php
echo $output->back_link();
$output->call_to_js('init_edit_events_page');
$output->footer();

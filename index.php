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
 * Main script. Show the register,
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');

$or = new orchestra_register();

$includepast = $or->get_param('past', request::TYPE_BOOL, false, false);
$printview = $or->get_param('print', request::TYPE_BOOL, false, false);

$events = $or->get_events($includepast);
$user = $or->get_current_user();
if (!empty($user->player->part)) {
    $players = $or->get_players(false, $user->player->section, $user->player->part);
    $savechangesbutton = '<p><input type="submit" name="save" value="Save changes" /></p>';
} else {
    $players = $or->get_players(false);
    $savechangesbutton = '';
}
$or->load_attendance();
$subtotals = $or->load_subtotals();

if ($includepast) {
    $showhidepasturl = $or->url('');
    $showhidepastlabel = 'Hide events in the past';
} else {
    $showhidepasturl = $or->url('?past=1');
    $showhidepastlabel = 'Show events in the past';
}

$actions = new actions();
$actions->add($showhidepasturl, $showhidepastlabel);
$actions->add($or->url('ical.php', false), 'Download iCal file (to add the rehearsals into Outlook, etc.)');
$actions->add($or->url('?print=1'), 'Printable view');
$actions->add($or->url('players.php', false), 'Edit the list of players', $user->can_edit_players());
$actions->add($or->url('events.php', false), 'Edit the list of events', $user->can_edit_events());
$actions->add($or->url('wikiformat.php', false), 'List of events to copy-and-paste into the wiki', $user->can_edit_events());
//$actions->add($or->url('admin.php', false), 'Edit the system configuration', $user->can_edit_config());
$actions->add($or->url('logs.php', false), 'View the system logs', $user->can_view_logs());

$output = $or->get_output();

$bodyclass = '';
if ($printview) {
    $bodyclass = 'print';
}
$output->header('', $bodyclass);

if (!$printview && $user->is_authenticated()) {
?>
<form action="<?php echo $or->url('savechoices.php'); ?>" method="post">
<div>

<?php
    echo $output->sesskey_input($or);
    if ($includepast) {
        ?>
<input type="hidden" name="past" value="1" />
        <?php
    }
    echo $savechangesbutton;
}
?>

<table id="register">
<thead>
<tr class="headingrow">
<th>Section</th>
<th>Part</th>
<th>Name</th>
<?php
foreach ($events as $event) {
    ?>
<th>
<span class="eventname"><?php echo htmlspecialchars($event->name); ?></span>
<span class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></span>
<span class="eventvenue"><?php echo htmlspecialchars($event->venue); ?></span>
</th>
    <?php
}
?>
</tr>
</thead>
<tbody>
<?php
$rowparity = 1;
foreach ($players as $player) {
    $editable = $user->can_edit_attendance($player);
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td><?php echo htmlspecialchars($player->section); ?></td>
<td><?php echo htmlspecialchars($player->part); ?></td>
<th><?php echo htmlspecialchars($player->get_public_name()); ?></th>
    <?php
    foreach ($events as $event) {
        $attendance = $player->get_attendance($event);
        ?>
<td class="<?php echo $attendance->status; ?>"><?php
        if (!$printview && $editable) {
            echo $attendance->get_select($user->can_edit_events());
        } else {
            echo $attendance->get_symbol();
        }
        ?>
</td>
        <?php
    }
    ?>
</tr>
    <?php
}
?>
</tbody>
<tbody id="subtotals">
<tr class="headingrow">
<th colspan="3">Totals by part</th>
<?php
foreach ($events as $event) {
    ?>
<th><span class="eventdatetime"><?php echo htmlspecialchars($event->get_nice_datetime()); ?></span></th>
    <?php
}
?>
</tr>
<?php
$rowparity = 1;
foreach ($subtotals as $part => $subtotal) {
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td><?php echo htmlspecialchars($subtotal->section); ?></td>
<th colspan="2"><?php echo htmlspecialchars($part); ?></th>
    <?php
    foreach ($events as $event) {
        ?>
<td>
        <?php
        if ($subtotal->numplayers[$event->id]) {
            echo '<span class="total">', $subtotal->attending[$event->id], '</span><span class="outof">/',
                    $subtotal->numplayers[$event->id], '</span>';
        } else {
            echo '-';
        }
        ?>
</td>
        <?php
    }
    ?>
</tr>
    <?php
}
?>
</tbody>
</table>
<?php
if (!$printview && $user->is_authenticated()) {
    ?>

<input type="submit" name="save" value="Save changes" />
</div>
</form>
    <?php
}

if ($printview) {
    ?>
<p>From <?php echo $or->url('', false); ?> at <?php echo strftime('%H:%M, %d %B %Y') ?>.</p>
    <?php
} else {
    echo $actions->output($output);
    $output->call_to_js('init_index_page', array(array_keys($events), array_keys($players)));
}

$output->footer();

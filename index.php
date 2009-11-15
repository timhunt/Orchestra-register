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

        ini_set('display_errors', 1);
        error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib.php');

$or = new orchestra_register();
$events = $or->get_events();
$user = $or->get_current_user();
if (!empty($user->player->part)) {
   $players = $or->get_players($user->player->section, $user->player->part);
} else {
    $players = $or->get_players();
}
$or->load_attendance();
$subtotals = $or->load_subtotals();
$rowparity = 1;

$output = new html_output();
$output->header($or);
?>
<form action="<?php echo $or->url('savechoices.php'); ?>" method="post">
<div>

<table>
<thead>
<tr class="headingrow">
<th>Section</th>
<th>Part</th>
<th>Name</th>
<?php
foreach ($events as $event) {
    ?>
<th>
<span class="eventname"><?php echo $event->name; ?></span>
<span class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></span>
<span class="eventvenue"><?php echo $event->venue; ?></span>
</th>
    <?php
}
?>
</tr>
</thead>
<tbody>
<?php
foreach ($players as $player) {
    $editable = $user->can_edit_attendance($player);
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td><?php echo $player->section; ?></td>
<td><?php echo $player->part; ?></td>
<th><?php echo $player->get_name(); ?></th>
    <?php
    foreach ($events as $event) {
        $attendance = $player->get_attendance($event);
        if ($editable && $attendance->status != attendance::NOTREQUIRED) {
            ?>
<td class="<?php echo $attendance->status; ?>"><?php echo $attendance->get_select(); ?></td>
            <?php
        } else {
            ?>
<td class="<?php echo $attendance->status; ?>"><?php echo $attendance->get_symbol(); ?></td>
            <?php
        }
    }
    ?>
</tr>
    <?php
}
$rowparity = 1;
?>
<tr class="headingrow">
<th colspan="3">Totals by part</th>
<?php
foreach ($events as $event) {
    ?>
<th><span class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></span></th>
    <?php
}
?>
</tr>
<?php
foreach ($subtotals as $part => $subtotal) {
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td><?php echo $subtotal->section; ?></td>
<th colspan="2"><?php echo $part; ?></th>
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

<input type="submit" name="save" value="Save changes" />
</div>
</form>

<p><a href="<?php echo $or->url('ical.php', false); ?>">Download iCal file (will get the rehearsals into Outlook)</a></p>
<p><a href="<?php echo $or->url('wikiformat.php', false); ?>">List of events to copy-and-paste into the wiki</a></p>
<?php
if ($user->can_edit_players()) {
?>
<p><a href="<?php echo $or->url('recovertokens.php', false); ?>">List of users' edit URLs</a></p>
<?php
}
$output->call_to_js('init_index_page');
$output->footer($or);
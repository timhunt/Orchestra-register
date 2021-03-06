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
 * Main script. Show the register.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');

$or = new orchestra_register();

$includepast = $or->get_param('past', request::TYPE_BOOL, false, false);
$printview = $or->get_param('print', request::TYPE_BOOL, false, false);

$events = $or->get_events($includepast);
$user = $or->get_current_user();
$series = $or->get_series_list();

if ($printview) {
    $players = $or->get_players(false);
} else {
    $players = $or->get_players(false, $user->id);
}

$or->load_attendance();
list($subtotals, $totalplayers, $totalattending, $sectionplayers, $sectionattending) =
        $or->get_subtotals($events);

list($seriesactions, $systemactions) = $or->get_actions_menus($user, $includepast);

if ($user->is_authenticated()) {
    $savechangesbutton = '<p><input type="submit" name="save" value="Save changes" /></p>';
} else {
    $savechangesbutton = '';
}

$motdheading = $or->get_motd_heading();
$motd = $or->get_motd();

$bodyclass = '';
if ($printview) {
    $bodyclass = 'print';
}

$title = '';
if (count($series) > 1) {
    $title = 'Rehearsals for ' . $series[$or->get_current_seriesid()]->name;
}

$output = $or->get_output();
$output->header($title, $bodyclass);

if ($motdheading || $motd) {
    echo '<div class="motd">';
    if ($motdheading) {
        echo "<h2>$motdheading</h2>\n\n";
    }
    if ($motd) {
        echo $output->markdown($motd);
    }
    echo "</div>\n\n";
}

if (!$printview && $user->is_authenticated()) {
    if ($includepast) {
        $url = 'player.php?past=1';
    } else {
        $url = 'player.php';
    }
?>
<p><a href="<?php echo $or->url($url); ?>">Go to your personal page (better for mobile phones)</a></p>

<form action="<?php echo $or->url('savechoices.php'); ?>" method="post">
<div>

<?php
    echo $output->sesskey_input();
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
<th rowspan="4">Section</th>
<th rowspan="4">Part</th>
<th rowspan="4">Name</th>
<?php
foreach ($events as $event) {
    ?>
<th class="eventname"><?php echo $output->event_link($event); ?></th>
    <?php
}
?>
</tr>
<tr class="headingrow">
<?php
foreach ($events as $event) {
    ?>
<th class="eventdesc"><?php echo htmlspecialchars($event->description); ?></th>
    <?php
}
?>
</tr>
<tr class="headingrow">
<?php
foreach ($events as $event) {
    ?>
<th class="eventvenue"><?php echo htmlspecialchars($event->venue); ?></th>
    <?php
}
?>
</tr>
<tr class="headingrow">
<?php
foreach ($events as $event) {
    ?>
<th class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></th>
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
<td class="sectcol"><?php echo htmlspecialchars($player->section); ?></td>
<td class="partcol"><?php echo htmlspecialchars($player->part); ?></td>
<th><?php echo $output->player_link($player); ?></th>
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
<th colspan="3">Numbers by part</th>
<?php
foreach ($events as $event) {
    ?>
<th><span class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></span></th>
    <?php
}
?>
</tr>
<?php
$rowparity = 1;
foreach ($subtotals as $part => $subtotal) {
    ?>
<tr class="r<?php echo $rowparity = 1 - $rowparity; ?>">
<td class="sectcol"><?php echo htmlspecialchars($subtotal->section); ?></td>
<th class="partcol" colspan="2"><?php echo htmlspecialchars($part); ?></th>
    <?php
    foreach ($events as $event) {
        ?>
<td><?php echo $output->subtotal($subtotal->attending[$event->id], $subtotal->numplayers[$event->id]); ?></td>
        <?php
    }
    ?>
</tr>
<?php
}
?>
<tr class="headingrow">
<th colspan="3">Total numbers</th>
<?php
foreach ($events as $event) {
    ?>
<td><?php echo $output->subtotal($totalattending[$event->id], $totalplayers[$event->id]); ?></td>
    <?php
}
?>
</tr>
<?php

$rowparity = 1;
foreach ($sectionplayers as $section => $eventtotals) {
    ?>
<tr class="sectiontotal r<?php echo $rowparity = 1 - $rowparity; ?>">
<th colspan="3"><?php echo htmlspecialchars($section); ?> total</th>
    <?php
    foreach ($events as $event) {
        ?>
<td><?php echo $output->subtotal($sectionattending[$section][$event->id], $eventtotals[$event->id]); ?></td>
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
    echo $savechangesbutton;
    ?>
</div>
</form>
    <?php
}

if ($printview) {
    ?>
<p>From <?php echo $or->url('', false); ?> at <?php echo strftime('%H:%M, %d %B %Y') ?>.</p>
    <?php

} else {
    echo $output->links_to_other_series($series);

    echo '<h3>Options</h3>';
    echo $seriesactions->output($output);

    if (!$systemactions->is_empty()) {
        echo '<h3>System configuration</h3>';
        echo $systemactions->output($output);
    }

    $output->call_to_js('init_index_page', array(array_keys($events), array_keys($players)));
}

$output->footer();

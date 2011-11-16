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
$series = $or->get_series_list();

if ($user->is_authenticated()) {
    $savechangesbutton = '<p><input type="submit" name="save" value="Save changes" /></p>';
} else {
    $savechangesbutton = '';
}

if ($printview || empty($user->id)) {
    $players = $or->get_players(false);
} else {
    $players = $or->get_players(false, $user->id);
}

$or->load_attendance();
$subtotals = $or->load_subtotals();

$totalplayers = array();
$totalattending = array();
$sectionplayers = array();
$sectionattending = array();
foreach ($events as $event) {
    $totalplayers[$event->id] = 0;
    $totalattending[$event->id] = 0;

    foreach ($subtotals as $part => $subtotal) {
        if ($subtotal->numplayers[$event->id]) {
            $totalplayers[$event->id] += $subtotal->numplayers[$event->id];
            $totalattending[$event->id] += $subtotal->attending[$event->id];

            if (!isset($sectionplayers[$subtotal->section][$event->id])) {
                $sectionplayers[$subtotal->section][$event->id] = 0;
                $sectionattending[$subtotal->section][$event->id] = 0;
            }

            $sectionplayers[$subtotal->section][$event->id] += $subtotal->numplayers[$event->id];
            $sectionattending[$subtotal->section][$event->id] += $subtotal->attending[$event->id];
        }
    }
}

if ($includepast) {
    $showhidepasturl = $or->url('');
    $showhidepastlabel = 'Hide events in the past';
} else {
    $showhidepasturl = $or->url('?past=1');
    $showhidepastlabel = 'Show events in the past';
}

$seriesactions = new actions();
$seriesactions->add($showhidepasturl, $showhidepastlabel);
$seriesactions->add($or->url('?print=1'), 'Printable view');
$seriesactions->add($or->url('ical.php', false), 'Download iCal file (to add the rehearsals into Outlook, etc.)');
$seriesactions->add($or->url('wikiformat.php'), 'List of events to copy-and-paste into the wiki', $user->can_edit_events());
$seriesactions->add($or->url('players.php'), 'Edit the list of players', $user->can_edit_players());
$seriesactions->add($or->url('events.php'), 'Edit the list of events', $user->can_edit_events());
$seriesactions->add($or->url('extractemails.php'), 'Get a list of email addresses', $user->can_edit_users());

$systemactions = new actions();
$systemactions->add($or->url('users.php'), 'Edit the list of users', $user->can_edit_users());
$systemactions->add($or->url('serieslist.php'), 'Edit the list of rehearsal series', $user->can_edit_series());
$systemactions->add($or->url('editmotd.php'), 'Edit introductory message', $user->can_edit_motd());
$systemactions->add($or->url('admin.php'), 'Edit the system configuration', $user->can_edit_config());
$systemactions->add($or->url('logs.php'), 'View the system logs', $user->can_view_logs());

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
<th rowspan="4">Section</th>
<th rowspan="4">Part</th>
<th rowspan="4">Name</th>
<?php
foreach ($events as $event) {
    ?>
<th class="eventname"><?php echo htmlspecialchars($event->name); ?></th>
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
<tr class="headingrow">
<th colspan="3">Total numbers</th>
<?php
foreach ($events as $event) {
    ?>
<td><span class="total"><?php echo $totalattending[$event->id];
        ?></span><span class="outof">/<?php echo $totalplayers[$event->id]; ?></span></td>
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
<td>
        <?php
        if (array_key_exists($event->id, $eventtotals) && $eventtotals[$event->id]) {
            echo '<span class="total">', $sectionattending[$section][$event->id],
                    '</span><span class="outof">/', $eventtotals[$event->id], '</span>';
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

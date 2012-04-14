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
 * Show all the information about a particular player.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');

$or = new orchestra_register();

$includepast = $or->get_param('past', request::TYPE_BOOL, false, false);
$playerid = $or->get_param('id', request::TYPE_INT, 0, false);

$events = $or->get_events($includepast);
$user = $or->get_current_user();
$series = $or->get_series_list();
$sections = $or->get_sections_and_parts();

if (!$playerid) {
    $playerid = $user->id;
}

$players = $or->get_players(false, $playerid);

if (array_key_exists($playerid, $players)) {
    $player = $players[$playerid];
    $editable = $events && $user->can_edit_attendance($player);
} else {
    $player = null;
    $playeruser = $or->get_user($playerid);
    if (!$playeruser) {
        throw new not_found_exception('Unknown user', $playerid);
    }
    $editable = false;
}

$or->load_attendance();
list($subtotals, $totalplayers, $totalattending, $sectionplayers, $sectionattending) =
        $or->get_subtotals($events);

if ($playerid == $user->id) {
    $showhideurl = 'player.php';
} else {
    $showhideurl = 'player.php?id=' . $playerid;
}
list($seriesactions, $systemactions) = $or->get_actions_menus($user, $includepast, false, $showhideurl);

if ($editable) {
    $savechangesbutton = '<p><input type="submit" name="save" value="Save changes" /></p>';
} else {
    $savechangesbutton = '';
}

$motdheading = $or->get_motd_heading();
$motd = $or->get_motd();

if ($playerid == $user->id) {
    $title = 'Your attendance information';
} else if ($player) {
    $title = 'Attendance information for ' . $player->get_public_name();
} else {
    // Player exists, but is not playing in this series.
    $title = 'Attendance information for ' . $playeruser->firstname . ' ' . substr($playeruser->lastname, 0, 1);
}

$output = $or->get_output();
$output->header($title);

if ($playerid == $user->id && ($motdheading || $motd)) {
    echo '<div class="motd">';
    if ($motdheading) {
        echo "<h2>$motdheading</h2>\n\n";
    }
    if ($motd) {
        echo $output->markdown($motd);
    }
    echo "</div>\n\n";
}

if ($editable) {
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

if (!$player) {
    ?>
<p>Not playing in this series of rehearsals.</p>
    <?php

} else if (!$events) {
    ?>
<p>No events found!</p>
    <?php

} else {
    $rowparity = 1;
    foreach ($events as $event) {
        $info = array();
        if ($event->description) {
            $info[] = htmlspecialchars($event->description);
        }
        $info[] = htmlspecialchars($event->venue);
        $info[] = $event->get_nice_datetime();
        ?>
<div class="event r<?php echo $rowparity = 1 - $rowparity; ?>">
<h3 id="event-<?php echo $event->id; ?>"><?php echo $output->event_link($event, 'part-' . $output->make_id($player->part)); ?>
        (<?php echo implode('; ', $info); ?>)</h3>
        <?php

        $attendance = $player->get_attendance($event);
        ?>
<p><?php echo $attendance->get_radio($editable, $user->can_edit_events()); ?></p>
        <?php

        $listopen = false;
        foreach ($players as $otherplayer) {
            if ($otherplayer->id == $player->id) {
                continue;
            }

            if ($otherplayer->part == $player->part) {
                if (!$listopen) {
                    ?>
<h4>Other <?php echo htmlspecialchars($player->part); ?></h4>
<ul class="attendancelist">
                    <?php
                    $listopen = true;
                }

                $attendance = $otherplayer->get_attendance($event);
                ?>
<li class="<?php echo $attendance->status; ?>"><?php echo trim(htmlspecialchars($otherplayer->get_public_name()) .
        ' ' . $attendance->get_symbol()); ?></li>
                <?php
            }
        }

        if ($listopen) {
            ?>
</ul>
            <?php
        }

        foreach ($sectionattending as $section => $attending) {
            $parttotals = array();
            foreach ($sections[$section]->parts as $part) {
                $parttotals[] = htmlspecialchars($part->part) . ' ' . $output->subtotal(
                        $subtotals[$part->part]->attending[$event->id], $subtotals[$part->part]->numplayers[$event->id]);
            }
            ?>
<p><span class="section"><?php echo htmlspecialchars($section); ?></span> <?php echo $output->subtotal(
                $attending[$event->id], $sectionplayers[$section][$event->id]); ?>
                <span class="sectiontotal">(<?php echo implode('; ', $parttotals); ?>)</span></p>
            <?php
        }
        ?>
</div>
        <?php
    }
}

if ($editable) {
    echo $savechangesbutton;
    ?>
</div>
</form>
    <?php
}

echo $output->links_to_other_series($series, $showhideurl);

echo '<h3>Options</h3>';
echo $seriesactions->output($output);

if (!$systemactions->is_empty()) {
    echo '<h3>System configuration</h3>';
    echo $systemactions->output($output);
}

$output->call_to_js('init_player_page', array(array_keys($events), array_keys($players)));

$output->footer();

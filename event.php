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
 * Show all the information about a particular event.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');

$or = new orchestra_register();

$eventid = $or->get_param('id', request::TYPE_INT, 0, false);
if (!$eventid) {
    $events = $or->get_events(true);
    $now = time();
    $event = null;
    foreach ($events as $event) {
        if ($event->timestart > $now) {
            break;
        }
    }

} else {
    $event = $or->get_event($eventid);
    $or->set_series_id($event->seriesid);
}

$user = $or->get_current_user();
$series = $or->get_series_list();

$players = $or->get_players(false, $user->id);

$or->load_attendance();
if ($event) {
    list($subtotals, $totalplayers, $totalattending, $sectionplayers, $sectionattending) =
            $or->get_subtotals(array($event));
}

list($seriesactions, $systemactions) = $or->get_actions_menus($user, false, false, false);

if ($event) {
    $previousevent = $or->get_previous_event($event->id);
    $nextevent = $or->get_next_event($event->id);
    $title = htmlspecialchars($event->name);
} else {
    $previousevent = null;
    $nextevent = null;
    $title = 'No events!';
    $players = array();
}

$output = $or->get_output();
$output->header($title);

if ($event) {
    if ($event->description) {
        ?>
    <p class="eventdesc"><?php echo htmlspecialchars($event->description); ?></p>
        <?php
    }
    ?>
    <p class="eventvenue"><?php echo htmlspecialchars($event->venue); ?></p>
    <p class="eventdatetime"><?php echo $event->get_nice_datetime(); ?></p>
    <?php
}

echo $output->previous_next_links($previousevent, $nextevent);

if (!$players) {
    ?>
        <p class="eventdesc">No players!</p>
    <?php


} else {
    $currentsection = null;
    $currentpart = null;
    $listopen = false;
    foreach ($players as $player) {
        if ($currentsection != $player->section) {
            if ($listopen) {
                echo "</ul>\n";
                $listopen = false;
            }
            $currentsection = $player->section
            ?>
<h3 id="section-<?php echo $output->make_id($currentsection); ?>"><?php echo htmlspecialchars($currentsection); ?> <?php echo $output->subtotal(
                $sectionattending[$currentsection][$event->id],
                $sectionplayers[$currentsection][$event->id]); ?></h3>
            <?php
        }

        if ($currentpart != $player->part) {
            if ($listopen) {
                echo "</ul>\n";
                $listopen = false;
            }
            $currentpart = $player->part
            ?>
<h4 id="part-<?php echo $output->make_id($currentpart); ?>"><?php echo htmlspecialchars($currentpart); ?> <?php echo $output->subtotal(
                $subtotals[$currentpart]->attending[$event->id],
                $subtotals[$currentpart]->numplayers[$event->id]); ?></h4>
            <?php
        }

        if (!$listopen) {
            echo '<ul class="attendancelist">', "\n";
            $listopen = true;
        }

        $attendance = $player->get_attendance($event);
        ?>
<li class="<?php echo $attendance->status; ?>"><?php echo trim(htmlspecialchars($player->get_public_name()) .
            ' ' . $attendance->get_symbol()); ?></li>
        <?php
    }
    if ($listopen) {
        echo "</ul>\n";
        $listopen = false;
    }
}

echo $output->previous_next_links($previousevent, $nextevent);
?>
<p><a href="<?php echo $or->url(''); ?>">Show the full register</a></p>
<?php
echo $output->links_to_other_series($series, 'event.php');

echo '<h3>Options</h3>';
echo $seriesactions->output($output);

if (!$systemactions->is_empty()) {
    echo '<h3>System configuration</h3>';
    echo $systemactions->output($output);
}

$output->call_to_js('init_event_page');

$output->footer();

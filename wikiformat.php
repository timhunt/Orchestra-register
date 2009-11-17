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
 * Export the list of events in a format for the wiki.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib/core.php');
$or = new orchestra_register();
$events = $or->get_events();

$content = '';
$month = '';
foreach ($events as $event) {
    $nextmonth = strftime('%B', $event->timestart);
    if ($nextmonth != $month) {
        $content .= "\n===" . $nextmonth . "===\n\n";
    }
    $month = $nextmonth;
    $content .= '* ' . $event->get_nice_datetime() . ', ' . $event->venue . "\n";
}

$wikiediturl = $or->get_wiki_edit_url();

$output = new html_output($or);
$output->header('Export events for the wiki');
if ($wikiediturl) {
    echo '<p>Copy the rehearsal list below to the <a href="' . $wikiediturl .
            '">Orchestra Rehearsals page</a>.</p>';
}
echo '<textarea id="wikimarkup" readonly="readonly" cols="80" rows="25">' . $content . '</textarea>';
echo '<p><a href="' . $or->url('') . '">Back to the register</a></p>';
$output->call_to_js('init_wiki_format_page');
$output->footer();

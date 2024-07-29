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
 * A page to add or edit a rehearsal series.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');
require_once(__DIR__ . '/lib/forms.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_series()) {
    throw new permission_exception('You don\'t have permission to edit rehearsal series.');
}

$seriesid = $or->get_param('id', request::TYPE_INT, 0, false);

if ($seriesid) {
    $series = $or->get_series($seriesid);
    $title = 'Edit a rehearsal series';
    $submitlabel = 'Save changes';
    $url = $or->url('editseries.php?id=' . $seriesid, false, false);

} else {
    $series = new series();
    $title = 'Add a rehearsal series';
    $submitlabel = 'Create rehearsal series';
    $url = $or->url('editseries.php', false, false);
    $existingseries = $or->get_series_options();
    $existingseries[0] = 'Do not copy';
    $series->copyplayersfrom = 0;
}

$form = new form($url, $submitlabel);
$form->add_field(new text_field('name', 'Event name', request::TYPE_RAW));
$form->add_field(new text_field('description', 'Description', request::TYPE_RAW));
if (!$seriesid) {
    $form->add_field(new select_field('copyplayersfrom', 'Copy the list of players from', $existingseries));
}
$form->set_required_fields('name');

$form->set_initial_data($series);
$form->parse_request($or);

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('series.php');

    case form::SUBMITTED:
        $newseries = $form->get_submitted_data('series');

        if ($seriesid) {
            $newseries->id = $seriesid;
            $or->update_series($newseries);
            $or->log('edit series ' . $newseries->id);

        } else {
            $or->create_series($newseries);
            $or->log('add series ' . $newseries->id);

            if (!$seriesid && $newseries->copyplayersfrom) {
                $or->copy_players_between_series($newseries->copyplayersfrom, $newseries->id);
            }
        }

        $or->redirect('series.php');
}

$output = $or->get_output();
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_series_page');
$output->footer();

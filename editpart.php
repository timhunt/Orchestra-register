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
 * A page to edit the definitino of a part.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');
require_once(__DIR__ . '/lib/forms.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_parts()) {
    throw new permission_exception('You don\'t have permission to edit parts.');
}

$part = $or->get_param('part', request::TYPE_ALNUMSPACE, null, false);
if ($part) {
    if (!$or->is_valid_part($part)) {
        throw new not_found_exception('Unknown part.');
    }
} else {
    $section = $or->get_param('section', request::TYPE_ALNUMSPACE, null, false);
    if (!$or->is_valid_section($section)) {
        throw new not_found_exception('Either part or section must be specified.');
    }
}

if ($part) {
    $title = 'Rename part ' . $part;
    $submitlabel = 'Save changes';
    $url = $or->url('editpart.php?part=' . $part, false, false);

} else {
    $title = 'Add a part to section ' . $section;
    $submitlabel = 'Create part';
    $url = $or->url('editpart.php?section=' . $section, false, false);
}

$form = new form($url, $submitlabel);
$form->add_field(new text_field('partname', 'New part name', request::TYPE_ALNUMSPACE));
$form->set_required_fields('partname');

$form->set_initial_data(['partname' => $part]);
$form->parse_request($or);

$newpart = $form->get_field_value('partname');
if ($newpart != $part && $or->is_valid_part($newpart)) {
    $form->set_field_error('partname', 'There is already a part with this name');
}

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('parts.php');

    case form::SUBMITTED:
        $data = $form->get_submitted_data('stdClass');
        $partname = $data->partname;

        if ($part) {
            if ($partname != $part) {
                $or->rename_part($part, $partname);
            }
            $or->log('rename part ' . $part . ' to ' . $partname);

        } else {
            $or->create_part($section, $partname);
            $or->log('add part ' . $partname . ' to section ' . $section);
        }

        $or->redirect('parts.php');
}

$output = $or->get_output();
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_part_page');
$output->footer();

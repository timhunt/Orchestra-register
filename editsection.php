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
 * A page to edit the definitino of a section.
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
    throw new permission_exception('You don\'t have permission to edit sections.');
}

$section = $or->get_param('section', request::TYPE_ALNUMSPACE, null, false);
if ($section) {
    if (!$or->is_valid_section($section)) {
        throw new not_found_exception('Unknown section.');
    }
}

if ($section) {
    $title = 'Rename section ' . $section;
    $submitlabel = 'Save changes';
    $url = $or->url('editsection.php?section=' . $section, false, false);

} else {
    $title = 'Add a section';
    $submitlabel = 'Create section';
    $url = $or->url('editsection.php', false, false);
}

$form = new form($url, $submitlabel);
$form->add_field(new text_field('sectionname', 'New section name', request::TYPE_ALNUMSPACE));
$form->set_required_fields('sectionname');

$form->set_initial_data(array('sectionname' => $section));
$form->parse_request($or);

$newsection = $form->get_field_value('sectionname');
if ($newsection != $section && $or->is_valid_section($newsection)) {
    $form->set_field_error('sectionname', 'There is already a section with this name');
}

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('parts.php');
        break;

    case form::SUBMITTED:
        $data = $form->get_submitted_data('stdClass');
        $sectionname = $data->sectionname;

        if ($section) {
            if ($sectionname != $section) {
                $or->rename_section($section, $sectionname);
            }
            $or->log('rename section ' . $section . ' to ' . $sectionname);

        } else {
            $or->create_section($sectionname);
            $or->log('add section ' . $sectionname);
        }

        $or->redirect('parts.php');
        break;
}

$output = $or->get_output();
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_section_page');
$output->footer();

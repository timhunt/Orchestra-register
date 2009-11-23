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
 * A page to edit the introductory message.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/form.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_motd()) {
    throw new permission_exception('You don\'t have permission to edit the introductory message.');
}

$form = new form($or->url('editmotd.php'));
$form->add_field(new text_field('motdheading', 'Heading', request::TYPE_RAW));
$form->add_field(new textarea_field('motd', 'Message', request::TYPE_RAW, 10, 50));
$form->get_field('motd')->set_note('Uses <a href="http://daringfireball.net/projects/markdown/syntax">MarkDown</a> formatting.');

$current = array(
    'motdheading' => $or->get_motd_heading(),
    'motd' => $or->get_motd(),
);

$form->set_initial_data($current);

switch ($form->parse_request($or)) {
    case form::CANCELLED:
        $or->redirect('');

    case form::SUBMITTED:
        foreach (array('motdheading', 'motd') as $field) {
            $newvalue = $form->get_field_value($field);
            if ($newvalue != $current[$field]) {
                $or->set_config($field, $newvalue);
                $or->log('set config variable ' . $field . ' to ' . $newvalue);
            }
        }

        $or->redirect('');
}

$output = $or->get_output();
$output->header('Edit introductory message');
echo $form->output($output);
$output->call_to_js('init_editmotd_page');
$output->footer();

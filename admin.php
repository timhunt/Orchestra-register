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
 * A page to edit the system configuration.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/forms.php');
$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_config()) {
    throw new permission_exception('You don\'t have permission to edit the system configuration.');
}

$currentconfig = $or->get_config();

$fields = array('title', 'defaultseriesid', 'timezone', 'helpurl', 'wikiediturl', 'icaleventnameprefix');

$form = new form($or->url('admin.php'));
$form->add_field(new text_field('title', 'Register title', request::TYPE_RAW));
$form->add_field(new select_field('defaultseriesid', 'Current rehearsal series', $or->get_series_options()));
$form->add_field(new timezone_field('timezone', 'Time zone'));
$form->add_field(new text_field('helpurl', 'Help URL', request::TYPE_RAW));
$form->add_field(new text_field('wikiediturl', 'Rehearsals wiki page edit URL', request::TYPE_RAW));
$form->add_field(new text_field('icaleventnameprefix', 'Prefix to add to event names when exporting as iCal', request::TYPE_RAW));
$form->set_required_fields('title');
$form->get_field('helpurl')->set_note('Could be a mailto: or a http: url');

$form->set_initial_data($currentconfig);

switch ($form->parse_request($or)) {
    case form::CANCELLED:
        $or->redirect('');

    case form::SUBMITTED:
        foreach ($fields as $field) {
            $newvalue = $form->get_field_value($field);
            if ($newvalue != $currentconfig->$field) {
                $or->set_config($field, $newvalue);
                $or->log('set config variable ' . $field . ' to ' . $newvalue);
            }
        }

        $or->redirect('');
}

$output = $or->get_output();
$output->header('Edit system configuration');
echo $form->output($output);
$output->call_to_js('init_admin_page');
echo '<p>This is ' . $or->version_string() . '</p>';
$output->footer();

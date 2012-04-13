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
 * A page to edit or create a user.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/forms.php');
$or = new orchestra_register();
$series = $or->get_series_list();
$parts = $or->get_parts(true);

$currentuser = $or->get_current_user();
if (!$currentuser->can_edit_users()) {
    throw new permission_exception('You don\'t have permission to edit users.');
}

$userid = $or->get_param('id', request::TYPE_INT, 0, false);

if ($userid) {
    $user = $or->get_user($userid, true);
    $title = 'Edit a user';
    $submitlabel = 'Save changes';
    $url = $or->url('edituser.php?id=' . $userid, false, false);

    $userparts = $or->get_player_parts($user);
    foreach ($series as $s) {
        if (array_key_exists($s->id, $userparts)) {
            $field = 'part' . $s->id;
            $user->$field = $userparts[$s->id]->part;
        }
    }

} else {
    $user = new user();
    $title = 'Add a user';
    $submitlabel = 'Create user';
    $url = $or->url('edituser.php', false, false);
    $userparts = array();
}

$assignableroles = $currentuser->assignable_roles($userid);

$form = new form($url, $submitlabel);
$form->add_field(new text_field('firstname', 'First name', request::TYPE_RAW));
$form->add_field(new text_field('lastname', 'Last name', request::TYPE_RAW));
$form->add_field(new text_field('email', 'Email', request::TYPE_EMAIL));

if ($assignableroles) {
    $form->add_field(new select_field('role', 'Role', $assignableroles));
    $form->get_field('role')->set_note('Controls what this person is allowed to do');
}

if ($currentuser->can_edit_password($userid)) {
    $form->add_field(new password_field('changepw', 'New password', request::TYPE_RAW));
    $form->add_field(new password_field('confirmchangepw', 'Comfirm new password', request::TYPE_RAW));
    $form->get_field('changepw')->set_note('Leave blank for no change');
}

foreach ($series as $s) {
    $form->add_field(new group_select_field('part' . $s->id, $s->name, $parts, 0));
}

$form->set_required_fields('firstname', 'lastname', 'email');
$form->set_initial_data($user);
$form->parse_request($or);
if ($currentuser->can_edit_password($userid) && $form->get_field_value('changepw') != $form->get_field_value('confirmchangepw')) {
    $form->set_field_error('changepw', '');
    $form->set_field_error('confirmchangepw', 'The two passwords did not match');
}

switch ($form->get_outcome()) {
    case form::CANCELLED:
        $or->redirect('users.php');

    case form::SUBMITTED:
        $newuser = $form->get_submitted_data('user');

        if ($userid) {
            $newuser->id = $userid;
            if (!$assignableroles) {
                $newuser->role = $user->role;
            }
            $or->update_user($newuser);
            $or->log('edit user ' . $newuser->id);

        } else {
            $or->create_user($newuser);
            $or->log('add user ' . $newuser->id);
        }

        if ($currentuser->can_edit_password($userid) &&
                ($newpassword = $form->get_field_value('changepw'))) {
            $or->set_user_password($newuser->id, $newpassword);
            $or->log('change password ' . $newuser->id);
        }

        foreach ($series as $s) {
            $field = 'part' . $s->id;
            $newpart = $newuser->$field;

            if (!$or->is_valid_part($newpart)) {
                continue;
            }

            if ($newpart === '0') {
                $newpart = null;
            }
            if (array_key_exists($s->id, $userparts) && $newpart == $userparts[$s->id]->part) {
                continue;
            }

            $or->set_player_part($newuser, $newpart, $s->id);
        }

        $or->redirect('users.php');
}

$output = $or->get_output();
$output->header($title);
echo $form->output($output);
$output->call_to_js('init_edit_user_page');
$output->footer();

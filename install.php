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
 * Installer scrip.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/lib/form.php');

if (!is_readable(dirname(__FILE__) . '/config.php')) {
    throw new register_exception('In order to install Orchestra register, ' .
            'you must create the file config.php, based on the model provided by config-example.php.');
}

$or = new orchestra_register(false);

if ($or->get_config()) {
    throw new register_exception('Already installed.');
}
$or->set_default_config();

$form = new form($or->url('install.php', false, false, 'none'), 'Install Orchestra register', false);
$form->add_field(new text_field('firstname', 'Admin user first name', request::TYPE_RAW));
$form->add_field(new text_field('lastname', 'Admin user last name', request::TYPE_RAW));
$form->add_field(new text_field('email', 'Admin user email', request::TYPE_EMAIL));
$form->add_field(new password_field('pw', 'Admin user password', request::TYPE_RAW));
$form->add_field(new password_field('confirmpw', 'Comfirm admin user password', request::TYPE_RAW));

$form->set_required_fields('firstname', 'lastname', 'email', 'pw', 'confirmpw');
$form->parse_request($or);
if ($form->get_field_value('pw') != $form->get_field_value('confirmpw')) {
    $form->set_field_error('pw', '');
    $form->set_field_error('confirmpw', 'The two passwords did not match');
}

switch ($form->get_outcome()) {
    case form::SUBMITTED:
        $admin = $form->get_submitted_data('user');
        $admin->role = user::ADMIN;

        $or->install();
        $or->create_user($admin);
        $or->set_user_password($admin->id, $admin->pw);
        $or->verify_login($admin->email, $admin->pw);
        $or->log('install ' . $or->version_string());
        $or->redirect('admin.php');
}

$output = $or->get_output();
$output->header('Preparing to install Orchestra register', '', false);
echo $form->output($output);
$output->footer();

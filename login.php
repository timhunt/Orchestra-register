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
 * Login script. Show form, then validate submitted data,
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');

$or = new orchestra_register();

if ($or->get_param('cancel', request::TYPE_BOOL)) {
    $or->redirect('');
}

$email = $or->get_param('email', request::TYPE_RAW);
$password = $or->get_param('password', request::TYPE_RAW);

$ok = null;
if (!is_null($email) && !is_null($password)) {
    $ok = $or->verify_login($email, $password);
    if ($ok) {
        $or->log('log in');
        $or->redirect('');

    } else {
        $or->log_failed_login($email);
    }
}

$or->refresh_sesskey();

$output = $or->get_output();
$output->header('Login');

if ($ok === false) {
    echo '<p class="error">Email and or password not recognised.</p>';
}

?>
<form action="<?php echo $or->url('login.php'); ?>" method="post">
<div>
<?php echo $output->sesskey_input(); ?>
<p><label for="email">Email</label>: <input type="text" size="50" name="email" id="email" value="<?php echo htmlspecialchars($or->get_param('email', '//')); ?>" /></p>
<p><label for="password">Password</label>: <input type="password" size="50" name="password" id="password" /></p>

<p><input type="submit" value="Log in" /> <input type="submit" name="cancel" value="Cancel" /></p>

</div>
</form>
<?php

$output->call_to_js('init_login_page');
$output->footer();

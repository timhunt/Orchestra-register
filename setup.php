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
 * This file includes the main libraries.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/lib/exceptions.php');

set_exception_handler('early_exception_handler');

require_once(__DIR__ . '/lib/orchestra_register.php');
require_once(__DIR__ . '/lib/request.php');
require_once(__DIR__ . '/lib/user.php');
require_once(__DIR__ . '/lib/sys_config.php');
require_once(__DIR__ . '/lib/db_config.php');
require_once(__DIR__ . '/lib/version.php');
require_once(__DIR__ . '/lib/series.php');
require_once(__DIR__ . '/lib/event.php');
require_once(__DIR__ . '/lib/player.php');
require_once(__DIR__ . '/lib/attendance.php');
require_once(__DIR__ . '/lib/actions.php');
require_once(__DIR__ . '/lib/database_connection.php');
require_once(__DIR__ . '/lib/transaction.php');
require_once(__DIR__ . '/lib/database.php');
require_once(__DIR__ . '/lib/html_output.php');
require_once(__DIR__ . '/lib/email.php');

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
 * Example configuration file.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Information required to connect to the database.
$config->dbhost = 'localhost'; // For example 'localhost' or 'db.isp.com'
$config->dbuser = 'username';  // Your database username
$config->dbpass = 'password';  // Your database password
$config->dbname = 'register';  // Your database name, for example 'register';

// Web site location - what will the URL of this register be?
$config->wwwroot = 'http://my.server.com/register/'; // Must include the final /.

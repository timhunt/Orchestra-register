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
 * Class holding the data that comes from the config.php file.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sys_config {
    public $dbhost;
    public $dbuser;
    public $dbpass;
    public $dbname;

    public $wwwroot;

    public $pwsalt;

    public function check() {
        if (empty($this->wwwroot)) {
            throw new configuration_exception('Web site location ($config->wwwroot) not set in config.php.');
        }
        if (empty($this->pwsalt) || $this->pwsalt === '0123456789012345678901234567890123456789') {
            throw new configuration_exception('Password salt ($config->pwsalt) not set in config.php.');
        }
        if (strlen($this->pwsalt) < 40) {
            throw new configuration_exception('Password salt ($config->pwsalt) set in config.php must be at least 40 characters long.');
        }
    }
}

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
 * Class holding the configuration options that are stored in the database.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_config {
    public $version;

    public $changesesskeyonloginout = 0;

    public $icalguid;
    public $icaleventnameprefix = '';

    public $title = 'Orchestra Register';
    public $timezone = 'Europe/London';

    public $helpurl = null;
    public $wikiediturl = null;

    public $motdheading = '';
    public $motd = '';

    public $defaultseriesid;

    /** @var bool whether the system is in maitenance mode. */
    public $maintenancemode = false;

    protected $propertynames = null;

    /**
     * Is this a config property that can be set by the admin?
     * @param string $name
     * @return boolean
     */
    public function is_settable_property($name) {
        if (in_array($name, array('icalguid', 'version'))) {
            return false;
        }

        if (is_null($this->propertynames)) {
            $class = new ReflectionClass('db_config');
            $this->propertynames = array();
            foreach ($class->getProperties() as $property) {
                $this->propertynames[] = $property->name;
            }
        }

        return in_array($name, $this->propertynames);
    }
}

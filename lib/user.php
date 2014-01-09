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
 * Class holding the data about one user.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user {
    const AUTH_NONE = 0;
    const AUTH_TOKEN = 10;
    const AUTH_LOGIN = 20;
    const DISABLED = 'disabled';
    const PLAYER = 'player';
    const ORGANISER = 'organiser';
    const ADMIN = 'admin';
    protected static $roles = array(
        self::DISABLED => 'Disabled',
        self::PLAYER => 'Ordinary player',
        self::ORGANISER => 'Committee member',
        self::ADMIN => 'Administrator',
    );
    /** @var player */
    public $id;
    public $firstname;
    public $lastname;
    public $email;
    public $authkey;
    public $username;
    public $pwhash;
    public $pwsalt;
    public $role = user::PLAYER;
    public $authlevel = self::AUTH_NONE;
    public $sesskey;
    public $maintenancemode = false;
    public function __construct() {
        if (!empty($_SESSION) && array_key_exists('sesskey', $_SESSION)) {
            $this->sesskey = $_SESSION['sesskey'];
        } else {
            $this->refresh_sesskey();
        }
    }
    public static function auth_name($level) {
        switch ($level) {
            case self::AUTH_NONE:
                return 'None';
            case self::AUTH_TOKEN:
                return 'Token';
            case self::AUTH_LOGIN:
                return 'Logged in';
        }
    }
    public function refresh_sesskey() {
        $this->sesskey = database::random_string(40);
        $_SESSION['sesskey'] = $this->sesskey;
    }

    public function can_edit_attendance($player) {
        return ($this->authlevel >= self::AUTH_TOKEN &&
                        $this->id == $player->id && !$this->maintenancemode) ||
                $this->is_organiser_level_access();
    }
    public function can_edit_users() {
        return $this->is_organiser_level_access();
    }
    public function can_edit_players() {
        return $this->is_organiser_level_access();
    }
    public function can_edit_series() {
        return $this->is_organiser_level_access();
    }
    public function can_edit_events() {
        return $this->is_organiser_level_access();
    }
    public function can_edit_motd() {
        return $this->is_organiser_level_access();
    }
    public function can_edit_password($userid) {
        return ($this->authlevel >= self::AUTH_LOGIN &&
                        $this->id == $userid && !$this->maintenancemode) ||
                $this->is_admin_level_access();
    }
    public function can_edit_parts() {
        return $this->is_admin_level_access();
    }
    public function can_edit_config() {
        return $this->is_admin_level_access_allow_in_maintenance_mode();
    }
    public function can_view_logs() {
        return $this->is_admin_level_access_allow_in_maintenance_mode();
    }

    protected function is_admin_level_access_allow_in_maintenance_mode() {
        return $this->authlevel >= self::AUTH_LOGIN && $this->is_admin();
    }
    protected function is_admin_level_access() {
        return $this->authlevel >= self::AUTH_LOGIN && $this->is_admin() && !$this->maintenancemode;
    }
    protected function is_organiser_level_access() {
        return $this->authlevel >= self::AUTH_LOGIN && $this->is_organiser() && !$this->maintenancemode;
    }

    public function is_logged_in() {
        return $this->authlevel >= self::AUTH_LOGIN;
    }
    public function is_authenticated() {
        return $this->authlevel >= self::AUTH_TOKEN;
    }

    protected function is_organiser() {
        return in_array($this->role, array(self::ORGANISER, self::ADMIN));
    }
    protected function is_admin() {
        return $this->role == self::ADMIN;
    }

    public function get_name() {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function assignable_roles($userid) {
        if ($this->authlevel < self::AUTH_LOGIN || !$this->is_admin() ||
                $this->id == $userid) {
            return array();
        }
        return self::$roles;
    }
    public static function get_all_roles() {
        return self::$roles;
    }
}

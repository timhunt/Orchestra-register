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
 * Library functions.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/data.php');
require_once(dirname(__FILE__) . '/output.php');

class permission_exception extends Exception {
    
}

class orchestra_register {
    private $user;
    private $players = null;
    private $events = null;
    private $parts = null;
    private $request;
    private $sysconfig;
    private $config;
    private $version;
    private $db;
    private $attendanceloaded = false;

    public function __construct() {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        $config = new sys_config();
        include(dirname(__FILE__) . '/../config.php');
        $this->sysconfig = $config;

        $version = new version();
        include(dirname(__FILE__) . '/../version.php');
        $this->version = $version;

        $this->db = new database($this->sysconfig->dbhost, $this->sysconfig->dbuser,
                $this->sysconfig->dbpass, $this->sysconfig->dbname);
        $this->db->check_installed();

        $this->config = $this->db->load_config();

        $this->request = new request();

        session_start();

        date_default_timezone_set($this->config->timezone);
    }

    public function get_request() {
        return $this->request;
    }

    public function get_player($id, $includedeleted = false) {
        return $this->db->find_player_by_id($id, $includedeleted);
    }

    public function get_players($includedeleted = false, $currentsection = '', $currentpart = '') {
        if (is_null($this->players)) {
            $this->players = $this->db->load_players($includedeleted, $currentsection, $currentpart);
        }
        return $this->players;
    }

    public function get_events($includepast = false) {
        if (is_null($this->events)) {
            $this->events = $this->db->load_events($includepast);
        }
        return $this->events;
    }

    public function get_parts() {
        if (is_null($this->parts)) {
            $partsdata = $this->db->load_parts();
            $this->parts = array();
            foreach ($partsdata as $part) {
                $this->parts[$part->section][$part->part] = $part->part;
            }
        }
        return $this->parts;
    }

    public function load_attendance() {
        if ($this->attendanceloaded) {
            return;
        }
        $attendances = $this->db->load_attendances();
        foreach ($attendances as $a) {
            $this->players[$a->playerid]->attendance[$a->eventid] = $a;
        }
        $this->attendanceloaded = true;
    }

    public function load_subtotals() {
        $data = $this->db->load_subtotals();
        $subtotals = array();
        foreach ($data as $row) {
            if (!array_key_exists($row->part, $subtotals)) {
                $subtotal = new stdClass;
                $subtotal->section = $row->section;
                $subtotal->attending = array();
                $subtotal->numplayers = array();
                $subtotals[$row->part] = $subtotal;
            }
            $subtotals[$row->part]->attending[$row->eventid] = $row->attending;
            $subtotals[$row->part]->numplayers[$row->eventid] = $row->numplayers;
        }
        return $subtotals;
    }

    public function set_attendance($player, $event, $newattendance) {
        $this->db->set_attendance($player->id, $event->id, $newattendance);
    }

    public function delete_player($player) {
        $this->db->set_player_deleted($player->id, 1);
    }

    public function undelete_player($player) {
        $this->db->set_player_deleted($player->id, 0);
    }

    public function get_title() {
        return $this->config->title;
    }

    public function get_login_info() {
        $this->get_current_user();
        if ($this->user->is_logged_in()) {
            return 'You are logged in as ' . $this->user->get_name() .
                    '. <a href="' . $this->url('logout.php', false) . '">Log out</a>.';
        } else {
            return 'You are not logged in. <a href="' . $this->url('login.php') . '">Log in</a>.';
        }
    }

    public function version_string() {
        return 'Orchestra Register ' . $this->version->name . ' (' . $this->version->id .
                ') <a href="doc/">Documentation</a>';
    }

    public function get_event_guid($event) {
        return 'event' . $event->id . '@' . $this->config->icalguid;
    }

    public function url($relativeurl, $withtoken = true, $xmlescape = true) {
        $extra = '';
        if ($withtoken && empty($_SESSION['userid']) && !empty($this->user->player->authkey)) {
            $extra = 't=' . $this->user->player->authkey;
            if (strpos($relativeurl, '?') !== false) {
                $extra = '&' . $extra;
            } else {
                $extra = '?' . $extra;
            }
        }
        $url = $this->sysconfig->wwwroot . $relativeurl . $extra;
        if ($xmlescape) {
            return htmlspecialchars($url);
        } else {
            return $url;
        }
    }

    public function get_param($name, $type, $default = null, $postonly = true) {
        return $this->request->get_param($name, $type, $default, $postonly);
    }

    public function require_sesskey() {
        if ($this->get_sesskey() != $this->get_param('sesskey', request::TYPE_AUTHTOKEN)) {
            throw new Exception('Invalid request (sesskey does not match)');
        }
    }

    public function refresh_sesskey() {
        $this->get_current_user()->refresh_sesskey();
    }

    public function get_sesskey() {
        return $this->get_current_user()->sesskey;
    }

    public function verify_login() {
        $email = $this->request->get_param('email', request::TYPE_EMAIL);
        $password = $this->request->get_param('password', request::TYPE_RAW);
        if (is_null($email) || is_null($password)) {
            return null;
        }
        $this->require_sesskey();
        $player = $this->db->check_user_auth($email, $this->config->pwsalt . $password);
        if ($player) {
            session_regenerate_id(true);
            $_SESSION['userid'] = $player->id;
            return true;
        }
        return false;
    }

    public function logout() {
        unset($_SESSION['userid']);
        session_regenerate_id(true);
        $this->refresh_sesskey();
    }

    /** @return user */
    public function get_current_user() {
        if ($this->user) {
            return $this->user;
        }
        $this->user = new user();
        if (!empty($_SESSION['userid'])) {
            $player = $this->db->find_player_by_id($_SESSION['userid']);
            if ($player) {
                $this->user->player = $player;
                $this->user->authlevel = user::AUTH_LOGIN;
                return $this->user;
            }
        }
        $token = $this->request->get_param('t', request::TYPE_AUTHTOKEN, null, false);
        if ($token) {
            $player = $this->db->find_player_by_token($token);
            if ($player) {
                $this->user->player = $player;
                $this->user->authlevel = user::AUTH_TOKEN;
                return $this->user;
            }
        }
        return $this->user;
    }

    public function redirect($relativeurl, $withtoken = true) {
        header('HTTP/1.1 303 See Other');
        header('Location: ' . $this->url($relativeurl, $withtoken, false));
        exit(0);
    }

    public function get_wiki_edit_url() {
        return $this->config->wikiediturl;
    }
}

class request {
    const TYPE_INT = 1;
    const TYPE_ATTENDANCE = 2;
    const TYPE_EMAIL = 3;
    const TYPE_BOOL = 4;
    const TYPE_RAW = 666;
    const TYPE_AUTHTOKEN = '/[a-zA-Z0-9]{40}/';
    public function get_param($name, $type, $default = null, $postonly = true) {
        if (array_key_exists($name, $_POST)) {
            $raw = $_POST[$name];
        } else if (!$postonly && array_key_exists($name, $_GET)) {
            $raw = $_GET[$name];
        } else {
            return $default;
        }
        if ($type == self::TYPE_BOOL) {
            return $raw !== '' && $raw !== '0' && $raw !== 'false' && $raw !== 'no';
        }
        if ($this->validate($raw, $type)) {
            return $raw;
        } else {
            return $default;
        }
    }

    public function validate($raw, $type) {
        switch ($type) {
            case self::TYPE_INT:
                return strval(intval($raw)) === $raw;
            case self::TYPE_ATTENDANCE:
                return array_key_exists($raw, attendance::$symbols);
            case self::TYPE_EMAIL:
                return filter_var($raw, FILTER_VALIDATE_EMAIL);
            case self::TYPE_RAW:
                return true;
            default:
                return preg_match($type, $raw);
        }
    }
}

class user {
    const AUTH_NONE = 0;
    const AUTH_TOKEN = 10;
    const AUTH_LOGIN = 20;
    const PLAYER = 'player';
    const ORGANISER = 'organiser';
    const ADMIN = 'admin';
    protected static $roles = array(
        self::PLAYER => 'Ordinary player',
        self::ORGANISER => 'Committee member',
        self::ADMIN => 'Administrator',
    );
    /** @var player */
    public $player;
    public $authlevel = self::AUTH_NONE;
    public $sesskey;
    public function __construct() {
        if (array_key_exists('sesskey', $_SESSION)) {
            $this->sesskey = $_SESSION['sesskey'];
        } else {
            $this->refresh_sesskey();
        }
    }
    public function refresh_sesskey() {
        $this->sesskey = database::random_string(40);
        $_SESSION['sesskey'] = $this->sesskey;
    }
    public function can_edit_attendance($player) {
        return ($this->authlevel >= self::AUTH_TOKEN && $this->player->id == $player->id) ||
                $this->authlevel >= self::AUTH_LOGIN && $this->is_organiser();
    }
    public function can_edit_players() {
        return $this->authlevel >= self::AUTH_LOGIN && $this->is_organiser();
    }
    public function is_logged_in() {
        return $this->authlevel >= self::AUTH_LOGIN;
    }
    protected function is_organiser() {
        return in_array($this->player->role, array(self::ORGANISER, self::ADMIN));
    }
    public function get_name() {
        return $this->player->get_name();
    }
    public function assignable_roles() {
        $assignableroles = array();
        foreach (self::$roles as $role => $name) {
            $assignableroles[$role] = $name;
        }
        return $assignableroles;
    }
}

class sys_config {
    public $dbhost;
    public $dbuser;
    public $dbpass;
    public $dbname;

    public $wwwroot;
}

class db_config {
    public $pwsalt;

    public $icalguid;

    public $title = 'OU Orchestra Register';
    public $timezone = 'Europe/London';

    public $wikiediturl = 'http://www.open.ac.uk/wikis/ouocmc/index.php?title=Orchestra_rehearsals&action=edit';
}

class version {
    public $id;
    public $name;
}

class event {
    const DATE_FORMAT = '%a %e %b';
    const TIME_FORMAT = '%H:%M';
    public $id;
    public $name;
    public $description;
    public $venue;
    public $timestart;
    public $timeend;
    public $timemodified;

    public function get_nice_datetime() {
        $startdate = strftime(self::DATE_FORMAT, $this->timestart);
        $enddate = strftime(self::DATE_FORMAT, $this->timeend);
        $starttime = strftime(self::TIME_FORMAT, $this->timestart);
        $endtime = strftime(self::TIME_FORMAT, $this->timeend);
        if ($startdate == $enddate) {
            return $starttime . ' - ' . $endtime . ', ' . $startdate;
        } else {
            return $starttime . ', ' . $startdate . ' - ' . $endtime . ', ' . $enddate;
        }
    }
}

class player {
    public $id;
    public $firstname;
    public $lastname;
    public $email;
    public $part;
    public $section;
    public $authkey;
    public $username;
    public $pwhash;
    public $pwsalt;
    public $role = user::PLAYER;
    public $deleted = 0;
    public $attendance = array(); // $eventid => attendance.
    public function get_attendance($event) {
        if (!array_key_exists($event->id, $this->attendance)) {
            $attendance = new attendance();
            $attendance->eventid = $event->id;
            $attendance->playerid = $this->id;
            $this->attendance[$event->id] = $attendance;
        }
        return $this->attendance[$event->id];
    }
    public function get_name() {
        return $this->firstname . ' ' . $this->lastname;
    }
    public function get_public_name() {
        return $this->firstname . ' ' . substr($this->lastname, 0, 1);
    }
}

class attendance {
    const UNKNOWN = 'unknown';
    const UNSURE = 'unsure';
    const YES = 'yes';
    const NO = 'no';
    const NOTREQUIRED = 'notrequired';
    public static $symbols = array(
        self::UNKNOWN => '-',
        self::NOTREQUIRED => 'Not required',
        self::NO => 'No',
        self::UNSURE => 'Not sure',
        self::YES => 'Yes',
    );
    public $eventid;
    public $playerid;
    public $status = self::UNKNOWN;
    public function get_symbol() {
        return self::$symbols[$this->status];
    }
    public function get_field_name() {
        return 'att_' . $this->playerid . '_' . $this->eventid;
    }
    public function get_select() {
        $output = '<select name="' . $this->get_field_name() . '" class="statusselect" id="' .
                $this->get_field_name() . '">';
        foreach (self::$symbols as $value => $symbol) {
            if ($value == self::NOTREQUIRED) {
                continue;
            }
            $selected = $this->status == $value ? ' selected="selected"' : '';
            $output .= '<option value="' . $value . '"' . $selected . '>' . $symbol . '</option>';
        }
        $output .= '</select>';
        return $output;
    }
}
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
 * System class. Provides a facade to all the functionality.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orchestra_register {
    private $user;
    private $players = null;
    private $events = null;
    private $parts = null;
    private $sections = null;
    private $request;
    private $output = null;
    /** @var sys_config */
    private $sysconfig;
    /** @var db_config */
    private $config;
    private $version;
    private $db;
    private $attendanceloaded = false;
    private $seriesid;

    public function __construct($requireinstalled = true) {

        if ($requireinstalled && !is_readable(dirname(__FILE__) . '/../config.php')) {
            $this->redirect('install.php', false, 'none');
        }

        $config = new sys_config();
        include(dirname(__FILE__) . '/../config.php');
        $config->check();
        $this->sysconfig = $config;

        $version = new version();
        include(dirname(__FILE__) . '/../version.php');
        $this->version = $version;

        $this->request = new request();
        $this->output = new html_output($this);
        session_start();

        $this->db = new database($this->sysconfig->dbhost, $this->sysconfig->dbuser,
                $this->sysconfig->dbpass, $this->sysconfig->dbname);

        $this->config = $this->db->load_config();
        if (!$this->config) {
            if ($requireinstalled) {
                $this->redirect('install.php', false, 'none');
            } else {
                return;
            }
        }
        $this->config = $this->db->check_installed($this->config,
                $this->version->id, $this->sysconfig->pwsalt);

        set_exception_handler(array($this->output, 'exception'));

        date_default_timezone_set($this->config->timezone);

        $this->seriesid = $this->request->get_param('s', request::TYPE_INT, null, false);
        if (is_null($this->seriesid) || !$this->check_series_exists($this->seriesid)) {
            $this->seriesid = $this->config->defaultseriesid;
        }
    }

    public function install() {
        $this->db->install($this->version->id);
    }

    public function get_request() {
        return $this->request;
    }

    /**
     * @return html_output
     */
    public function get_output() {
        return $this->output;
    }

    /**
     * @return mail_helper
     */
    public function emails() {
        return new mail_helper($this);
    }

    public function get_players($includenotplaying = false, $currentuserid = null) {
        if (is_null($this->players)) {
            $this->players = $this->db->load_players($this->seriesid,
                    $includenotplaying, $currentuserid);
        }
        return $this->players;
    }

    public function get_user($userid, $includedisabled = false) {
        return $this->db->find_user_by_id($userid, $includedisabled);
    }

    public function get_users($includedisabled = false) {
        return $this->db->load_users($includedisabled);
    }

    public function get_event($id, $includedeleted = false) {
        $event = $this->db->find_event_by_id($id, $includedeleted);
        if (!$event) {
            throw new not_found_exception('Unknown event.', $id);
        }
        return $event;
    }

    public function get_events($includepast = false, $includedeleted = false) {
        if (is_null($this->events)) {
            $this->events = $this->db->load_events($this->seriesid, $includepast, $includedeleted);
        }
        return $this->events;
    }

    public function get_parts($includenotplaying = false) {
        if (is_null($this->parts)) {
            $partsdata = $this->db->load_parts();
            $this->parts = array();
            foreach ($partsdata as $part) {
                $this->parts[$part->section][$part->part] = $part->part;
            }
            if ($includenotplaying) {
                $this->parts['Not playing'][0] = 'Not playing';
            }
        }
        return $this->parts;
    }

    /**
     * @return array nested structure represending all the secions and parts.
     */
    public function get_sections_and_parts() {
        if (is_null($this->sections)) {
            $partsdata = $this->db->load_sections_and_parts();
            $this->sections = array();
            $currentsection = null;
            foreach ($partsdata as $part) {
                if ($part->section != $currentsection) {
                    $currentsection = $part->section;
                    $this->sections[$currentsection]->section = $part->section;
                    $this->sections[$currentsection]->sectionsort = $part->sectionsort;
                    $this->sections[$currentsection]->parts = array();
                }
                if (!is_null($part->part)) {
                    $this->sections[$currentsection]->parts[$part->part]->part = $part->part;
                    $this->sections[$currentsection]->parts[$part->part]->section = $currentsection;
                    $this->sections[$currentsection]->parts[$part->part]->partsort = $part->partsort;
                    $this->sections[$currentsection]->parts[$part->part]->inuse = $part->inuse;
                }
            }
        }
        return $this->sections;
    }

    public function get_part_data($part) {
        foreach ($this->get_sections_and_parts() as $sectiondata) {
            if (array_key_exists($part, $sectiondata->parts)) {
                return $sectiondata->parts[$part];
            }
        }
        return null;
    }

    function is_valid_part($part) {
        foreach ($this->get_parts(true) as $sectionparts) {
            if (isset($sectionparts[$part])) {
                return true;
            }
        }
        return false;
    }

    function get_section($part) {
        foreach ($this->get_parts(true) as $section => $sectionparts) {
            if (isset($sectionparts[$part])) {
                return $section;
            }
        }
        throw new coding_error('Unknown part.');
    }

    function is_valid_section($section) {
        return array_key_exists($section, $this->get_sections_and_parts());
    }

    public function create_part($section, $part) {
        $this->db->insert_part($section, $part);
    }

    public function create_section($section) {
        $this->db->insert_section($section);
    }

    public function rename_part($oldname, $newname) {
        $this->db->rename_part($oldname, $newname);
    }

    public function rename_section($oldname, $newname) {
        $this->db->rename_section($oldname, $newname);
    }

    public function delete_part($part) {
        $this->db->delete_part($part);
    }

    public function delete_section($section) {
        $this->db->delete_section($section);
    }

    public function swap_section_order($section1, $section2) {
        $sections = $this->get_sections_and_parts();
        $this->db->swap_section_order($section1, $sections[$section1]->sectionsort,
                $section2, $sections[$section2]->sectionsort);
    }

    public function swap_part_order($part1, $part2) {
        $sections = $this->get_sections_and_parts();
        $this->db->swap_part_order($part1, $this->get_part_data($part1)->partsort,
                $part2, $this->get_part_data($part2)->partsort);
    }

    public function get_series($id, $includedeleted = false) {
        $series = $this->db->find_series_by_id($id, $includedeleted);
        if (!$series) {
            throw new not_found_exception('Unknown series.', $id);
        }
        return $series;
    }

    public function get_series_list($includedeleted = false) {
        return $this->db->load_series($includedeleted);
    }

    public function get_series_options() {
        $series = $this->db->load_series();
        $options = array();
        foreach ($series as $s) {
            $options[$s->id] = $s->name;
        }
        return $options;
    }

    public function load_attendance() {
        if ($this->attendanceloaded) {
            return;
        }
        $attendances = $this->db->load_attendances($this->seriesid);
        foreach ($attendances as $a) {
            $this->players[$a->userid]->attendance[$a->eventid] = $a;
        }
        $this->attendanceloaded = true;
    }

    public function load_subtotals() {
        $data = $this->db->load_subtotals($this->seriesid);
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

    public function get_subtotals($events) {
        $subtotals = $this->load_subtotals();
        $totalplayers = array();
        $totalattending = array();
        $sectionplayers = array();
        $sectionattending = array();
        foreach ($events as $event) {
            $totalplayers[$event->id] = 0;
            $totalattending[$event->id] = 0;

            foreach ($subtotals as $part => $subtotal) {
                if ($subtotal->numplayers[$event->id]) {
                    $totalplayers[$event->id] += $subtotal->numplayers[$event->id];
                    $totalattending[$event->id] += $subtotal->attending[$event->id];

                    if (!isset($sectionplayers[$subtotal->section][$event->id])) {
                        $sectionplayers[$subtotal->section][$event->id] = 0;
                        $sectionattending[$subtotal->section][$event->id] = 0;
                    }

                    $sectionplayers[$subtotal->section][$event->id] += $subtotal->numplayers[$event->id];
                    $sectionattending[$subtotal->section][$event->id] += $subtotal->attending[$event->id];
                }
            }
        }
        return array($subtotals, $totalplayers, $totalattending, $sectionplayers, $sectionattending);
    }

    public function load_selected_players($parts, $eventid, $statuses) {
        return $this->db->load_selected_players($this->seriesid, $parts, $eventid, $statuses);
    }

    public function set_player_part($player, $newpart, $seriesid = null) {
        if (is_null($seriesid)) {
            $seriesid = $this->seriesid;
        }
        $this->db->set_player_part($player->id, $seriesid, $newpart);
    }

    public function get_player_parts($user) {
        return $this->db->load_player_parts($user->id);
    }

    public function copy_players_between_series($oldseriesid, $newseriesid) {
        $this->db->copy_players_between_series($oldseriesid, $newseriesid);
    }

    public function set_attendance($player, $event, $newattendance) {
        $this->db->set_attendance($player->id, $this->seriesid, $event->id, $newattendance);
    }

    public function create_user($user) {
        $this->db->insert_user($user);
    }

    public function update_user($user) {
        $this->db->update_user($user);
    }

    public function set_user_password($userid, $newpassword) {
        $this->db->set_password($userid, $this->sysconfig->pwsalt . $newpassword);
    }

    public function create_event($event) {
        $this->db->insert_event($event);
    }

    public function update_event($event) {
        $this->db->update_event($event);
    }

    public function delete_event($event) {
        $this->db->set_event_deleted($event->id, 1);
    }

    public function undelete_event($event) {
        $this->db->set_event_deleted($event->id, 0);
    }

    public function create_series($series) {
        $this->db->insert_series($series);
    }

    public function update_series($series) {
        $this->db->update_series($series);
    }

    public function delete_series($series) {
        $this->db->set_series_deleted($series->id, 1);
    }

    public function undelete_series($series) {
        $this->db->set_series_deleted($series->id, 0);
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

    public function get_event_guid($event) {
        return 'event' . $event->id . '@' . $this->config->icalguid;
    }

    public function url($relativeurl, $withtoken = true, $xmlescape = true, $seriesid = null) {
        $extra = array();

        if (is_null($seriesid)) {
            $seriesid = $this->seriesid;
        }
        if ($seriesid != 'none' && $seriesid != $this->config->defaultseriesid) {
            $extra[] = 's=' . $seriesid;
        }

        if ($withtoken && empty($_SESSION['userid']) && !empty($this->user->authkey)) {
            $extra[] = 't=' . $this->user->authkey;
        }

        $extra = implode('&', $extra);
        if ($extra) {
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

    public function get_array_param($name, $type, $default = null, $postonly = true) {
        return $this->request->get_array_param($name, $type, $default, $postonly);
    }

    public function require_sesskey() {
        if ($this->get_sesskey() != $this->get_param('sesskey', request::TYPE_AUTHTOKEN)) {
            throw new forbidden_operation_exception(
                    'The request you just made could not be verified. ' .
                    'Please click back, reload the page, and try again. ' .
                    '(The session-key did not match.)');
        }
    }

    public function refresh_sesskey() {
        $this->get_current_user()->refresh_sesskey();
    }

    public function get_sesskey() {
        return $this->get_current_user()->sesskey;
    }

    public function verify_login($email, $password) {
        $this->require_sesskey();
        $user = $this->db->check_user_auth($email, $this->sysconfig->pwsalt . $password);
        if ($user) {
            session_regenerate_id(true);
            if ($this->config->changesesskeyonloginout) {
                $this->refresh_sesskey();
            }
            $_SESSION['userid'] = $user->id;
            $this->user = $user;
            $this->user->authlevel = user::AUTH_LOGIN;
            return true;
        }
        return false;
    }

    public function logout() {
        unset($_SESSION['userid']);
        session_regenerate_id(true);
        if ($this->config->changesesskeyonloginout) {
            $this->refresh_sesskey();
        }
    }

    public function check_series_exists($seriesid) {
        return $this->db->find_series_by_id($seriesid);
    }

    public function get_current_seriesid() {
        return $this->seriesid;
    }

    /** @return user */
    public function get_current_user() {
        if ($this->user) {
            return $this->user;
        }

        if (!empty($_SESSION['userid'])) {
            $user = $this->db->find_user_by_id($_SESSION['userid']);
            if ($user) {
                $this->user = $user;
                $this->user->authlevel = user::AUTH_LOGIN;
                return $this->user;
            }
        }

        $token = $this->request->get_param('t', request::TYPE_AUTHTOKEN, null, false);
        if ($token) {
            $user = $this->db->find_user_by_token($token);
            if ($user) {
                $this->user = $user;
                $this->user->authlevel = user::AUTH_TOKEN;
                return $this->user;
            }
        }

        $this->user = new user();
        return $this->user;
    }

    public function redirect($relativeurl, $withtoken = true, $seriesid = null) {
        header('HTTP/1.1 303 See Other');
        header('Location: ' . $this->url($relativeurl, $withtoken, false, $seriesid));
        exit(0);
    }

    /**
     * @return db_config
     */
    public function get_config() {
        return $this->config;
    }

    public function set_config($name, $value) {
        if (!$this->config->is_settable_property($name)) {
            throw new coding_error('Cannot set that configuration variable.',
                    'Name: ' . $name . ', Value: ' . $value);
        }
        $this->db->set_config($name, $value, $this->config);
    }

    /**
     * For use by install.php only.
     */
    public function set_default_config() {
        $this->config = new db_config();
        $this->user = new user();
    }

    public function version_string() {
        return 'Orchestra Register ' . $this->version->name . ' (' . $this->version->id . ')';
    }

    public function get_title() {
        return $this->config->title;
    }

    public function get_motd_heading() {
        return $this->config->motdheading;
    }

    public function get_motd() {
        return $this->config->motd;
    }

    public function get_actions_menus($user, $includepast) {
        if ($includepast) {
            $showhidepasturl = $this->url('');
            $showhidepastlabel = 'Hide events in the past';
            $printurl = $this->url('?print=1&past=1');
        } else {
            $showhidepasturl = $this->url('?past=1');
            $showhidepastlabel = 'Show events in the past';
            $printurl = $this->url('?print=1');
        }

        $seriesactions = new actions();
        $seriesactions->add($showhidepasturl, $showhidepastlabel);
        $seriesactions->add($printurl, 'Printable view');
        $seriesactions->add($this->url('ical.php', false), 'Download iCal file (to add the rehearsals into Outlook, etc.)');
        $seriesactions->add($this->url('wikiformat.php'), 'List of events to copy-and-paste into the wiki', $user->can_edit_events());
        $seriesactions->add($this->url('players.php'), 'Edit the list of players', $user->can_edit_players());
        $seriesactions->add($this->url('events.php'), 'Edit the list of events', $user->can_edit_events());
        $seriesactions->add($this->url('extractemails.php'), 'Get a list of email addresses', $user->can_edit_users());

        $systemactions = new actions();
        $systemactions->add($this->url('users.php'), 'Edit the list of users', $user->can_edit_users());
        $systemactions->add($this->url('series.php'), 'Edit the list of rehearsal series', $user->can_edit_series());
        $systemactions->add($this->url('parts.php'), 'Edit the available sections and parts', $user->can_edit_parts());
        $systemactions->add($this->url('editmotd.php'), 'Edit introductory message', $user->can_edit_motd());
        $systemactions->add($this->url('admin.php'), 'Edit the system configuration', $user->can_edit_config());
        $systemactions->add($this->url('logs.php'), 'View the system logs', $user->can_view_logs());

        return array($seriesactions, $systemactions);
    }

    public function get_help_url() {
        return $this->config->helpurl;
    }

    public function get_wiki_edit_url() {
        return $this->config->wikiediturl;
    }

    public function log($action) {
        if (empty($this->user->id)) {
            throw new coding_error('Cannot log an un-authenicated action.');
        }
        $this->db->insert_log($this->user->id, $this->user->authlevel, $action);
    }

    public function log_failed_login($email) {
        $this->db->insert_log(null, user::AUTH_NONE, 'failed attempt to log in as ' . $email);
    }

    public function get_num_logs() {
        return $this->db->count_logs();
    }

    public function load_logs($from, $limit) {
        return $this->db->load_logs($from, $limit);
    }
}

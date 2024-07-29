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
use JetBrains\PhpStorm\NoReturn;

/**
 * System class. Provides a facade to all the functionality.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orchestra_register {
    /** @var user|null */
    private ?user $user;
    private ?array $players = null;
    private ?array $events = null;
    private ?array $parts = null;
    private ?array $sections = null;
    private request $request;
    private ?html_output $output;
    /** @var sys_config */
    private sys_config $sysconfig;
    /** @var db_config|null */
    private ?db_config $config;
    private version $version;
    private database $db;
    private bool $attendanceloaded = false;
    private ?int $seriesid;

    public function __construct(bool $requireinstalled = true) {

        if ($requireinstalled && !is_readable(__DIR__ . '/../config.php')) {
            $this->redirect('install.php', false, 'none');
        }

        $config = new sys_config();
        include(__DIR__ . '/../config.php');
        $config->check();
        $this->sysconfig = $config;

        $version = new version();
        include(__DIR__ . '/../version.php');
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
        $this->config = $this->db->check_installed($this->config, $this->version->id);

        set_exception_handler(array($this->output, 'exception'));

        date_default_timezone_set($this->config->timezone);

        $this->seriesid = $this->request->get_param('s', request::TYPE_INT, null, false);
        if (is_null($this->seriesid) || !$this->check_series_exists($this->seriesid)) {
            $this->seriesid = $this->config->defaultseriesid;
        }
    }

    public function set_series_id(int $seriesid): void {
        if (!$this->check_series_exists($seriesid)) {
            throw new not_found_exception('Unknown series.', $seriesid);
        }
        if ($this->seriesid != $seriesid) {
            $this->seriesid = $seriesid;
            $this->players = null;
            $this->events = null;
            $this->attendanceloaded = false;
        }
    }

    public function install(): void {
        $this->db->install($this->version->id);
    }

    public function get_request(): request {
        return $this->request;
    }

    /**
     * @return html_output
     */
    public function get_output(): html_output {
        return $this->output;
    }

    /**
     * @return mail_helper
     */
    public function emails(): mail_helper {
        return new mail_helper($this);
    }

    /**
     * @param bool $includenotplaying
     * @param int|null $currentuserid
     * @return player[]
     */
    public function get_players(bool $includenotplaying = false, ?int $currentuserid = null): array {
        if (is_null($this->players)) {
            $this->players = $this->db->load_players($this->seriesid,
                    $includenotplaying, $currentuserid);
        }
        return $this->players;
    }

    /**
     * @param int $userid
     * @param bool $includedisabled
     * @return user
     */
    public function get_user(int $userid, bool $includedisabled = false): ?user {
        return $this->db->find_user_by_id($userid, $includedisabled);
    }

    /**
     * @param bool $includedisabled
     * @return player[]
     */
    public function get_users(bool $includedisabled = false): array {
        return $this->db->load_users($includedisabled);
    }

    /**
     * Get the data about an event.
     * @param int $id
     * @param bool $includedeleted
     * @return event
     */
    public function get_event(int $id, bool $includedeleted = false): event {
        $event = $this->db->find_event_by_id($id, $includedeleted);
        if (!$event) {
            throw new not_found_exception('Unknown event.', $id);
        }
        return $event;
    }

    /**
     * Get the data about an event.
     * @param bool $includepast
     * @param bool $includedeleted
     * @return event[]
     */
    public function get_events(bool $includepast = false, bool $includedeleted = false): array {
        if (is_null($this->events)) {
            $this->events = $this->db->load_events($this->seriesid, $includepast, $includedeleted);
        }
        return $this->events;
    }

    public function get_previous_event(int $eventid): ?event {
        $previousevent = null;
        foreach ($this->get_events(true) as $event) {
            if ($event->id == $eventid) {
                return $previousevent;
            }
            $previousevent = $event;
        }
        return null;
    }

    public function get_next_event(int $eventid): ?event {
        $found = false;
        foreach ($this->get_events(true) as $event) {
            if ($found) {
                return $event;
            }
            if ($event->id == $eventid) {
                $found = true;
            }
        }
        return null;
    }

    public function get_parts(bool $includenotplaying = false): array {
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
     * @return array nested structure representing all the sections and parts.
     */
    public function get_sections_and_parts(): array {
        if (is_null($this->sections)) {
            $partsdata = $this->db->load_sections_and_parts();
            $this->sections = [];
            $currentsection = null;
            foreach ($partsdata as $part) {
                if ($part->section != $currentsection) {
                    $currentsection = $part->section;
                    $this->sections[$currentsection] = new stdClass();
                    $this->sections[$currentsection]->section = $part->section;
                    $this->sections[$currentsection]->sectionsort = $part->sectionsort;
                    $this->sections[$currentsection]->parts = [];
                }
                if (!is_null($part->part)) {
                    $this->sections[$currentsection]->parts[$part->part] = new stdClass();
                    $this->sections[$currentsection]->parts[$part->part]->part = $part->part;
                    $this->sections[$currentsection]->parts[$part->part]->section = $currentsection;
                    $this->sections[$currentsection]->parts[$part->part]->partsort = $part->partsort;
                    $this->sections[$currentsection]->parts[$part->part]->inuse = $part->inuse;
                }
            }
        }
        return $this->sections;
    }

    /**
     * @param string $part
     * @return null|stdClass
     */
    public function get_part_data(string $part): ?stdClass {
        foreach ($this->get_sections_and_parts() as $sectiondata) {
            if (array_key_exists($part, $sectiondata->parts)) {
                return $sectiondata->parts[$part];
            }
        }
        return null;
    }

    function is_valid_part(string $part): bool {
        foreach ($this->get_parts(true) as $sectionparts) {
            if (isset($sectionparts[$part])) {
                return true;
            }
        }
        return false;
    }

    function get_section(string $part): string {
        foreach ($this->get_parts(true) as $section => $sectionparts) {
            if (isset($sectionparts[$part])) {
                return $section;
            }
        }
        throw new coding_error('Unknown part.');
    }

    function is_valid_section(string $section): bool {
        return array_key_exists($section, $this->get_sections_and_parts());
    }

    public function create_part(string $section, string $part): void {
        $this->db->insert_part($section, $part);
    }

    public function create_section(string $section): void {
        $this->db->insert_section($section);
    }

    public function rename_part(string $oldname, string $newname): void {
        $this->db->rename_part($oldname, $newname);
    }

    public function rename_section(string $oldname, string $newname): void {
        $this->db->rename_section($oldname, $newname);
    }

    public function delete_part(string $part): void {
        $this->db->delete_part($part);
    }

    public function delete_section(string $section): void {
        $this->db->delete_section($section);
    }

    public function swap_section_order(string $section1, string $section2): void {
        $sections = $this->get_sections_and_parts();
        $this->db->swap_section_order($section1, $sections[$section1]->sectionsort,
                $section2, $sections[$section2]->sectionsort);
    }

    public function swap_part_order(string $part1, string $part2): void {
        $this->db->swap_part_order($part1, $this->get_part_data($part1)->partsort,
                $part2, $this->get_part_data($part2)->partsort);
    }

    public function get_series(int $id, bool $includedeleted = false): series {
        $series = $this->db->find_series_by_id($id, $includedeleted);
        if (!$series) {
            throw new not_found_exception('Unknown series.', $id);
        }
        return $series;
    }

    public function get_series_list(bool $includedeleted = false): array {
        return $this->db->load_series($includedeleted);
    }

    public function get_series_options(): array {
        $series = $this->db->load_series();
        $options = array();
        foreach ($series as $s) {
            $options[$s->id] = $s->name;
        }
        return $options;
    }

    public function load_attendance(): void {
        if ($this->attendanceloaded) {
            return;
        }
        $attendances = $this->db->load_attendances($this->seriesid);
        foreach ($attendances as $a) {
            if (isset($this->players[$a->userid])) {
                $this->players[$a->userid]->attendance[$a->eventid] = $a;
            }
        }
        $this->attendanceloaded = true;
    }

    public function load_subtotals(): array {
        $data = $this->db->load_subtotals($this->seriesid);
        $subtotals = array();
        foreach ($data as $row) {
            if (!array_key_exists($row->part, $subtotals)) {
                $subtotal = new stdClass;
                $subtotal->section = $row->section;
                $subtotal->attending = [];
                $subtotal->numplayers = [];
                $subtotals[$row->part] = $subtotal;
            }
            $subtotals[$row->part]->attending[$row->eventid] = $row->attending;
            $subtotals[$row->part]->numplayers[$row->eventid] = $row->numplayers;
        }
        return $subtotals;
    }

    public function get_subtotals(array $events): array {
        $subtotals = $this->load_subtotals();
        $totalplayers = [];
        $totalattending = [];
        $sectionplayers = [];
        $sectionattending = [];
        foreach ($events as $event) {
            $totalplayers[$event->id] = 0;
            $totalattending[$event->id] = 0;
            foreach ($this->get_sections_and_parts() as $section => $notused) {
                $sectionplayers[$section][$event->id] = 0;
                $sectionattending[$section][$event->id] = 0;
            }

            foreach ($subtotals as $subtotal) {
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
        return [$subtotals, $totalplayers, $totalattending, $sectionplayers, $sectionattending];
    }

    public function load_selected_players(array $parts, int $eventid, array $statuses): array {
        return $this->db->load_selected_players($this->seriesid, $parts, $eventid, $statuses);
    }

    public function set_player_part(player $player, string $newpart, int $seriesid = null): void {
        if (is_null($seriesid)) {
            $seriesid = $this->seriesid;
        }
        $this->db->set_player_part($player->id, $seriesid, $newpart);
    }

    public function get_player_parts(user|player $user): array {
        return $this->db->load_player_parts($user->id);
    }

    public function copy_players_between_series(int $oldseriesid, int $newseriesid): void {
        $this->db->copy_players_between_series($oldseriesid, $newseriesid);
    }

    public function set_attendance(player $player, event $event, string $newattendance): void {
        $this->db->set_attendance($player->id, $this->seriesid, $event->id, $newattendance);
    }

    public function create_user(user $user): void {
        $this->db->insert_user($user);
    }

    public function update_user(user $user): void {
        $this->db->update_user($user);
    }

    public function set_user_password(int $userid, string $newpassword): void {
        $this->db->set_password($userid, $this->sysconfig->pwsalt . $newpassword);
    }

    public function create_event(event $event): void {
        $this->db->insert_event($event);
    }

    public function update_event(event $event): void {
        $this->db->update_event($event);
    }

    public function delete_event(event $event): void {
        $this->db->set_event_deleted($event->id, 1);
    }

    public function undelete_event(event $event): void {
        $this->db->set_event_deleted($event->id, 0);
    }

    public function create_series(series $series): void {
        $this->db->insert_series($series);
    }

    public function update_series(series $series): void {
        $this->db->update_series($series);
    }

    public function delete_series(series$series): void {
        $this->db->set_series_deleted($series->id, 1);
    }

    public function undelete_series(series $series): void {
        $this->db->set_series_deleted($series->id, 0);
    }

    public function get_login_info(): string {
        $this->get_current_user();
        if ($this->user->is_logged_in()) {
            return 'You are logged in as ' . $this->user->get_name() .
                    '. <a href="' . $this->url('logout.php', false) . '">Log out</a>.';
        } else {
            return 'You are not logged in. <a href="' . $this->url('login.php') . '">Log in</a>.';
        }
    }

    public function get_event_guid(event $event): string {
        return 'event' . $event->id . '@' . $this->config->icalguid;
    }

    public function url(string $relativeurl, bool $withtoken = true, bool $xmlescape = true, ?int $seriesid = null): string {
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
            if (str_contains($relativeurl, '?')) {
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

    public function get_param(string $name, int $type, mixed $default = null, bool $postonly = true): mixed {
        return $this->request->get_param($name, $type, $default, $postonly);
    }

    public function get_array_param(string $name, int $type, mixed $default = null, bool $postonly = true): array {
        return $this->request->get_array_param($name, $type, $default, $postonly);
    }

    public function require_sesskey(): void {
        if ($this->get_sesskey() != $this->get_param('sesskey', request::TYPE_AUTHTOKEN)) {
            throw new forbidden_operation_exception(
                    'The request you just made could not be verified. ' .
                    'Please click back, reload the page, and try again. ' .
                    '(The session-key did not match.)');
        }
    }

    public function refresh_sesskey(): void {
        $this->get_current_user()->refresh_sesskey();
    }

    public function get_sesskey(): string {
        return $this->get_current_user()->sesskey;
    }

    public function verify_login(string $email, string $password): bool {
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

    public function logout(): void {
        unset($_SESSION['userid']);
        session_regenerate_id(true);
        if ($this->config->changesesskeyonloginout) {
            $this->refresh_sesskey();
        }
    }

    public function check_series_exists(int $seriesid): bool {
        return $this->db->find_series_by_id($seriesid);
    }

    public function get_current_seriesid(): int {
        return $this->seriesid;
    }

    /** @return user */
    public function get_current_user(): user {
        if (!$this->user) {
            $this->user = $this->find_current_user();
            $this->user->maintenancemode = $this->is_in_maintenance_mode();
        }
        return $this->user;
    }

    /**
     * Helper used by {@link get_current_user()}.
     * @return user
     */
    protected function find_current_user(): user {
        if (!empty($_SESSION['userid'])) {
            $user = $this->db->find_user_by_id($_SESSION['userid']);
            if ($user) {
                $user->authlevel = user::AUTH_LOGIN;
                return $user;
            }
        }

        $token = $this->request->get_param('t', request::TYPE_AUTHTOKEN, null, false);
        if ($token) {
            $user = $this->db->find_user_by_token($token);
            if ($user) {
                $user->authlevel = user::AUTH_TOKEN;
                return $user;
            }
        }

        return new user();
    }

    #[NoReturn] public function redirect(string $relativeurl, bool $withtoken = true, ?int $seriesid = null): void {
        header('HTTP/1.1 303 See Other');
        header('Location: ' . $this->url($relativeurl, $withtoken, false, $seriesid));
        exit(0);
    }

    /**
     * @return db_config
     */
    public function get_config(): db_config {
        return $this->config;
    }

    public function set_config(string $name, ?string $value): void {
        if (!$this->config->is_settable_property($name)) {
            throw new coding_error('Cannot set that configuration variable.',
                    'Name: ' . $name . ', Value: ' . $value);
        }
        $this->db->set_config($name, $value, $this->config);
    }

    /**
     * For use by install.php only.
     */
    public function set_default_config(): void {
        $this->config = new db_config();
        $this->user = new user();
    }

    public function version_string(): string {
        return 'Orchestra Register ' . $this->version->name . ' (' . $this->version->id . ')';
    }

    public function get_title(): string {
        return $this->config->title;
    }

    public function get_motd_heading(): string {
        return $this->config->motdheading;
    }

    public function get_motd(): string {
        return $this->config->motd;
    }

    /**
     * Get the lists of actions that appear under the register.
     * @param user $user
     * @param bool $includepast whether past events are currently included.
     * @param string $showhideurl whether to include the show/hide events in the past URL.
     * @return actions[] with two elements, both actions objects.
     */
    public function get_actions_menus(user $user, bool $includepast, string $showhideurl = ''): array {
        if ($includepast) {
            $showhidepasturl = $this->url($showhideurl);
            $showhidepastlabel = 'Hide events in the past';
        } else {
            if (str_contains($showhideurl, '?')) {
                $join = '&';
            } else {
                $join = '?';
            }
            $showhidepasturl = $this->url($showhideurl . $join . 'past=1');
            $showhidepastlabel = 'Show events in the past';
        }

        $seriesactions = new actions();
        if (is_string($showhideurl)) {
            $seriesactions->add($showhidepasturl, $showhidepastlabel);
        }
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

    public function get_help_url(): string {
        return $this->config->helpurl;
    }

    public function get_wiki_edit_url(): string {
        return $this->config->wikiediturl;
    }

    public function is_in_maintenance_mode(): bool {
        return $this->get_config()->maintenancemode;
    }

    public function log(string $action): void {
        if (empty($this->user->id)) {
            throw new coding_error('Cannot log an un-authenicated action.');
        }
        $this->db->insert_log($this->user->id, $this->user->authlevel, $action);
    }

    public function log_failed_login(string $email): void {
        $this->db->insert_log(null, user::AUTH_NONE, 'failed attempt to log in as ' . $email);
    }

    public function get_num_logs(): int {
        return $this->db->count_logs();
    }

    public function load_logs(int $from, int $limit): array {
        return $this->db->load_logs($from, $limit);
    }
}

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
 * Low-level database functions.
 *
 * This class should only be used by the other classes in this file, it should
 * not be used more widely.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class database_connection {
    private $conn;

    public function __construct($dbhost, $dbuser, $dbpass, $dbname) {
        $this->conn = mysql_connect($dbhost, $dbuser, $dbpass);
        if (!$this->conn) {
            throw new database_connect_exception($this->get_last_error());
        }
        if (!mysql_select_db($dbname)) {
            throw new database_connect_exception('Could not select the database ' . $dbname, $this->get_last_error());
        }
    }

    public function escape($value, $maxlength = null) {
        if (is_null($value)) {
            return 'NULL';
        }
        if ($maxlength) {
            $value = substr($value, 0, $maxlength - 1);
        }
        return "'" . mysql_real_escape_string($value, $this->conn) . "'";
    }

    public function get_last_insert_id() {
        return mysql_insert_id($this->conn);
    }

    public function get_last_error() {
        return mysql_error($this->conn);
    }

    public function execute_sql($sql) {
        $result = mysql_query($sql, $this->conn);
        if (!$result) {
            throw new database_exception('Failed to load or save data from the databse.',
                    $this->get_last_error() . '. SQL: ' . $sql);
        }
        return $result;
    }

    public function update($sql) {
        $this->execute_sql($sql);
    }

    public function table_exists($name) {
        $result = $this->execute_sql("SHOW TABLES LIKE 'config'");
        $exists = mysql_num_rows($result);
        mysql_free_result($result);
        return $exists;
    }

    public function get_record_select($table, $where, $class) {
        return $this->get_record_sql("SELECT * FROM $table WHERE $where", $class);
    }

    public function get_record_sql($sql, $class) {
        $object = null;
        $result = $this->execute_sql($sql);
        if (mysql_num_rows($result) == 1) {
            $object = mysql_fetch_object($result, $class);
        }
        mysql_free_result($result);
        return $object;
    }

    public function get_records($table, $class, $order = '') {
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $order, $class);
    }

    public function get_records_select($table, $where, $class, $order = '') {
        if ($where) {
            $where = ' WHERE ' . $where;
        }
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $where . $order, $class);
    }

    public function get_records_sql($sql, $class) {
        $result = $this->execute_sql($sql);
        $objects = array();
        while ($object = mysql_fetch_object($result, $class)) {
            if (!empty($object->id)) {
                $objects[$object->id] = $object;
            } else {
                $objects[] = $object;
            }
        }
        mysql_free_result($result);
        return $objects;
    }

    public function count_records($table) {
        $record = $this->get_record_sql('SELECT COUNT(1) AS count FROM ' . $table, 'stdClass');
        if (!$record) {
            throw new database_exception('Failed to count data in the databse.', 'Table ' . $table);
        }
        return $record->count;
    }
}

/**
 * Database access functions.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class database {
    /**
     * @var database_connection
     */
    private $connection;

    public function __construct($dbhost, $dbuser, $dbpass, $dbname) {
        $this->connection = new database_connection($dbhost, $dbuser, $dbpass, $dbname);
    }

    protected function escape($value, $maxlength = null) {
        return $this->connection->escape($value, $maxlength);
    }

    public function load_users($includedisabled = false) {
        $order = 'firstname, lastname';
        if ($includedisabled) {
            return $this->connection->get_records('users', 'player', $order);
        } else {
            return $this->connection->get_records_select('users',
                    "users.role <> '" . user::DISABLED . "'", 'player', $order);
        }
    }

    public function load_players($seriesid, $includenotplaying = false,
            $currentuserid = null) {
        if ($includenotplaying) {
            $deletedtest = '';
        } else {
            $deletedtest = 'AND players.part IS NOT NULL';
        }
        $sql = "
            SELECT id, firstname, lastname, email, players.part, parts.section, authkey, pwhash, pwsalt, role
            FROM users
            LEFT JOIN players ON users.id = players.userid AND players.seriesid = {$this->escape($seriesid)}
            LEFT JOIN parts ON players.part = parts.part
            LEFT JOIN sections ON parts.section = sections.section
            WHERE
                users.role <> '" . user::DISABLED . "'
                $deletedtest
            ORDER BY
                CASE WHEN parts.section IS NULL THEN 1 ELSE 0 END,
                CASE WHEN parts.section = (
                    SELECT section
                    FROM parts JOIN players ON players.part = parts.part
                    WHERE players.seriesid = {$this->escape($seriesid)}
                        AND players.userid = {$this->escape($currentuserid)}
                ) THEN -1 ELSE sectionsort END,
                CASE WHEN players.part = (
                    SELECT part FROM players
                    WHERE players.seriesid = {$this->escape($seriesid)}
                        AND players.userid = {$this->escape($currentuserid)}
                ) THEN -1 ELSE partsort END,
                firstname,
                lastname";
        return $this->connection->get_records_sql($sql, 'player');
    }

    public function load_series($includedeleted = false) {
        $conditions = array();
        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }
        return $this->connection->get_records_select('series',
                $this->where_clause($conditions), 'series', 'id');
    }

    public function load_events($seriesid, $includepast = false, $includedeleted = false) {
        $conditions = array('seriesid = ' . $this->escape($seriesid));
        if (!$includedeleted) {
            $conditions[] = 'deleted = 0';
        }
        if (!$includepast) {
            $conditions[] = 'timeend > ' . $this->escape(time());
        }
        return $this->connection->get_records_select('events', implode(' AND ', $conditions), 'event', 'timestart');
    }

    public function load_attendances($seriesid) {
        return $this->connection->get_records_select('attendances',
                'seriesid = ' . $this->escape($seriesid), 'attendance');
    }

    public function load_subtotals($seriesid) {
        return $this->connection->get_records_sql("
            SELECT
                parts.part,
                parts.section,
                events.id AS eventid,
                sum(CASE WHEN status = '" . attendance::YES . "' THEN 1 ELSE 0 END) AS attending,
                sum(CASE WHEN status = '" . attendance::NOTREQUIRED . "' THEN 0 ELSE 1 END) AS numplayers
            FROM players
            JOIN users ON players.userid = users.id
            JOIN parts ON players.part = parts.part
            JOIN sections ON parts.section = sections.section
            JOIN events ON events.seriesid = players.seriesid
            LEFT JOIN attendances ON attendances.userid = players.userid AND
                    attendances.seriesid = players.seriesid AND attendances.eventid = events.id
            WHERE
                events.deleted = 0 AND
                users.role <> '" . user::DISABLED . "' AND
                players.seriesid = {$this->escape($seriesid)}
            GROUP BY events.id, parts.part, parts.section
            ORDER BY sectionsort, partsort", 'stdClass');
    }

    public function load_selected_players($seriesid, $parts, $eventid, $statuses) {
        $tests = array("users.role <> '" . user::DISABLED . "'");

        $extrajoin = '';
        $conditions = array();
        $partlist = array();
        foreach ($parts AS $part) {
            if (empty($part)) {
                $conditions[] = 'players.part IS NULL';
            } else {
                $partlist[] = $this->escape($part);
            }
        }
        if (!empty($partlist)) {
            $conditions[] = 'players.part IN (' . implode(',', $partlist) . ')';
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';

        if ($eventid) {
            $extrajoin = "LEFT JOIN attendances ON attendances.seriesid = {$this->escape($seriesid)} AND
                    attendances.eventid = {$this->escape($eventid)} AND attendances.userid = players.userid";
            $statuslist = array();
            $extra = '';
            foreach ($statuses AS $status) {
                $statuslist[] = $this->escape($status);
                if ($status == attendance::UNKNOWN) {
                    $extra = ' OR attendances.status IS NULL';
                }
            }
            $tests[] = "(attendances.status IN (" . implode(',', $statuslist) . ")$extra)";
        }

        $tests = implode(' AND ', $tests);

        $sql = "
            SELECT id, firstname, lastname, email, players.part, parts.section, authkey, pwhash, pwsalt, role
            FROM users
            LEFT JOIN players ON users.id = players.userid AND players.seriesid = {$this->escape($seriesid)}
            LEFT JOIN parts ON players.part = parts.part
            LEFT JOIN sections ON parts.section = sections.section
            $extrajoin
            WHERE
                $tests
            ORDER BY
                CASE WHEN parts.section IS NULL THEN 1 ELSE 0 END,
                sectionsort,
                partsort,
                firstname,
                lastname";
        return $this->connection->get_records_sql($sql, 'player');
    }

    public function load_parts() {
        return $this->connection->get_records_sql("
            SELECT parts.part, parts.section
            FROM parts
            JOIN sections ON parts.section = sections.section
            ORDER BY sectionsort, partsort", 'stdClass');
    }

    public function find_user_by_id($userid, $includedisabled = false) {
        $disabledtest = '';
        if (!$includedisabled) {
            $disabledtest = " AND role <> '" . user::DISABLED . "'";
        }
        return $this->connection->get_record_select('users',
                "id = $userid" . $disabledtest, 'user');
    }

    public function find_user_by_token($token) {
        return $this->connection->get_record_select('users',
                "authkey = {$this->escape($token)} AND role <> '" . user::DISABLED . "'", 'user');
    }

    public function find_event_by_id($eventid, $includedeleted = false) {
        $conditions = array('id' => $eventid);
        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }
        return $this->connection->get_record_select('events', $this->where_clause($conditions), 'event');
    }

    public function find_series_by_id($seriesid, $includedeleted = false) {
        $conditions = array('id' => $seriesid);
        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }
        return $this->connection->get_record_select('series', $this->where_clause($conditions), 'series');
    }

    public function check_user_auth($email, $saltedpassword) {
        return $this->connection->get_record_select('users',
                "email = " . $this->escape($email) . " AND pwhash = SHA1(CONCAT(" .
                $this->escape($saltedpassword) . ", pwsalt)) AND role <> '" . user::DISABLED . "'", 'user');
    }

    public function set_password($userid, $saltedpassword) {
        $this->connection->update("UPDATE users SET pwhash = SHA1(CONCAT(" .
            $this->escape($saltedpassword) . ", pwsalt)) WHERE id = " . $this->escape($userid));
    }

    public function set_event_deleted($eventid, $deleted) {
        $this->connection->update("UPDATE events SET deleted = " . $this->escape($deleted) .
                " WHERE id = " . $this->escape($eventid));
    }

    public function set_series_deleted($seriesid, $deleted) {
        $this->connection->update("UPDATE series SET deleted = " . $this->escape($deleted) .
                " WHERE id = " . $this->escape($seriesid));
    }

    /**
     * @return db_config
     */
    public function load_config() {
        if (!$this->connection->table_exists('config')) {
            return null;
        }
        $result = $this->connection->execute_sql('SELECT * FROM config');
        $config = new db_config();
        while ($row = mysql_fetch_object($result)) {
            $config->{$row->name} = $row->value;
        }
        mysql_free_result($result);
        return $config;
    }

    public function copy_players_between_series($oldseriesid, $newseriesid) {
        $sql = "INSERT INTO players (userid, seriesid, part)
                SELECT oldplayers.userid, {$this->escape($newseriesid)}, oldplayers.part
                FROM players oldplayers
                WHERE
                    oldplayers.seriesid = {$this->escape($oldseriesid)} AND
                    NOT EXISTS (SELECT 1 FROM players newplayers
                        WHERE newplayers.userid = oldplayers.userid AND
                            newplayers.seriesid = {$this->escape($newseriesid)})";
        $this->connection->update($sql);
    }

    public function set_player_part($userid, $seriesid, $newpart) {
        $sql = "INSERT INTO players (userid, seriesid, part)
                VALUES (" . $this->escape($userid) . ", " . $this->escape($seriesid) . ", " .
                        $this->escape($newpart) . ")
                ON DUPLICATE KEY UPDATE part = " . $this->escape($newpart);
        $this->connection->update($sql);
    }

    public function set_attendance($userid, $seriesid, $eventid, $newstatus) {
        $sql = "INSERT INTO attendances (userid, seriesid, eventid, status)
                VALUES (" . $this->escape($userid) . ", " . $this->escape($seriesid) . ", " .
                        $this->escape($eventid) . ", " . $this->escape($newstatus) . ")
                ON DUPLICATE KEY UPDATE status = " . $this->escape($newstatus);
        $this->connection->update($sql);
    }

    public function set_config($name, $value, $config = null) {
        $sql = "INSERT INTO config (name, value)
                VALUES (" . $this->escape($name) . ", " . $this->escape($value) . ")
                ON DUPLICATE KEY UPDATE value = " . $this->escape($value);
        $this->connection->update($sql);
        if ($config) {
            $config->$name = $value;
        }
    }

    public function insert_user($user) {
        $sql = "INSERT INTO users (firstname, lastname, email, authkey, pwhash, pwsalt, role)
                VALUES (" . $this->escape($user->firstname) . ", " .
                $this->escape($user->lastname) . ", " .
                $this->escape($user->email) . ", " .
                $this->escape(self::random_string(40)) . ", " .
                "NULL, " .
                $this->escape(self::random_string(40)) . ", " .
                $this->escape($user->role) . ")";
        $this->connection->update($sql);
        $user->id = $this->connection->get_last_insert_id();
    }

    public function update_user($user) {
        if (empty($user->id)) {
            throw new coding_error('Trying to update a player who is not in the database.');
        }
        $sql = "UPDATE users SET
                firstname = " . $this->escape($user->firstname) . ",
                lastname = " . $this->escape($user->lastname) . ",
                email = " . $this->escape($user->email) . ",
                role = " . $this->escape($user->role) . "
                WHERE " . $this->where_clause(array('id' => $user->id));
        $this->connection->update($sql);
    }

    public function insert_series($series) {
        $sql = "INSERT INTO series (name, description, deleted)
                VALUES (" . $this->escape($series->name) . ", " .
                $this->escape($series->description) . ", " .
                $this->escape($series->deleted) . ")";
        $this->connection->update($sql);
        $series->id = $this->connection->get_last_insert_id();
    }

    public function insert_event($event) {
        $sql = "INSERT INTO events (seriesid, name, description, venue, timestart, timeend, timemodified)
                VALUES (" . $this->escape($event->seriesid) . ", " .
                $this->escape($event->name) . ", " . $this->escape($event->description) . ", " .
                $this->escape($event->venue) . ", " . $this->escape($event->timestart) . ", " .
                $this->escape($event->timeend) . ", " . $this->escape(time()) . ")";
        $this->connection->update($sql);
        $event->id = $this->connection->get_last_insert_id();
    }

    public function update_series($series) {
        if (empty($series->id)) {
            throw new coding_error('Trying to update a series that is not in the database.');
        }
        $sql = "UPDATE series SET
                name = " . $this->escape($series->name) . ",
                description = " . $this->escape($series->description) . ",
                deleted = " . $this->escape($series->deleted) . "
                WHERE " . $this->where_clause(array('id' => $series->id));
        $this->connection->update($sql);
    }

    public function update_event($event) {
        if (empty($event->id)) {
            throw new coding_error('Trying to update an event that is not in the database.');
        }
        $sql = "UPDATE events SET
                seriesid = " . $this->escape($event->seriesid) . ",
                name = " . $this->escape($event->name) . ",
                description = " . $this->escape($event->description) . ",
                venue = " . $this->escape($event->venue) . ",
                timestart = " . $this->escape($event->timestart) . ",
                timeend = " . $this->escape($event->timeend) . ",
                timemodified = " . $this->escape(time()) . "
                WHERE " . $this->where_clause(array('id' => $event->id));
        $this->connection->update($sql);
    }

    public function insert_section($section, $sort) {
        $sql = "INSERT INTO sections (section, sectionsort)
                VALUES (" . $this->escape($section) . ", " . $this->escape($sort) . ")";
        $this->connection->update($sql);
    }

    public function insert_part($part, $section, $sort) {
        $sql = "INSERT INTO parts (part, section, partsort)
                VALUES (" . $this->escape($part) . ", " . $this->escape($section) . ", " .
                $this->escape($sort) . ")";
        $this->connection->update($sql);
    }

    public function insert_log($userid, $authlevel, $action) {
        $sql = "INSERT INTO logs (timestamp, userid, authlevel, ipaddress, action)
                VALUES (" . $this->escape(time()) . ", " . $this->escape($userid) . ", " .
                $this->escape($authlevel) . ", " . $this->escape(request::get_ip_address()) . ", " .
                $this->escape($action, 255) . ")";
        $this->connection->update($sql);
    }

    public function count_logs() {
        return $this->connection->count_records('logs');
    }

    public function load_logs($from, $limit) {
        $sql = "SELECT l.timestamp, u.firstname, u.lastname, u.email, l.authlevel, l.ipaddress, l.action
                FROM logs l
                LEFT JOIN users u ON u.id = l.userid
                ORDER BY l.timestamp DESC, l.id DESC
                LIMIT $from, $limit";
        return $this->connection->get_records_sql($sql, 'stdCLass');
    }

    public static function random_string($length) {
        $pool  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pool .= 'abcdefghijklmnopqrstuvwxyz';
        $pool .= '0123456789';
        $poollen = strlen($pool);
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= substr($pool, mt_rand(0, $poollen - 1), 1);
        }
        return $string;
    }

    protected function where_clause($tests) {
        $clauses = array();
        foreach ($tests as $field => $value) {
            $clauses[] = $field . " = " . $this->escape($value);
        }
        return implode(' AND ', $clauses);
    }

    protected function get_last_error() {
        return $this->connection->get_last_error();
    }

    public static function load_csv($filename, $skipheader = true) {
        $handle = fopen(dirname(__FILE__) . '/../' . $filename, 'r');
        if (!$handle) {
            return array();
        }
        if ($skipheader) {
            fgets($handle);
        }
        $data = array();
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
        return $data;
    }

    protected function get_installer() {
        require_once(dirname(__FILE__) . '/installer.php');
        return new installer($this, $this->connection);
    }

    /**
     * Check that the database is installed, and up-to-date. If not, rectify that.
     * @param db_config $config
     * @param string $codeversion
     * @param string $pwsalt
     * @return db_config the config, possibly updated.
     */
    public function check_installed(db_config $config, $codeversion, $pwsalt) {
        $donesomething = false;
        if (is_null($config)) {
            $this->get_installer()->install($pwsalt);
            $this->insert_log(null, user::AUTH_NONE, 'install version ' . $codeversion);
            $donesomething = true;

        } else if ($config->version < $codeversion) {
            $this->get_installer()->upgrade($config->version);
            $this->insert_log(null, user::AUTH_NONE, 'upgrade to version ' . $codeversion);
            $donesomething = true;
        }

        if ($donesomething) {
            $this->set_config('version', $codeversion);
            $config = $this->load_config();
        }

        return $config;
    }
}

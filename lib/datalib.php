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
 * Database access functions.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class database {
    private $conn;

    public function __construct($dbhost, $dbuser, $dbpass, $dbname) {
        $this->conn = mysql_connect($dbhost, $dbuser, $dbpass);
        if (!$this->conn) {
            throw new Exception('Could not connect to the database.');
        }
        if (!mysql_select_db($dbname)) {
            throw new Exception('Could not select the right database.');
        }
    }

    protected function escape($value) {
        if (is_null($value)) {
            return 'NULL';
        }
        return "'" . mysql_real_escape_string($value, $this->conn) . "'";
    }

    protected function where_clause($tests) {
        $clauses = array();
        foreach ($tests as $field => $value) {
            $clauses[] = $field . " = " . $this->escape($value);
        }
        return implode(' AND ', $clauses);
    }

    protected function execute_sql($sql) {
        $result = mysql_query($sql, $this->conn);
        if (!$result) {
            throw new Exception('Query failed: ' . mysql_error($this->conn) .
                    ', SQL: ' . $sql);
        }
        return $result;
    }

    protected function get_records($table, $class, $order = '') {
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $order, $class);
    }

    protected function get_records_select($table, $where, $class, $order = '') {
        if ($where) {
            $where = ' WHERE ' . $where;
        }
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $where . $order, $class);
    }

    protected function get_records_sql($sql, $class) {
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

    protected function update($sql) {
        $this->execute_sql($sql);
    }

    public function load_players($currentsection = '', $currentpart = '') {
        return $this->get_records_sql("
            SELECT id, firstname, lastname, email, players.part, parts.section, authkey, pwhash, pwsalt, role
            FROM players
            JOIN parts ON players.part = parts.part
            JOIN sections ON parts.section = sections.section
            ORDER BY
                CASE WHEN parts.section = " . $this->escape($currentsection) . " THEN -1 ELSE sectionsort END,
                CASE WHEN players.part = " . $this->escape($currentpart) . " THEN -1 ELSE partsort END,
                lastname,
                firstname
        ", 'player');
    }

    public function load_events($includepast = false) {
        if ($includepast) {
            return $this->get_records('events', 'event', 'timestart');
        } else {
            return $this->get_records_select('events', 'timeend > ' . $this->escape(time()),
                    'event', 'timestart');
        }
    }

    public function load_attendances() {
        return $this->get_records('attendances', 'attendance');
    }

    public function load_subtotals() {
        return $this->get_records_sql("
            SELECT
                parts.part,
                parts.section,
                events.id AS eventid,
                sum(CASE WHEN status = '" . attendance::YES . "' THEN 1 ELSE 0 END) AS attending,
                sum(CASE WHEN status = '" . attendance::NOTREQUIRED . "' THEN 0 ELSE 1 END) AS numplayers 
            FROM players
            JOIN parts ON players.part = parts.part
            JOIN sections ON parts.section = sections.section
            JOIN events
            LEFT JOIN attendances ON playerid = players.id AND eventid = events.id
            GROUP BY events.id, parts.part, parts.section
            ORDER BY sectionsort, partsort", 'stdClass');
    }

    protected function get_record_select($table, $where, $class) {
        return $this->get_record_sql("SELECT * FROM $table WHERE $where", $class);
    }

    protected function get_record_sql($sql, $class) {
        $object = null;
        $result = $this->execute_sql($sql);
        if (mysql_num_rows($result) == 1) {
            $object = mysql_fetch_object($result, $class);
        }
        mysql_free_result($result);
        return $object;
    }

    public function find_player_by_id($playerid) {
        return $this->get_record_sql("
                SELECT id, firstname, lastname, email, players.part, parts.section, authkey, pwhash, pwsalt, role
                FROM players
                JOIN parts ON players.part = parts.part
                WHERE " . $this->where_clause(array('id' => $playerid)), 'player');
    }

    public function find_player_by_token($token) {
        return $this->get_record_sql("
                SELECT id, firstname, lastname, email, players.part, parts.section, authkey, pwhash, pwsalt, role
                FROM players
                JOIN parts ON players.part = parts.part
                WHERE " . $this->where_clause(array('authkey' => $token)), 'player');
    }

    public function check_user_auth($email, $saltedpassword) {
        return $this->get_record_select('players',
                "email = " . $this->escape($email) . " AND pwhash = SHA1(CONCAT(" .
                $this->escape($saltedpassword) . ", pwsalt))", 'player');
    }

    public function set_password($playerid, $saltedpassword) {
        $this->update("UPDATE players SET pwhash = SHA1(CONCAT(" .
            $this->escape($saltedpassword) . ", pwsalt)) WHERE id = " . $this->escape($playerid));
    }

    public function load_config() {
        $result = $this->execute_sql('SELECT * FROM config');
        $config = new db_config();
        while ($row = mysql_fetch_object($result)) {
            $config->{$row->name} = $row->value;
        }
        mysql_free_result($result);
        return $config;
    }

    public function table_exists($name) {
        $result = $this->execute_sql("SHOW TABLES LIKE 'config'");
        $exists = mysql_num_rows($result);
        mysql_free_result($result);
        return $exists;
    }

    public function set_attendance($playerid, $eventid, $newstatus) {
        $sql = "INSERT INTO attendances (playerid, eventid, status)
                VALUES (" . $this->escape($playerid) . ", " . $this->escape($eventid) . ", " .
                        $this->escape($newstatus) . ")
                ON DUPLICATE KEY UPDATE status = " . $this->escape($newstatus);
        $this->update($sql);
    }

    public function set_config($name, $value) {
        $sql = "INSERT INTO config (name, value)
                VALUES (" . $this->escape($name) . ", " . $this->escape($value) . ")
                ON DUPLICATE KEY UPDATE value = " . $this->escape($value);
        $this->update($sql);
    }

    public function insert_player($player) {
        $sql = "INSERT INTO players (firstname, lastname, email, part, authkey, pwhash, pwsalt, role)
                VALUES (" . $this->escape($player->firstname) . ", " . $this->escape($player->lastname) . ", " .
                $this->escape($player->email) . ", " . $this->escape($player->part) . ", " .
                $this->escape($this->random_string(40)) . ", NULL, " .
                $this->escape($this->random_string(40)) . ", " . $this->escape($player->role) . ")";
        $this->update($sql);
    }

    public function insert_event($event) {
        $sql = "INSERT INTO events (name, description, venue, timestart, timeend, timemodified)
                VALUES (" . $this->escape($event->name) . ", " . $this->escape($event->description) . ", " .
                $this->escape($event->venue) . ", " . $this->escape($event->timestart) . ", " .
                $this->escape($event->timeend) . ", " . time() . ")";
        $this->update($sql);
    }

    public function insert_section($section, $sort) {
        $sql = "INSERT INTO sections (section, sectionsort)
                VALUES (" . $this->escape($section) . ", " . $this->escape($sort) . ")";
        $this->update($sql);
    }

    public function insert_part($part, $section, $sort) {
        $sql = "INSERT INTO parts (part, section, partsort)
                VALUES (" . $this->escape($part) . ", " . $this->escape($section) . ", " .
                $this->escape($sort) . ")";
        $this->update($sql);
    }

    public function random_string($length) {
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

    public function check_installed() {
        if (!$this->table_exists('config')) {
            $this->install();
        }
    }

    protected function get_last_insert_id() {
        return mysql_insert_id($this->conn);
    }

    protected function install() {
        $this->execute_sql("
            CREATE TABLE config (
                name VARCHAR(32) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE = InnoDB
        ");
        $this->execute_sql("
            CREATE TABLE sections (
                section VARCHAR(100) NOT NULL PRIMARY KEY,
                sectionsort INT(10) NOT NULL UNIQUE
            ) ENGINE = InnoDB
        ");
        $this->execute_sql("
            CREATE TABLE parts (
                part VARCHAR(100) NOT NULL PRIMARY KEY,
                section VARCHAR(100) NOT NULL REFERENCES sections (section) ON DELETE RESTRICT ON UPDATE RESTRICT,
                partsort INT(10) NOT NULL,
                CONSTRAINT UNIQUE (section, partsort)
            ) ENGINE = InnoDB
        ");
        $this->execute_sql("
            CREATE TABLE players (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                firstname VARCHAR(100) NOT NULL,
                lastname VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                part VARCHAR(100) NOT NULL REFERENCES parts (part) ON DELETE RESTRICT ON UPDATE RESTRICT,
                authkey VARCHAR(40) NOT NULL,
                pwhash VARCHAR(40) NULL,
                pwsalt VARCHAR(40) NULL,
                role VARCHAR(40) NOT NULL
            ) ENGINE = InnoDB
        ");
        $this->execute_sql("
            CREATE TABLE events (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                venue VARCHAR(100) NOT NULL,
                timestart INT(10) NOT NULL,
                timeend INT(10) NOT NULL,
                timemodified INT(10) NOT NULL
            ) ENGINE = InnoDB
        ");
        $this->execute_sql("
            CREATE TABLE attendances (
                playerid INT(10) NOT NULL REFERENCES players (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                eventid INT(10) NOT NULL REFERENCES events (id) ON DELETE CASCADE ON UPDATE RESTRICT,
                status VARCHAR(32) NOT NULL,
                CONSTRAINT PRIMARY KEY (playerid, eventid)
            ) ENGINE = InnoDB
        ");

        $pwsalt = $this->random_string(40);
        $this->set_config('pwsalt', $pwsalt);
        $this->set_config('icalguid', $this->random_string(40));
        $this->set_config('title', 'OU Orchestra Register');
        $this->set_config('timezone', 'Europe/London');

        $sections = self::load_csv('data/sections.txt');
        foreach ($sections as $section) {
            $this->insert_section($section[1], $section[0]);
        }
        $parts = self::load_csv('data/parts.txt');
        foreach ($parts as $part) {
            $this->insert_part($part[2], $part[0], $part[1]);
        }
        $players = self::load_csv('data/players.txt');
        $firstplayer = true;
        foreach ($players as $data) {
            $player = new player();
            $player->firstname = $data[0];
            $player->lastname = $data[1];
            $player->email = $data[2];
            $player->part = $data[3];
            $player->role = 'player';
            $this->insert_player($player);
            if ($firstplayer) {
                $this->set_password($this->get_last_insert_id(), $pwsalt . 'mozart');
                $firstplayer = false;
            }
        }
        $events = self::load_csv('data/events.txt');
        foreach ($events as $data) {
            $event = new event();
            $event->name = $data[0];
            $event->description = $data[1];
            $event->venue = $data[2];
            $event->timestart = strtotime($data[3] . ' ' . $data[4]);
            $event->timeend = strtotime($data[3] . ' ' . $data[5]);
            $this->insert_event($event);
        }
    }

    public static function load_csv($filename, $skipheader = true) {
        $data = array();
        $handle = fopen(dirname(__FILE__) . '/../' . $filename, 'r');
        if ($skipheader) {
            fgets($handle);
        }
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
        return $data;
    }
}

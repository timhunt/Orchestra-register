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
class installer {
    /** @var database_connection */
    private $connection;
    /** @var database */
    private $db;

    public function __construct(database $db, database_connection $connection) {
        $this->db = $db;
        $this->connection = $connection;
    }

    public function install($pwsalt) {
        $this->connection->execute_sql("
            CREATE TABLE config (
                name VARCHAR(32) NOT NULL PRIMARY KEY,
                value TEXT NOT NULL
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE sections (
                section VARCHAR(100) NOT NULL PRIMARY KEY,
                sectionsort INT(10) NOT NULL UNIQUE
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE parts (
                part VARCHAR(100) NOT NULL PRIMARY KEY,
                section VARCHAR(100) NOT NULL,
                partsort INT(10) NOT NULL,
                CONSTRAINT UNIQUE (section, partsort),
                CONSTRAINT fk_parts_section FOREIGN KEY (section) REFERENCES sections (section)
                        ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE series (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                deleted INT(1) NOT NULL DEFAULT 0
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE events (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                seriesid INT(10) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                venue VARCHAR(100) NOT NULL,
                timestart INT(10) NOT NULL,
                timeend INT(10) NOT NULL,
                timemodified INT(10) NOT NULL,
                deleted INT(1) NOT NULL DEFAULT 0,
                CONSTRAINT fk_events_seriesid FOREIGN KEY (seriesid) REFERENCES series (id)
                        ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE users (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                firstname VARCHAR(100) NOT NULL,
                lastname VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                authkey VARCHAR(40) NOT NULL,
                pwhash VARCHAR(40) NULL,
                pwsalt VARCHAR(40) NULL,
                role VARCHAR(40) NOT NULL
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql("
            CREATE TABLE logs (
                id INT(10) AUTO_INCREMENT PRIMARY KEY,
                timestamp INT(10) NOT NULL,
                userid INT(10) NULL,
                authlevel INT(10) NOT NULL,
                ipaddress VARCHAR(40) NOT NULL,
                action VARCHAR(255) NOT NULL,
                CONSTRAINT fk_logs_userid FOREIGN KEY (userid) REFERENCES users (id)
                        ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE = InnoDB
        ");
        $this->connection->execute_sql('
            CREATE TABLE players (
                userid INT(10) NOT NULL,
                seriesid INT(10) NOT NULL,
                part VARCHAR(100),
                CONSTRAINT PRIMARY KEY (userid, seriesid),
                CONSTRAINT fk_players_userid FOREIGN KEY (userid) REFERENCES users (id)
                        ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT fk_players_seriesid FOREIGN KEY (seriesid) REFERENCES series (id)
                        ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT fk_players_part FOREIGN KEY (part) REFERENCES parts (part)
                        ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE = InnoDB');
        $this->connection->execute_sql("
            CREATE TABLE attendances (
                userid INT(10) NOT NULL,
                seriesid INT(10) NOT NULL,
                eventid INT(10) NOT NULL,
                status VARCHAR(32) NOT NULL,
                CONSTRAINT PRIMARY KEY (userid, seriesid, eventid),
                CONSTRAINT fk_attendances_eventid FOREIGN KEY (eventid) REFERENCES events (id)
                        ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT fk_attendances_userid_seriesid FOREIGN KEY (userid, seriesid)
                        REFERENCES players (userid, seriesid)
                        ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE = InnoDB
        ");

        $series = new series();
        $series->name = 'Rehearsals';
        $series->description = '';
        $this->db->insert_series($series);

        $this->db->set_config('icalguid', database::random_string(40));
        $this->db->set_config('title', 'Orchestra Register');
        $this->db->set_config('timezone', 'Europe/London');
        $this->db->set_config('defaultseriesid', $series->id);

        $parts = database::load_csv('data/parts.txt');
        $sections = array();
        foreach ($parts as $partdata) {
            list($section, $part) = $partdata;
            if (!array_key_exists($section, $sections)) {
                $this->db->insert_section($section);
                $sections[$section] = $section;
            }
            $this->db->insert_part($section, $part);
        }

        $events = database::load_csv('data/events.txt');
        foreach ($events as $data) {
            $event = new event();
            $event->seriesid = $series->id;
            $event->name = $data[0];
            $event->description = $data[1];
            $event->venue = $data[2];
            $event->timestart = strtotime($data[3] . ' ' . $data[4]);
            $event->timeend = strtotime($data[3] . ' ' . $data[5]);
            $this->db->insert_event($event);
        }

        $users = database::load_csv('data/users.txt');
        $firstuser = true;
        foreach ($users as $data) {
            $user = new user();
            $user->firstname = $data[0];
            $user->lastname = $data[1];
            $user->email = $data[2];
            $user->role = 'player';
            if ($firstuser) {
                $user->role = 'admin';
            }
            $this->db->insert_user($user);
            if ($firstuser) {
                $this->db->set_password($user->id, $pwsalt . 'mozart');
                $firstuser = false;
            }
        }
    }

    public function upgrade($fromversion) {
        if ($fromversion < 2009111800) {
            // Allow events to be deleted.
            $this->connection->execute_sql('ALTER TABLE events ADD COLUMN
                    deleted INT(1) NOT NULL DEFAULT 0');
        }

        if ($fromversion < 2009111901) {
            // Add logs table.
            $this->connection->execute_sql("
                CREATE TABLE logs (
                    id INT(10) AUTO_INCREMENT PRIMARY KEY,
                    timestamp INT(10) NOT NULL,
                    userid INT(10) NULL REFERENCES players (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                    authlevel INT(10) NOT NULL,
                    ipaddress VARCHAR(40) NOT NULL,
                    action VARCHAR(100) NOT NULL
                ) ENGINE = InnoDB
            ");
        }

        if ($fromversion < 2009112302) {
            // Increase the sizes of two columns.
            $this->connection->execute_sql("
                ALTER TABLE logs MODIFY COLUMN action VARCHAR(255) NOT NULL
            ");
            $this->connection->execute_sql("
                ALTER TABLE config MODIFY COLUMN value TEXT NOT NULL
            ");
        }

        if ($fromversion < 2009112303) {
            // Password salt moving to config.php.
            $this->connection->execute_sql("
                DELETE FROM config WHERE name = 'pwsalt';
            ");
        }

        if ($fromversion < 2010040301) {

            // Create a new series table.
            $this->connection->execute_sql("
                CREATE TABLE series (
                    id INT(10) AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT NOT NULL,
                    deleted INT(1) NOT NULL DEFAULT 0
                ) ENGINE = InnoDB
            ");

            // Insert a series to represent the implicit series we have had so far.
            $series = new series();
            $series->name = 'Rehearsals';
            $series->description = '';
            $this->db->insert_series($series);

            // Add seriesid column to the events table.
            // The default is temporary, just to initialise things.
            $this->connection->execute_sql('ALTER TABLE events ADD COLUMN
                    seriesid INT(10) NOT NULL
                        DEFAULT ' . $this->connection->escape($series->id));
            $this->connection->execute_sql('ALTER TABLE events ADD CONSTRAINT
                        fk_events_seriesid FOREIGN KEY (seriesid) REFERENCES series (id)
                                ON DELETE RESTRICT ON UPDATE RESTRICT');

            // Now drop the default.
            $this->connection->execute_sql('ALTER TABLE events ALTER COLUMN
                    seriesid DROP DEFAULT');

            // Rename the players table to users, in preparation for splitting.
            $this->connection->execute_sql('ALTER TABLE players RENAME TO users');

            // Create new players table.
            $this->connection->execute_sql('
                CREATE TABLE players (
                    userid INT(10) NOT NULL,
                    seriesid INT(10) NOT NULL,
                    part VARCHAR(100),
                    CONSTRAINT PRIMARY KEY (userid, seriesid),
                    CONSTRAINT fk_players_userid FOREIGN KEY (userid) REFERENCES users (id)
                            ON DELETE RESTRICT ON UPDATE RESTRICT,
                    CONSTRAINT fk_players_seriesid FOREIGN KEY (seriesid) REFERENCES series (id)
                            ON DELETE RESTRICT ON UPDATE RESTRICT,
                    CONSTRAINT fk_players_part FOREIGN KEY (part) REFERENCES parts (part)
                            ON DELETE RESTRICT ON UPDATE RESTRICT
                ) ENGINE = InnoDB');

            // Populate the players table.
            $this->connection->update('
                INSERT INTO players (userid, seriesid, part)
                SELECT id, ' . $this->connection->escape($series->id) . ',
                        CASE WHEN deleted <> 0 THEN NULL ELSE part END
                FROM users');

            // Prepare to drop the user.deleted column.
            $this->connection->update("
                UPDATE users SET role = '" . user::DISABLED . "' WHERE deleted = 1");

            // Drop the users.part and users.deleted columns.
            $this->connection->execute_sql('ALTER TABLE users DROP COLUMN part');
            $this->connection->execute_sql('ALTER TABLE users DROP COLUMN deleted');

            // Add a seriesid column to the attendances table (temp default again).
            $this->connection->execute_sql('ALTER TABLE attendances ADD COLUMN
                    seriesid INT(10) NOT NULL
                        DEFAULT ' . $this->connection->escape($series->id));

            // Now drop the default.
            $this->connection->execute_sql('ALTER TABLE attendances ALTER COLUMN
                    seriesid DROP DEFAULT');

            // Now drop the default.
            $this->connection->execute_sql('ALTER TABLE attendances CHANGE COLUMN
                    playerid userid INT(10) NOT NULL');

            // Change the primary key definition.
            $this->connection->execute_sql('ALTER TABLE attendances DROP PRIMARY KEY');
            $this->connection->execute_sql('ALTER TABLE attendances ADD CONSTRAINT
                    PRIMARY KEY (userid, seriesid, eventid)');

            // Finally, add a new foreign key to players.
            $this->connection->execute_sql('ALTER TABLE attendances ADD CONSTRAINT
                    fk_attendances_userid_seriesid FOREIGN KEY (userid, seriesid)
                            REFERENCES players (userid, seriesid)
                            ON DELETE RESTRICT ON UPDATE RESTRICT');
        }

        if ($fromversion < 2010040302) {
            // Add in foreign keys which should have been created already, but
            // weren't becuase of MySQL weirdness.
            $this->connection->execute_sql('ALTER TABLE parts ADD CONSTRAINT
                    fk_parts_section FOREIGN KEY (section) REFERENCES sections (section)
                    ON DELETE RESTRICT ON UPDATE RESTRICT');

            $this->connection->execute_sql('ALTER TABLE logs ADD CONSTRAINT
                    fk_logs_userid FOREIGN KEY (userid) REFERENCES users (id)
                    ON DELETE RESTRICT ON UPDATE RESTRICT');

            $this->connection->execute_sql('ALTER TABLE attendances ADD CONSTRAINT
                    fk_attendances_eventid FOREIGN KEY (eventid) REFERENCES events (id)
                    ON DELETE CASCADE ON UPDATE RESTRICT');
        }

        if ($fromversion < 2010040303) {
            $row = $this->connection->get_record_sql(
                    'SELECT MIN(id) AS id FROM series WHERE deleted = 0', 'stdClass');
            $this->db->set_config('defaultseriesid', $row->id);
        }
    }
}

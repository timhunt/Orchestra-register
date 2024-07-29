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
    /** @var mysqli */
    private mysqli $conn;

    /** @var transaction[] stack of currently active transactions. */
    private array $transactions = [];
    private bool $isrolledback = false;

    public function __construct(string $dbhost, string $dbuser, string $dbpass, string $dbname) {
        $this->conn = new mysqli($dbhost, $dbuser, $dbpass);
        if (!$this->conn) {
            throw new database_connect_exception($this->get_last_error());
        }
        if (!$this->conn->select_db($dbname)) {
            throw new database_connect_exception($this->get_last_error());
        }
    }

    public function escape(?string $value, ?int $maxlength = null): string {
        if (is_null($value)) {
            return 'NULL';
        }
        if ($maxlength) {
            $value = substr($value, 0, $maxlength - 1);
        }
        return "'" . $this->conn->real_escape_string($value) . "'";
    }

    public function get_last_insert_id(): int|string {
        return $this->conn->insert_id;
    }

    public function get_last_error(): string {
        return $this->conn->error;
    }

    public function execute_sql(string $sql): mysqli_result {
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new database_exception('Failed to load or save data from the databse.',
                    $this->get_last_error() . '. SQL: ' . $sql);
        }
        return $result;
    }

    public function update(string $sql): void {
        $this->execute_sql($sql);
    }

    public function set_field(string $table, string $column, ?string $newvalue, string $where): void {
        $this->update("UPDATE $table SET $column = " . $this->escape($newvalue) .
                "WHERE $where");
    }

    public function table_exists(string $name): bool {
        $result = $this->execute_sql("SHOW TABLES LIKE {$this->escape($name)}");
        $exists = $result->num_rows;
        $result->close();
        return (bool) $exists;
    }

    public function get_record_select(string $table, string $where, string $class): mixed {
        return $this->get_record_sql("SELECT * FROM $table WHERE $where", $class);
    }

    /**
     * @param string $sql
     * @param string $class
     * @return mixed
     * @throws database_exception
     */
    public function get_record_sql(string $sql, string $class): mixed {
        $object = null;
        $result = $this->execute_sql($sql);
        if ($result->num_rows == 1) {
            $object = $result->fetch_object($class);
        }
        $result->close();
        return $object;
    }

    public function get_records(string $table, string $class, string $order = ''): array {
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $order, $class);
    }

    public function get_records_select(string $table, string $where, string $class, string $order = ''): array {
        if ($where) {
            $where = ' WHERE ' . $where;
        }
        if ($order) {
            $order = ' ORDER BY ' . $order;
        }
        return $this->get_records_sql('SELECT * FROM ' . $table . $where . $order, $class);
    }

    public function get_records_sql(string $sql, string $class): array {
        $result = $this->execute_sql($sql);
        $objects = [];
        while ($object = $result->fetch_object($class)) {
            if (!empty($object->id)) {
                $objects[$object->id] = $object;
            } else {
                $objects[] = $object;
            }
        }
        $result->close();
        return $objects;
    }

    public function count_records(string $table) {
        $record = $this->get_record_sql('SELECT COUNT(1) AS count FROM ' . $table, 'stdClass');
        if (!$record) {
            throw new database_exception('Failed to count data in the databse.', 'Table ' . $table);
        }
        return $record->count;
    }

    public function begin_transaction(): transaction {
        if (empty($this->transactions)) {
            $this->execute_sql('BEGIN');
        }
        $transaction = new transaction($this);
        $this->transactions[] = $transaction;
        return $transaction;
    }

    protected function verify_right_transaction(transaction $transaction): void {
        $nexttrans = array_pop($this->transactions);
        if ($nexttrans !== $transaction) {
            throw new coding_error('Transactions incorrectly nested.');
        }
        $transaction->dispose();
    }

    public function commit_transaction(transaction $transaction): void {
        if ($this->isrolledback) {
            $this->rollback_transaction($transaction);
        }

        $this->verify_right_transaction($transaction);

        if (empty($this->transactions)) {
            $this->execute_sql('COMMIT');
        }
    }

    public function rollback_transaction(transaction $transaction): void {
        $this->verify_right_transaction($transaction);

        if (empty($this->transactions)) {
            $this->execute_sql('ROLLBACK');
            $this->isrolledback = false;
        } else {
            $this->isrolledback = true;
        }
    }

    public function __destruct() {
        if ($this->transactions) {
            $this->execute_sql('ROLLBACK');
            $this->transactions = [];
            $this->isrolledback = false;
        }
    }
}

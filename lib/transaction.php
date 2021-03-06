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
 * Transaction class.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction {
    /** @var database_connection */
    private $connection;

    public function __construct(database_connection $connection) {
        $this->connection = $connection;
    }

    protected function is_disposed() {
        return empty($this->connection);
    }

    public function dispose() {
        return $this->connection = null;
    }

    public function commit() {
        if ($this->is_disposed()) {
            throw new coding_error('Transactions already disposed');
        }
        $this->connection->commit_transaction($this);
    }

    public function rollback() {
        if ($this->is_disposed()) {
            throw new coding_error('Transactions already disposed');
        }
        $this->connection->rollback_transaction($this);
    }
}

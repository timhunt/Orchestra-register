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
 * Class with methods to safely extract information from the request.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    const TYPE_INT = 1;
    const TYPE_ATTENDANCE = 2;
    const TYPE_EMAIL = 3;
    const TYPE_BOOL = 4;
    const TYPE_DATE = 5;
    const TYPE_RAW = 666;
    const TYPE_AUTHTOKEN = '/[a-zA-Z0-9]{40}/';
    const TYPE_TIME = '/\d\d?:\d\d?(?::\d\d?)?/';
    const TYPE_ALNUMSPACE = '/^[a-zA-Z0-9 ]*$/';
    public static array $typenames = [
        self::TYPE_INT => 'integer',
        self::TYPE_ATTENDANCE => 'attendance status',
        self::TYPE_EMAIL => 'email address',
        self::TYPE_DATE => 'date',
        self::TYPE_BOOL => 'boolean',
        self::TYPE_RAW => 'anything',
        self::TYPE_AUTHTOKEN => 'authentication token',
        self::TYPE_TIME => 'time',
        self::TYPE_ALNUMSPACE => 'string of letters, numbers and spaces only',
    ];

    public function get_param(string $name, int $type, mixed $default = null, bool $postonly = true): mixed {
        if (array_key_exists($name, $_POST)) {
            $raw = $_POST[$name];
        } else if (!$postonly && array_key_exists($name, $_GET)) {
            $raw = $_GET[$name];
        } else {
            return $default;
        }
        if ($type == self::TYPE_BOOL) {
            return $this->bool_value($raw);
        }
        if ($this->validate($raw, $type)) {
            return $raw;
        } else {
            return $default;
        }
    }

    protected function bool_value(string $raw): bool {
        return $raw !== '' && $raw !== '0' && $raw !== 'false' && $raw !== 'no';
    }

    public function get_array_param(string $name, int $type, mixed $default = null, bool $postonly = true): array {
        if (array_key_exists($name, $_POST)) {
            $raw = $_POST[$name];
        } else if (!$postonly && array_key_exists($name, $_GET)) {
            $raw = $_GET[$name];
        } else {
            return $default;
        }
        if (!is_array($raw)) {
            return $default;
        }
        $clean = array();
        foreach ($raw as $key => $value) {
            if ($type == self::TYPE_BOOL) {
                $clean[$key] = $this->bool_value($value);
            } else if ($this->validate($value, $type)) {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    public function validate(string $raw, int $type): bool {
        switch ($type) {
            case self::TYPE_INT:
                return strval(intval($raw)) === $raw;
            case self::TYPE_ATTENDANCE:
                return array_key_exists($raw, attendance::$symbols) || $raw === 'nochange';
            case self::TYPE_EMAIL:
                return filter_var($raw, FILTER_VALIDATE_EMAIL);
            case self::TYPE_DATE:
                return strtotime($raw . ' 00:00') !== false;
            case self::TYPE_RAW:
                return true;
            default:
                return preg_match($type, $raw);
        }
    }

    public static function get_ip_address(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP']; // share internet
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR']; // pass from proxy
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

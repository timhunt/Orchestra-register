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
 * Class recording a player's status in relation to a paritcular event.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance {
    const UNKNOWN = 'unknown';
    const UNSURE = 'unsure';
    const YES = 'yes';
    const NO = 'no';
    const NOTREQUIRED = 'notrequired';
    public static $symbols = array(
        self::UNKNOWN => ' ',
        self::YES => 'Yes',
        self::NO => 'No',
        self::UNSURE => '?',
        self::NOTREQUIRED => '-',
    );
    public $eventid;
    public $userid;
    public $seriesid;
    public $status = self::UNKNOWN;
    public function get_symbol() {
        return self::$symbols[$this->status];
    }
    public function get_field_name() {
        return 'att_' . $this->userid . '_' . $this->eventid;
    }
    public function get_select($includenoneeded) {
        if (!$includenoneeded && $this->status == attendance::NOTREQUIRED) {
            return $this->get_symbol();
        }
        $output = '<select name="' . $this->get_field_name() . '" class="statusselect" id="' .
                $this->get_field_name() . '">';
        foreach (self::$symbols as $value => $symbol) {
            if (!$includenoneeded && $value == self::NOTREQUIRED) {
                continue;
            }
            if ($this->status == $value) {
                $selected = ' selected="selected"';
                $actualvalue = 'nochange';
            } else {
                $selected = '';
                $actualvalue = $value;
            }
            $output .= '<option class="' . $value . '" value="' . $actualvalue . '"' .
                    $selected . '>' . $symbol . '</option>';
        }
        $output .= '</select>';
        return $output;
    }
}

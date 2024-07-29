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
    public static array $symbols = [
        self::UNKNOWN => ' ',
        self::YES => 'Yes',
        self::NO => 'No',
        self::UNSURE => '?',
        self::NOTREQUIRED => '-',
    ];
    public int $eventid;
    public int $userid;
    public int $seriesid;
    public string $status = self::UNKNOWN;
    public function get_symbol() {
        return self::$symbols[$this->status];
    }
    public function get_field_name(): string {
        return 'att_' . $this->userid . '_' . $this->eventid;
    }

    protected function get_choices($includenoneeded): array {
        $choices = [];

        foreach (self::$symbols as $value => $symbol) {
            if (!$includenoneeded && $value == self::NOTREQUIRED) {
                continue;
            }
            $choices[$value] = $symbol;
        }

        return $choices;
    }

    public function get_select($includenoneeded) {
        if (!$includenoneeded && $this->status == attendance::NOTREQUIRED) {
            return $this->get_symbol();
        }
        $output = '<select name="' . $this->get_field_name() . '" class="statusselect" id="' .
                $this->get_field_name() . '">';
        foreach ($this->get_choices($includenoneeded) as $value => $symbol) {
            if ($value == $this->status) {
                $actualvalue = 'nochange';
                $selected = ' selected="selected"';
            } else {
                $actualvalue = $value;
                $selected = '';
            }
            $output .= '<option class="' . $value . '" value="' . $actualvalue . '"' .
                    $selected . '>' . $symbol . '</option>';
        }
        $output .= '</select>';
        return $output;
    }

    public function get_radio($editable, $includenoneeded) {
        if (!$includenoneeded && $this->status == attendance::NOTREQUIRED) {
            return $this->get_symbol();
        }

        $output = '';
        foreach ($this->get_choices($includenoneeded) as $value => $symbol) {
            if ($value == $this->status) {
                $actualvalue = 'nochange';
                $checked = ' checked="checked"';
            } else {
                $actualvalue = $value;
                $checked = '';
            }
            $disabled = '';
            if (!$editable) {
                $disabled = ' disabled="disabled"';
            }
            $output .= '<label for="' . $this->get_field_name() . '-' . $value .
                    '" class="status ' . $value . '"><input type="radio" name="' .
                    $this->get_field_name() . '" value="' . $actualvalue . '"' .
                    $checked . $disabled . ' id="' . $this->get_field_name() . '-' . $value .
                    '" />' . $symbol . '</label>';
        }

        return $output;
    }
}

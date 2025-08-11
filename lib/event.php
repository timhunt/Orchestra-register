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
 * Class holding the data about one event.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event {
    const DATE_FORMAT = 'D j M';
    const TIME_FORMAT = 'H:i';
    public int $id;
    public int $seriesid;
    public string $name;
    public string $description;
    public string $venue;
    public int $timestart;
    public int $timeend;
    public int $timemodified;
    public int $deleted = 0;

    protected function wrap_in_span($content, $class): string {
        return '<span class="' . $class . '">' . $content . '</span>';
    }

    public function get_nice_datetime($dateformat = self::DATE_FORMAT, $html = true): string {
        if (date('Y', $this->timestart) != date('Y', time())) {
            $dateformat .= ' Y';
        }
        $startdate = date($dateformat, $this->timestart);
        $enddate = date($dateformat, $this->timeend);
        $starttime = date(self::TIME_FORMAT, $this->timestart);
        $endtime = date(self::TIME_FORMAT, $this->timeend);
        if ($html) {
            $startdate = $this->wrap_in_span($startdate, 'date');
            $enddate = $this->wrap_in_span($enddate, 'date');
            $starttime = $this->wrap_in_span($starttime, 'time');
            $endtime = $this->wrap_in_span($endtime, 'time');
        }
        if ($startdate == $enddate) {
            return $starttime . ' - ' . $endtime . ', ' . $startdate;
        } else {
            return $starttime . ', ' . $startdate . ' - ' . $endtime . ', ' . $enddate;
        }
    }
}

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
 * Class representing an array of action links.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actions {
    protected array $actions = [];

    public function add($url, $linktext, $allowed = true): void {
        if ($allowed) {
            $this->actions[$url] = $linktext;
        }
    }

    public function output(html_output $output): string {
        return $output->action_menu($this->actions);
    }

    public function is_empty(): bool {
        return empty($this->actions);
    }
}

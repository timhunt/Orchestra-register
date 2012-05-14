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
 * Library funcitions to help with sending email.
 *
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class mail_helper {
    /** @var orchestra_register */
    protected $or;

    public function __construct(orchestra_register $or) {
        $this->or = $or;
    }

    public function forgotten_url_mailto(player $user) {
        $body = $user->firstname . ",\n\nThe URL you need to use to edit your attendance in the " .
                $this->or->get_title() . " is:\n\n" . $this->or->url('', false) . "?t=" .
                $user->authkey . "\n\nThanks,\n\n" . $this->or->get_current_user()->firstname;
        return $this->make_mailto_url($user->email, $this->or->get_title() . ' URL reminder', $body);
    }

    protected function make_mailto_url($to, $subject, $body) {
        return str_replace('+', '%20', 'mailto:' . urlencode($to) .
                '?subject=' . urlencode($subject) .
                '&body=' . urlencode($body));
    }
}
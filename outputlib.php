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
 * Output helper functions.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class html_output {
    protected $javascriptcode = array();

    public function header($or, $subhead = '') {
        $title = $or->get_title();
        if ($subhead) {
            $title = $subhead . ' - ' . $title;
        }
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html>
    <head>
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $or->url('styles.css', false); ?>" />
    </head>
    <body>
    <div class="logininfo"><?php echo $or->get_login_info(); ?></div>
    <h1><?php echo $or->get_title(); ?></h1>
    <?php
        if ($subhead) {
            echo '<h2>' . $subhead . '</h2>';
        }
    }

    public function footer($or) {
    ?>
    <div class="footer"><?php echo $or->version_string(); ?></div>
    <script type="text/javascript" src="<?php echo $or->url('thirdparty/yui-min.js', false); ?>"></script>
    <script type="text/javascript" src="<?php echo $or->url('script.js', false); ?>"></script>
    <?php
    if ($this->javascriptcode) {
        echo '<script type="text/javascript">' . implode("\n", $this->javascriptcode) . '</script>';
    }
    ?>
    </body>
    </html>
    <?php
    }

    public function call_to_js($function, $arguments = array()) {
        $quotedargs = array();
        foreach ($arguments as $arg) {
            $quotedargs[] = json_encode($arg);
        }
        $this->javascriptcode[] = $function . '(' . implode(', ', $quotedargs) . ');';
    }
}
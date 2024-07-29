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
use JetBrains\PhpStorm\NoReturn;

/**
 * Our exception classes and some catching code.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class register_exception extends Exception {
    protected ?string $debuginfo;
    public function __construct(string $message, ?string $debuginfo = null, int $code = 500) {
        parent::__construct($message, $code);
        $this->debuginfo = $debuginfo;
    }
    public function get_summary(): string {
        return 'An error occurred';
    }
    public function get_debug_info(): ?string {
        return $this->debuginfo;
    }
}

class configuration_exception extends register_exception {
    protected ?string $debuginfo;
    public function __construct(string $message, ?string $debuginfo = null) {
        parent::__construct($message, $debuginfo);
    }
    public function get_summary(): string {
        return 'Configuration problem';
    }
}

class forbidden_operation_exception extends register_exception {
    public function __construct(string $message, ?string $debuginfo = null) {
        parent::__construct($message, $debuginfo, 403);
    }
    public function get_summary(): string {
        return 'Request forbidden';
    }
}

class permission_exception extends forbidden_operation_exception {
    public function get_summary(): string {
        return 'Permission denied';
    }
}

class not_found_exception extends register_exception {
    public function __construct(string $message, ?string $debuginfo = null) {
        parent::__construct($message, $debuginfo, 404);
    }
    public function get_summary(): string {
        return 'Not found';
    }
}

class database_connect_exception extends register_exception {
    public function __construct(?string $debuginfo = null) {
        parent::__construct('The database may be overloaded or otherwise not running properly. ' .
                'The site administrator should also check that the database details ' .
                'have been correctly specified in config.php.', $debuginfo);
    }
    public function get_summary(): string {
        return 'Error connecting to the database';
    }
}

class database_exception extends register_exception {
    public function get_summary(): string {
        return 'Database error';
    }
}

class coding_error extends register_exception {
    public function __construct(string $message, ?string $debuginfo = null, $code = 500) {
        parent::__construct($message . ' Please report this bug to the developers.', $debuginfo, $code);
    }
    public function get_summary(): string {
        return 'A bug was detected';
    }
}

function prepare_exception(Throwable $e) {
    $code = $e->getCode();
    if ($code < 400) {
        $code = 500;
    }
    http_response_code($code);

    if ($e instanceof register_exception) {
        $summary = $e->get_summary();
        $debuginfo = $e->get_debug_info();
    } else {
        $summary = 'An error occurred';
        $debuginfo = '';
    }

    $log = "Exception caught: " . $summary . ", " . $e->getMessage() . ", ";
    if ($debuginfo) {
        $log .= $debuginfo . ", ";
    }
    error_log($log . $e->getTraceAsString(), E_USER_ERROR);

    return $summary;
}


#[NoReturn] function early_exception_handler(Throwable $e): void {
    $summary = prepare_exception($e);
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang = "en">
<head>
<title>Error - Orchestra register</title>
<style type="text/css">
body {
    font: 14px/1.2 Verdana, sans-serif;
}
h1 {
    font-weight: bold;
    font-size: 140%;
    margin: 0 0 0.5em;
}
h2 {
    font-weight: bold;
    font-size: 125%;
    margin: 1.2em 0 0.4em;
}
p {
    margin: 0.5em 0;
}
.errorbox {
    margin: 2em 20%;
    padding: 0 2em 1.5em;
    border: 1px solid #800;
    background: #ffe4e4;
}
</style>
</head>
<body>
<h1>Orchestra register</h1>

<div class="errorbox">
<h2><?php echo $summary; ?></h2>
<p><?php echo $e->getMessage(); ?></p>
</div>
<p><a href=".">Continue</a></p>
</body>
</html>
<?php
    die;
}

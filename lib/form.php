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
 * Class for representing a HTML form.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class form {
    const CANCELLED = -1;
    const NOTHING = 0;
    const INVALID = 1;
    const SUBMITTED = 2;

    protected $state = null;
    protected $actionurl;
    protected $method = 'post';
    protected $fields = array();
    protected $submitlabel;
    protected $hascancel;

    public function __construct($actionurl, $submitlabel = 'Save changes', $hascancel = true) {
        $this->actionurl = $actionurl;
        $this->submitlabel = $submitlabel;
        $this->hascancel = $hascancel;
    }

    public function add_field(form_field $field) {
        $this->fields[$field->get_name()] = $field;
    }

    /**
     * @param string $name
     * @return form_field
     */
    public function get_field($name) {
        return $this->fields[$name];
    }

    /**
     * @param $_
     */
    public function set_required_fields($_) {
        foreach (func_get_args() as $field) {
            $this->fields[$field]->set_required(true);
        }
    }

    public function set_initial_data($data) {
        foreach ($this->fields as $field) {
            $field->set_initial($data);
        }
    }

    public function parse_request(orchestra_register $or) {
        if ($or->get_param('cancel', request::TYPE_BOOL, false)) {
            return $this->state = self::CANCELLED;
        }

        if (!$or->get_param('submit', request::TYPE_BOOL, false)) {
            return $this->state = self::NOTHING;
        }

        $or->require_sesskey();

        $isvalid = true;
        foreach ($this->fields as $field) {
            if (!$field->parse_request($or)) {
                $isvalid = false;
            }
        }

        if ($isvalid) {
            return $this->state = self::SUBMITTED;
        } else {
            return $this->state = self::INVALID;
        }
    }

    public function set_field_error($name, $message) {
        $this->get_field($name)->set_error($message);
        if ($this->state == self::SUBMITTED) {
            $this->state = self::INVALID;
        }
    }

    public function get_outcome() {
        if (is_null($this->state)) {
            throw new coding_error('Call to form::get_outcome without first calling ' .
                    'form::parse_request.');
        }
        return $this->state;
    }

    public function get_field_value($name) {
        return $this->get_field($name)->get_current();
    }

    public function get_submitted_data($class) {
        $object = new $class();
        foreach ($this->fields as $name => $field) {
            $object->$name = $field->get_submitted();
        }
        return $object;
    }

    public function output(html_output $output) {
        $html = $output->start_form($this->actionurl, $this->method);
        $html .= $output->sesskey_input();
        foreach ($this->fields as $field) {
            $html .= $field->output($output);
        }
        $buttons = $output->submit_button('submit', $this->submitlabel);
        if ($this->hascancel) {
            $buttons .= ' ' . $output->submit_button('cancel', 'Cancel');
        }
        $html .= '<p>' . $buttons . '</p>';
        $html .= $output->end_form();
        return $html;
    }
}

abstract class form_field {
    protected $label;
    protected $name;
    protected $note = '';
    protected $error = '';
    protected $default = null;
    protected $initial = null;
    protected $submitted = null;
    protected $raw = null;
    protected $isrequired = false;

    public function __construct($name, $label) {
        $this->name = $name;
        $this->label = $label;
    }

    abstract public function parse_request(orchestra_register $or);

    public function set_initial($data) {
        if (is_array($data) && isset($data[$this->name])) {
            $this->initial = $data[$this->name];
        } else if (is_object($data) && isset($data->{$this->name})) {
            $this->initial = $data->{$this->name};
        }
    }

    public function set_required($isrequired) {
        $this->isrequired = $isrequired;
    }

    public function set_note($note) {
        $this->note = $note;
    }

    public function set_error($message) {
        $this->error = $message;
        if (is_null($this->raw)) {
            $this->raw = $this->submitted;
        }
        $this->submitted = null;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_submitted() {
        if (is_null($this->submitted)) {
            throw new coding_error('No submitted data.');
        }
        return $this->submitted;
    }

    public function get_current() {
        if (!is_null($this->submitted)) {
            return $this->submitted;
        } else if (!is_null($this->raw)) {
            return $this->raw;
        } else {
            return $this->get_initial();
        }
    }

    public function get_initial() {
        if (!is_null($this->initial)) {
            return $this->initial;
        } else if (!is_null($this->default)) {
            return $this->default;
        } else {
            return '';
        }
    }

    public function output(html_output $output) {
        if ($this->error) {
            $note = '<span class="error">' . $this->error . '</span>';
        } else if ($this->note) {
            $note = $this->note;
        } else if ($this->isrequired) {
            $note = 'Required';
        } else {
            $note = '';
        }
        return $output->form_field($this->label, $this->output_field($output), $note);
    }

    public abstract function output_field(html_output $output);
}

abstract class single_value_field extends form_field {
    protected $type;

    public function __construct($name, $label, $type, $default = null) {
        parent::__construct($name, $label);
        $this->type = $type;
        $this->default = $default;
    }

    public function parse_request(orchestra_register $or) {
        $this->submitted = $or->get_param($this->name, $this->type);
        if (is_null($this->submitted)) {
            $this->error = 'Not a valid ' . request::$typenames[$this->type];
            $this->raw = $or->get_param($this->name, request::TYPE_RAW);
            return false;
        }
        if ($this->isrequired && $this->submitted === '') {
            $this->error = 'Required';
            return false;
        }
        return true;
    }
}

class hidden_field extends single_value_field {
    public function __construct($name, $type, $default) {
        parent::__construct($name, '', $type, $default);
    }

    public function output_field(html_output $output) {
        return '<input type="hidden" id="' . $this->name . '" name="' . $this->name .
                '" value="' . $this->get_current() . '" />';
    }
}

class text_field extends single_value_field {
    public function output_field(html_output $output) {
        return '<input type="text" id="' . $this->name . '" name="' . $this->name .
                '" value="' . $this->get_current() . '" />';
    }
}

class date_field extends text_field {
    public function __construct($name, $label, $default = null) {
        parent::__construct($name, $label, request::TYPE_DATE, $default);
    }
    public function output(html_output $output) {
        $output->call_to_js('init_date_hint', array($this->name));
        return parent::output($output);
    }
}

class time_field extends text_field {
    public function __construct($name, $label, $default = null) {
        parent::__construct($name, $label, request::TYPE_TIME, $default);
    }
}

class password_field extends single_value_field {
    public function output_field(html_output $output) {
        return '<input type="password" id="' . $this->name . '" name="' . $this->name .
                '" value="' . $this->get_current() . '" />';
    }
    public function parse_request(orchestra_register $or) {
        $isvalid = parent::parse_request($or);
        $this->raw = '';
        return $isvalid;
    }
    public function set_error($message) {
        parent::set_error($message);
        $this->raw = '';
    }
}

class select_field extends single_value_field {
    public $choices;

    public function __construct($name, $label, $choices, $default = null) {
        parent::__construct($name, $label, request::TYPE_RAW, $default);
        $this->choices = $choices;
    }

    public function parse_request(orchestra_register $or) {
        if (parent::parse_request($or) && array_key_exists($this->submitted, $this->choices)) {
            return true;
        }
        $this->submitted = null;
        return false;
    }

    public function output_field(html_output $output) {
        return $output->select($this->name, $this->choices, $this->get_current());
    }
}

class group_select_field extends single_value_field {
    public $choices;

    public function __construct($name, $label, $choices, $default = null) {
        parent::__construct($name, $label, request::TYPE_RAW, $default);
        $this->choices = $choices;
    }

    public function parse_request(orchestra_register $or) {
        if (parent::parse_request($or)) {
            foreach ($this->choices as $groupoptions) {
                if (array_key_exists($this->submitted, $groupoptions)) {
                    return true;
                }
            }
        }
        $this->submitted = null;
        return false;
    }

    public function output_field(html_output $output) {
        return $output->group_select($this->name, $this->choices, $this->get_current());
    }
}

class timezone_field extends select_field {
    public function __construct($name, $label, $default = null) {
        $zones = DateTimeZone::listIdentifiers();
        parent::__construct($name, $label, array_combine($zones, $zones), $default);
    }
}

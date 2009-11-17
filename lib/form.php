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

    public function __construct($actionurl, $submitlabel = 'Save', $hascancel = true) {
        $this->actionurl = $actionurl;
        $this->submitlabel = $submitlabel;
        $this->hascancel = $hascancel;
    }

    public function add_field(form_field $field) {
        $this->fields[$field->name] = $field;
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
            $this->fields[$field]->isrequired = true;
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

    public function get_outcome() {
        if (is_null($this->state)) {
            throw new Exception('Must call parse_request before calling get_outcome on a form.');
        }
        return $this->state;
    }

    public function get_submitted_data($class) {
        
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
    public $label;
    public $name;
    public $note = '';
    public $error = '';
    public $default = null;
    public $initial = null;
    public $submitted = null;
    public $isrequired = false;

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

    public function get_current() {
        if (!is_null($this->submitted)) {
            return $this->submitted;
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
    public $type;

    public function __construct($name, $label, $type, $default = null) {
        parent::__construct($name, $label);
        $this->type = $type;
        $this->default = $default;
    }

    public function parse_request(orchestra_register $or) {
        $this->submitted = $or->get_param($this->name, $this->type, null);
        if (is_null($this->submitted)) {
            $this->error = 'Invalid input';
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

class password_field extends single_value_field {
    public function output_field(html_output $output) {
        return '<input type="password" id="' . $this->name . '" name="' . $this->name .
                '" value="' . $this->get_current() . '" />';
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

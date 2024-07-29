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
 * Output helper functions.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class html_output {
    const EXTRA_STYLES = 'styles-extra.css';
    protected orchestra_register $or;
    protected array $javascriptcode = [];
    protected bool $headeroutput = false;
    protected ?Parsedown $markdownparser = null;

    public function __construct(orchestra_register $or) {
        $this->or = $or;
    }

    #[NoReturn] public function exception(Throwable $e): void {
        $summary = prepare_exception($e);

        $this->javascriptcode = [];
        if (!$this->headeroutput) {
            $this->header('', '', false);
        }

        echo '<div class="errorbox">';
        echo '<h2>' . $summary . '</h2>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '</div>';
        echo $this->back_link('Continue');

        $this->footer();
        die;
    }

    public function header(string $subhead = '', string $bodyclass = '',
            bool $showlogin = true, bool $hidemaintenance = false): void {
        $title = $this->or->get_title();
        if ($subhead) {
            $title = $subhead . ' - ' . $title;
        }
        if ($bodyclass) {
            $bodyclass = ' class="' . $bodyclass . '"';
        }
        $stylesheets = [$this->or->url('styles.css', false, true, 'none')];
        if (is_readable(__DIR__ . '/../' . self::EXTRA_STYLES)) {
            $stylesheets[] = $this->or->url(self::EXTRA_STYLES, false, true, 'none');
        }
        if ($showlogin) {
            $logininfo = $this->or->get_login_info();
        } else {
            $logininfo = '';
        }
        $this->headeroutput = true;
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en">
<head>
<title><?php echo $title; ?></title>
    <?php
    foreach ($stylesheets as $stylesheet) {
        ?>
<link rel="stylesheet" type="text/css" href="<?php echo $stylesheet; ?>" />
        <?php
    }
    ?>
</head>
<body<?php echo $bodyclass; ?>>
<div class="logininfo"><?php echo $logininfo; ?></div>
<h1><?php echo $this->or->get_title(); ?></h1>
    <?php
        if (!$hidemaintenance && $this->or->is_in_maintenance_mode()) {
            echo '<div class="errorbox">';
            echo '<p>The system is in maintenance mode. No changes can be made at the moment. If you need to update anything, please try again later.</p>';
            echo '</div>';
        }
        if ($subhead) {
            echo '<h2>' . $subhead . '</h2>';
        }
    }

    public function footer(): void {
        if ($helpurl = $this->or->get_help_url()) {
            $helplink = ' <a href="' . $helpurl . '">Get help</a> -';
        } else {
            $helplink = '';
        }
    ?>
<div class="footer"><span class="helplinks"><a href="doc/">Documentation</a> -<?php echo $helplink; ?></span>
    Powered by <a href="https://github.com/timhunt/Orchestra-register">Orchestra register</a></div>
<script type="text/javascript" src="<?php echo $this->or->url('thirdparty/yui-min.js', false, true, 'none'); ?>"></script>
<script type="text/javascript" src="<?php echo $this->or->url('script.js', false, true, 'none'); ?>"></script>
    <?php
    if ($this->javascriptcode) {
        echo '<script type="text/javascript">' . implode("\n", $this->javascriptcode) . '</script>';
    }
    ?>
</body>
</html>
    <?php
    }

    public function action_button($url, $params, $label, $method = 'post'): string {
        $output = '<form action="' . $url . '" method="' . $method . '"><div>';
        if ($method == 'post') {
            $output .= $this->sesskey_input();
        }
        foreach ($params as $name => $value) {
            $output .= '<input type="hidden" name="' . $name . '" value="' . $value . '" />';
        }
        $output .= '<input type="submit" value="' . $label . '" />';
        $output .= '</div></form>';
        return $output;
    }

    public function start_form($action, $method): string {
        return '<form action="' . htmlspecialchars($action) . '" method="' . $method . '"><div>';
    }

    public function submit_button($name, $label): string {
        return '<input type="submit" name="' . $name . '" value="' . $label . '">';
    }

    public function end_form(): string {
        return '</div></form>';
    }

    public function form_field($label, $field, $postfix = ''): string {
        return '<p><span class="label">' . $label . ' </span><span class="field">' .
                $field . '</span><span class="postfix"> ' . $postfix . "</span></p>\n";
    }

    public function sesskey_input(): string {
        return $this->hidden_field('sesskey', $this->or->get_sesskey());
    }

    public function hidden_field($name, $value): string {
        return '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
    }

    public function text_field($label, $name, $default, $postfix = ''): string {
        return $this->form_field($label, '<input type="text" name="' . $name .
                '" value="' . htmlspecialchars($default) . '" />', $postfix);
    }

    public function password_field($label, $name, $default, $postfix = ''): string {
        return $this->form_field($label, '<input type="password" name="' . $name .
                '" value="' . htmlspecialchars($default) . '" /> ', $postfix);
    }

    public function select($name, $choices, $default = null): string {
        $output = '<select id="' . $name . '" name="' . $name . '">';
        $output .= $this->options($choices, $default);
        $output .= '</select>';
        return $output;
    }

    public function group_select($name, $choices, $default = null): string {
        $output = '<select id="' . $name . '" name="' . $name . '">';
        foreach ($choices as $group => $groupchoices) {
            $output .= '<optgroup label="' . $group . '">';
            $output .= $this->options($groupchoices, $default);
            $output .= '</optgroup>';
        }
        $output .= '</select>';
        return $output;
    }

    public function multi_select($name, $choices, $default = null, $size = 10): string {
        $output = '<select id="' . $name . '" name="' . $name .
                '[]" multiple="multiple" size="' . $size . '">';
        $output .= $this->options($choices, $default);
        $output .= '</select>';
        return $output;
    }

    public function group_multi_select($name, $choices, $default = null, $size = 10): string {
        $output = '<select id="' . $name . '" name="' . $name .
                '[]" multiple="multiple" size="' . $size . '">';
        foreach ($choices as $group => $groupchoices) {
            $output .= '<optgroup label="' . $group . '">';
            $output .= $this->options($groupchoices, $default);
            $output .= '</optgroup>';
        }
        $output .= '</select>';
        return $output;
    }

    protected function options($choices, $default): string {
        $output = '';
        foreach ($choices as $value => $label) {
            if ($this->option_is_selected($value, $default)) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            $output .= '<option value="' . $value . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        return $output;
    }

    protected function option_is_selected($value, $default): string {
        if (!is_array($default)) {
            return ((string)$value) === ((string)$default);
        } else {
            return in_array((string)$value, $default);
        }
    }

    public function action_menu($actions): string {
        if (empty($actions)) {
            return '';
        }
        $output = "<ul>\n";
        foreach ($actions as $url => $linktext) {
            $output .= '<li><a href="' . $url . '">' . $linktext . "</a></li>\n";
        }
        $output .= "</ul>\n";
        return $output;
    }

    public function links_to_other_series($series, $relativeurl = '', $withtoken = true,
            $linkall = false, $intro = 'Other series of rehearsals'): string {
        if (!$linkall && count($series) <= 1) {
            return '';
        }

        $links = [];
        foreach ($series as $s) {
            if (!$linkall && $s->id == $this->or->get_current_seriesid()) {
                $links[] = '<b>' . htmlspecialchars($s->name) . '</b>';
            } else {
                $links[] = '<a href="' . $this->or->url($relativeurl, $withtoken, true, $s->id) . '">' .
                htmlspecialchars($s->name) . '</a>';
            }
        }

        return '<p>' . $intro . ': ' . implode(' - ', $links) . '</p>';
    }

    public function back_link($text = 'Back to the register', event $event = null): string {
        if (($event && $event->timestart < time()) || $this->or->get_param('past', request::TYPE_BOOL, false, false)) {
            $url = '?past=1';
        } else {
            $url = '';
        }
        return '<p><a href="' . $this->or->url($url) . '">' . $text . '</a></p>';
    }

    public function event_link($event, $fragment = ''): string {
        if ($fragment) {
            $fragment = '#' . $fragment;
        }
        return '<a class="eventlink" href="' . $this->or->url('event.php?id=' . $event->id) . $fragment . '">' .
                htmlspecialchars($event->name) . '</a>';
    }

    public function player_link(player $player, event $event = null, $fullname = false): string {
        $params = [];
        if ($player->id != $this->or->get_current_user()->id) {
            $params[] = 'id=' . $player->id;
        }
        if (($event && $event->timestart < time()) || $this->or->get_param('past', request::TYPE_BOOL, false, false)) {
            $params[] = 'past=1';
        }
        if ($params) {
            $params = '?' . implode('&', $params);
        } else {
            $params = '';
        }

        $fragment = '';
        if ($event) {
            $fragment = '#event-' . $event->id;
        }

        if ($fullname) {
            $name = $player->get_name();
        } else {
            $name = $player->get_public_name();
        }
        return '<a class="playerlink" href="' . $this->or->url('player.php' . $params) . $fragment . '">' .
                htmlspecialchars($name) . '</a>';
    }

    public function player_attendance(player $player, event $event): string {
        $attendance = $player->get_attendance($event);

        $content = $this->player_link($player, $event);
        if (trim($attendance->get_symbol())) {
            $content .= ': ' . htmlspecialchars($attendance->get_symbol());
        }

        return '<li class="' . $attendance->status . '">' . $content . '</li> ';
    }

    public function subtotal($attending, $outof): string {
        if ($outof) {
            return '<span class="total">' . $attending . '</span><span class="outof">/' . $outof . '</span>';
        } else {
            return '-';
        }
    }

    /**
     * Output the links to the previous or next events, if any.
     *
     * @param event|null $previousevent
     * @param event|null $nextevent
     * @return string HTML to outpute.
     */
    public function previous_next_links(?event $previousevent, ?event $nextevent): string {
        $output = '';
        if ($previousevent) {
            $output .= '<p><a href="' . $this->or->url('event.php?id=' . $previousevent->id) .
                    '">Previous: ' . htmlspecialchars($previousevent->name) .
                    ' (' . $previousevent->get_nice_datetime() . ')</a></p>';
        }
        if ($nextevent) {
            $output .= '<p><a href="' . $this->or->url('event.php?id=' . $nextevent->id) . '">Next: ' . htmlspecialchars($nextevent->name) .
                    ' (' . $nextevent->get_nice_datetime() . ')</a></p>';
        }
        return $output;
    }

    public function make_id($string): string {
        return trim(preg_replace('~[^a-z0-9]+~', '-', strtolower($string)), '-');
    }

    public function call_to_js(string $function, array $arguments = []): void {
        $quotedargs = [];
        foreach ($arguments as $arg) {
            $quotedargs[] = json_encode($arg);
        }
        $this->javascriptcode[] = $function . '(' . implode(', ', $quotedargs) . ');';
    }

    public function markdown($content): string {
        if (is_null($this->markdownparser)) {
            require_once(__DIR__ . '/../thirdparty/parsedown/Parsedown.php');
            $this->markdownparser = new Parsedown();
            $this->markdownparser->setSafeMode(true);
        }
        return $this->markdownparser->text($content);
    }
}

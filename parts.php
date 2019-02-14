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
 * Script for editing the the list of parts and sections.
 *
 * @package orchestraregister
 * @copyright 2009 onwards Tim Hunt. T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/setup.php');

$or = new orchestra_register();

$user = $or->get_current_user();
if (!$user->can_edit_parts()) {
    throw new permission_exception('You don\'t have permission to edit the list of parts.');
}

$sections = $or->get_sections_and_parts();

if ($section = $or->get_param('sectionup', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    if (!array_key_exists($section, $sections)) {
        throw new not_found_exception('Unknown part');
    }

    $prevsection = null;
    foreach ($sections as $sect => $notused) {
        if ($sect == $section) {
            break;
        }
        $prevsection = $sect;
    }

    if (!$prevsection) {
        throw new not_found_exception('Cannot move up');
    }

    $or->swap_section_order($prevsection, $section);

    $or->redirect('parts.php');
}

if ($section = $or->get_param('sectiondown', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    if (!array_key_exists($section, $sections)) {
        throw new not_found_exception('Unknown part');
    }

    $prevsection = null;
    $found = false;
    foreach ($sections as $sect => $notused) {
        if ($prevsection == $section) {
            $found = true;
            break;
        }
        $prevsection = $sect;
    }

    if (!$found) {
        throw new not_found_exception('Cannot move down');
    }

    $or->swap_section_order($sect, $section);

    $or->redirect('parts.php');
}

if ($section = $or->get_param('sectiondelete', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    if (!array_key_exists($section, $sections)) {
        throw new not_found_exception('Unknown part');
    }

    if (!empty($sections[$section]->parts)) {
        throw new forbidden_operation_exception('Cannot delete a section that contains parts.');
    }

    $or->delete_section($section);

    $or->redirect('parts.php');
}

if ($part = $or->get_param('partup', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    $partdata = $or->get_part_data($part);
    if (!$partdata) {
        throw new not_found_exception('Unknown part');
    }

    $prevpart = null;
    foreach ($sections[$partdata->section]->parts as $pt => $notused) {
        if ($pt == $part) {
            break;
        }
        $prevpart = $pt;
    }

    if (!$prevpart) {
        throw new not_found_exception('Cannot move up');
    }

    $or->swap_part_order($prevpart, $part);

    $or->redirect('parts.php');
}

if ($part = $or->get_param('partdown', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    $partdata = $or->get_part_data($part);
    if (!$partdata) {
        throw new not_found_exception('Unknown part');
    }

    $prevpart = null;
    $found = false;
    foreach ($sections[$partdata->section]->parts as $pt => $notused) {
        if ($prevpart == $part) {
            $found = true;
            break;
        }
        $prevpart = $pt;
    }

    if (!$found) {
        throw new not_found_exception('Cannot move down');
    }

    $or->swap_part_order($pt, $part);

    $or->redirect('parts.php');
}

if ($part = $or->get_param('partdelete', request::TYPE_RAW, false)) {
    $or->require_sesskey();
    $partdata = $or->get_part_data($part);

    if (!$partdata) {
        throw new not_found_exception('Unknown part');
    }

    if ($partdata->inuse) {
        throw new forbidden_operation_exception('Cannot delete a part that is used.');
    }

    $or->delete_part($part);

    $or->redirect('parts.php');
}

$output = $or->get_output();
$output->header('Edit sections and parts');

echo $output->back_link();
?>
<ul class="sections">
<?php
$rowparity = 1;
end($sections);
$lastsection = key($sections);
$isfirstsection = true;
foreach ($sections as $section => $sectiondata) {
    echo '<li>', $section;
    if (!$isfirstsection) {
        echo $output->action_button($or->url('parts.php', false), array('sectionup' => $section), '↑');
    } else {
        $isfirstsection = false;
    }
    if ($section != $lastsection) {
        echo $output->action_button($or->url('parts.php', false), array('sectiondown' => $section), '↓');
    }
    echo $output->action_button($or->url('editsection.php', false), array('section' => $section), 'Rename', 'get');
    if (empty($sectiondata->parts)) {
        if (count($sections) > 1) {
            echo $output->action_button($or->url('parts.php', false), array('sectiondelete' => $section), 'Delete');
        }
    } else {
        echo '<ul class="parts">';
        end($sectiondata->parts);
        $lastpart = key($sectiondata->parts);
        $isfirstpart = true;
        foreach ($sectiondata->parts as $part => $partdata) {
            echo '<li>', $part;
            if (!$isfirstpart) {
                echo $output->action_button($or->url('parts.php', false), array('partup' => $part), '↑');
            } else {
                $isfirstpart = false;
            }
            if ($part != $lastpart) {
                echo $output->action_button($or->url('parts.php', false), array('partdown' => $part), '↓');
            }
            echo $output->action_button($or->url('editpart.php', false), array('part' => $part), 'Rename', 'get');
            if (!$partdata->inuse) {
                echo $output->action_button($or->url('parts.php', false), array('partdelete' => $part), 'Delete');
            }
        }
        echo '<li>', $output->action_button($or->url('editpart.php', false), array('section' => $section), 'Add a part', 'get'), '</li>';
        echo '</ul>';
    }
    echo '</li>';
}
echo '<li>', $output->action_button($or->url('editsection.php', false), array(), 'Add a section', 'get'), '</li>';
?>
</ul>

<?php
echo $output->back_link();
$output->call_to_js('init_edit_parts_page', array($sections));

$output->footer();

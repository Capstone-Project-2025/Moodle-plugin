<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of log events for the progcontest module.
 *
 * @package    mod_progcontest
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'progcontest', 'action'=>'add', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'update', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'view', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'report', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'attempt', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'submit', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'review', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'editquestions', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'preview', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'start attempt', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'close attempt', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'continue attempt', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'edit override', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'delete override', 'mtable'=>'progcontest', 'field'=>'name'),
    array('module'=>'progcontest', 'action'=>'view summary', 'mtable'=>'progcontest', 'field'=>'name'),
);
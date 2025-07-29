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
 * Library of functions used by the progcontest module.
 *
 * This contains functions that are called from within the progcontest module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package    mod_progcontest
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/progcontest/lib.php');
require_once($CFG->dirroot . '/mod/progcontest/accessmanager.php');
require_once($CFG->dirroot . '/mod/progcontest/accessmanager_form.php');
require_once($CFG->dirroot . '/mod/progcontest/renderer.php');
require_once($CFG->dirroot . '/mod/progcontest/attemptlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');


/**
 * @var int We show the countdown timer if there is less than this amount of time left before the
 * the progcontest close date. (1 hour)
 */
define('progcontest_SHOW_TIME_BEFORE_DEADLINE', '3600');

/**
 * @var int If there are fewer than this many seconds left when the student submits
 * a page of the progcontest, then do not take them to the next page of the progcontest. Instead
 * close the progcontest immediately.
 */
define('progcontest_MIN_TIME_TO_CONTINUE', '2');

/**
 * @var int We show no image when user selects No image from dropdown menu in progcontest settings.
 */
define('progcontest_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in progcontest settings.
 */
define('progcontest_SHOWIMAGE_SMALL', 1);

/**
 * @var int We show Large image when user selects Large image from dropdown menu in progcontest settings.
 */
define('progcontest_SHOWIMAGE_LARGE', 2);


// Functions related to attempts ///////////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a progcontest
 *
 * Creates an attempt object to represent an attempt at the progcontest by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param object $progcontestobj the progcontest object to create an attempt for.
 * @param int $attemptnumber the sequence number for the attempt.
 * @param object $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $progcontest->attemptonlast is true.
 * @param int $timenow the time the attempt was started at.
 * @param bool $ispreview whether this new attempt is a preview.
 * @param int $userid  the id of the user attempting this progcontest.
 *
 * @return object the newly created attempt object.
 */
function progcontest_create_attempt(progcontest $progcontestobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $progcontest = $progcontestobj->get_progcontest();
    if ($progcontest->sumgrades < 0.000005 && $progcontest->grade > 0.000005) {
        throw new moodle_exception('cannotstartgradesmismatch', 'progcontest',
                new moodle_url('/mod/progcontest/view.php', array('q' => $progcontest->id)),
                    array('grade' => progcontest_format_grade($progcontest, $progcontest->grade)));
    }

    if ($attemptnumber == 1 || !$progcontest->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->progcontest = $progcontest->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            print_error('cannotfindprevattempt', 'progcontest');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->timemodifiedoffline = 0;
    $attempt->state = progcontest_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $progcontestobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}
/**
 * Start a normal, new, progcontest attempt.
 *
 * @param progcontest      $progcontestobj            the progcontest object to start an attempt for.
 * @param question_usage_by_activity $quba
 * @param object    $attempt
 * @param integer   $attemptnumber      starting from 1
 * @param integer   $timenow            the attempt start time
 * @param array     $questionids        slot number => question id. Used for random questions, to force the choice
 *                                        of a particular actual question. Intended for testing purposes only.
 * @param array     $forcedvariantsbyslot slot number => variant. Used for questions with variants,
 *                                          to force the choice of a particular variant. Intended for testing
 *                                          purposes only.
 * @throws moodle_exception
 * @return object   modified attempt object
 */
function progcontest_start_new_attempt($progcontestobj, $quba, $attempt, $attemptnumber, $timenow,
                                $questionids = array(), $forcedvariantsbyslot = array()) {

    // Usages for this user's previous progcontest attempts.
    $qubaids = new \mod_progcontest\question\qubaids_for_users_attempts(
            $progcontestobj->get_progcontestid(), $attempt->userid);

    // Fully load all the questions in this progcontest.
    $progcontestobj->preload_questions();
    $progcontestobj->load_questions();

    // First load all the non-random questions.
    $randomfound = false;
    $slot = 0;
    $questions = array();
    $maxmark = array();
    $page = array();
    foreach ($progcontestobj->get_questions() as $questiondata) {
        $slot += 1;
        $maxmark[$slot] = $questiondata->maxmark;
        $page[$slot] = $questiondata->page;
        if ($questiondata->qtype == 'random') {
            $randomfound = true;
            continue;
        }
        if (!$progcontestobj->get_progcontest()->shuffleanswers) {
            $questiondata->options->shuffleanswers = false;
        }
        $questions[$slot] = question_bank::make_question($questiondata);
    }

    // Then find a question to go in place of each random question.
    if ($randomfound) {
        $slot = 0;
        $usedquestionids = array();
        foreach ($questions as $question) {
            if (isset($usedquestions[$question->id])) {
                $usedquestionids[$question->id] += 1;
            } else {
                $usedquestionids[$question->id] = 1;
            }
        }
        $randomloader = new \core_question\bank\random_question_loader($qubaids, $usedquestionids);

        foreach ($progcontestobj->get_questions() as $questiondata) {
            $slot += 1;
            if ($questiondata->qtype != 'random') {
                continue;
            }

            $tagids = progcontest_retrieve_slot_tag_ids($questiondata->slotid);

            // Deal with fixed random choices for testing.
            if (isset($questionids[$quba->next_slot_number()])) {
                if ($randomloader->is_question_available($questiondata->category,
                        (bool) $questiondata->questiontext, $questionids[$quba->next_slot_number()], $tagids)) {
                    $questions[$slot] = question_bank::load_question(
                            $questionids[$quba->next_slot_number()], $progcontestobj->get_progcontest()->shuffleanswers);
                    continue;
                } else {
                    throw new coding_exception('Forced question id not available.');
                }
            }

            // Normal case, pick one at random.
            $questionid = $randomloader->get_next_question_id($questiondata->randomfromcategory,
                    $questiondata->randomincludingsubcategories, $tagids);
            if ($questionid === null) {
                throw new moodle_exception('notenoughrandomquestions', 'progcontest',
                                           $progcontestobj->view_url(), $questiondata);
            }

            $questions[$slot] = question_bank::load_question($questionid,
                    $progcontestobj->get_progcontest()->shuffleanswers);
        }
    }

    // Finally add them all to the usage.
    ksort($questions);
    foreach ($questions as $slot => $question) {
        $newslot = $quba->add_question($question, $maxmark[$slot]);
        if ($newslot != $slot) {
            throw new coding_exception('Slot numbers have got confused.');
        }
    }

    // Start all the questions.
    $variantstrategy = new core_question\engine\variants\least_used_strategy($quba, $qubaids);

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
            $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
            $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow, $attempt->userid);

    // Work out the attempt layout.
    $sections = $progcontestobj->get_sections();
    foreach ($sections as $i => $section) {
        if (isset($sections[$i + 1])) {
            $sections[$i]->lastslot = $sections[$i + 1]->firstslot - 1;
        } else {
            $sections[$i]->lastslot = count($questions);
        }
    }

    $layout = array();
    foreach ($sections as $section) {
        if ($section->shufflequestions) {
            $questionsinthissection = array();
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $questionsinthissection[] = $slot;
            }
            shuffle($questionsinthissection);
            $questionsonthispage = 0;
            foreach ($questionsinthissection as $slot) {
                if ($questionsonthispage && $questionsonthispage == $progcontestobj->get_progcontest()->questionsperpage) {
                    $layout[] = 0;
                    $questionsonthispage = 0;
                }
                $layout[] = $slot;
                $questionsonthispage += 1;
            }

        } else {
            $currentpage = $page[$section->firstslot];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                if ($currentpage !== null && $page[$slot] != $currentpage) {
                    $layout[] = 0;
                }
                $layout[] = $slot;
                $currentpage = $page[$slot];
            }
        }

        // Each section ends with a page break.
        $layout[] = 0;
    }
    $attempt->layout = implode(',', $layout);

    return $attempt;
}

/**
 * Start a subsequent new attempt, in each attempt builds on last mode.
 *
 * @param question_usage_by_activity    $quba         this question usage
 * @param object                        $attempt      this attempt
 * @param object                        $lastattempt  last attempt
 * @return object                       modified attempt object
 *
 */
function progcontest_start_attempt_built_on_last($quba, $attempt, $lastattempt) {
    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = array();
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $newslot = $quba->add_question($oldqa->get_question(false), $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
    return $attempt;
}

/**
 * The save started question usage and progcontest attempt in db and log the started attempt.
 *
 * @param progcontest                       $progcontestobj
 * @param question_usage_by_activity $quba
 * @param object                     $attempt
 * @return object                    attempt object with uniqueid and id set.
 */
function progcontest_attempt_save_started($progcontestobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('progcontest_attempts', $attempt);

    // Params used by the events below.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $progcontestobj->get_courseid(),
        'context' => $progcontestobj->get_context()
    );
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = array(
            'progcontestid' => $progcontestobj->get_progcontestid()
        );
        $event = \mod_progcontest\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_progcontest\event\attempt_started::create($params);

    }

    // Trigger the event.
    $event->add_record_snapshot('progcontest', $progcontestobj->get_progcontest());
    $event->add_record_snapshot('progcontest_attempts', $attempt);
    $event->trigger();

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given progcontest. This function does not return preview attempts.
 *
 * @param int $progcontestid the id of the progcontest.
 * @param int $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function progcontest_get_user_attempt_unfinished($progcontestid, $userid) {
    $attempts = progcontest_get_user_attempts($progcontestid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a progcontest attempt.
 * @param mixed $attempt an integer attempt id or an attempt object
 *      (row of the progcontest_attempts table).
 * @param object $progcontest the progcontest object.
 */
function progcontest_delete_attempt($attempt, $progcontest) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('progcontest_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->progcontest != $progcontest->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to progcontest $attempt->progcontest " .
                "but was passed progcontest $progcontest->id.");
        return;
    }

    if (!isset($progcontest->cmid)) {
        $cm = get_coursemodule_from_instance('progcontest', $progcontest->id, $progcontest->course);
        $progcontest->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('progcontest_attempts', array('id' => $attempt->id));

    // Log the deletion of the attempt if not a preview.
    if (!$attempt->preview) {
        $params = array(
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => context_module::instance($progcontest->cmid),
            'other' => array(
                'progcontestid' => $progcontest->id
            )
        );
        $event = \mod_progcontest\event\attempt_deleted::create($params);
        $event->add_record_snapshot('progcontest_attempts', $attempt);
        $event->trigger();
    }

    // Search progcontest_attempts for other instances by this user.
    // If none, then delete record for this progcontest, this user from progcontest_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    if (!$DB->record_exists('progcontest_attempts', array('userid' => $userid, 'progcontest' => $progcontest->id))) {
        $DB->delete_records('progcontest_grades', array('userid' => $userid, 'progcontest' => $progcontest->id));
    } else {
        progcontest_save_best_grade($progcontest, $userid);
    }

    progcontest_update_grades($progcontest, $userid);
}

/**
 * Delete all the preview attempts at a progcontest, or possibly all the attempts belonging
 * to one user.
 * @param object $progcontest the progcontest object.
 * @param int $userid (optional) if given, only delete the previews belonging to this user.
 */
function progcontest_delete_previews($progcontest, $userid = null) {
    global $DB;
    $conditions = array('progcontest' => $progcontest->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('progcontest_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        progcontest_delete_attempt($attempt, $progcontest);
    }
}

/**
 * @param int $progcontestid The progcontest id.
 * @return bool whether this progcontest has any (non-preview) attempts.
 */
function progcontest_has_attempts($progcontestid) {
    global $DB;
    return $DB->record_exists('progcontest_attempts', array('progcontest' => $progcontestid, 'preview' => 0));
}

// Functions to do with progcontest layout and pages //////////////////////////////////

/**
 * Repaginate the questions in a progcontest
 * @param int $progcontestid the id of the progcontest to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function progcontest_repaginate_questions($progcontestid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $sections = $DB->get_records('progcontest_sections', array('progcontestid' => $progcontestid), 'firstslot ASC');
    $firstslots = array();
    foreach ($sections as $section) {
        if ((int)$section->firstslot === 1) {
            continue;
        }
        $firstslots[] = $section->firstslot;
    }

    $slots = $DB->get_records('progcontest_slots', array('progcontestid' => $progcontestid),
            'slot');
    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if (($firstslots && in_array($slot->slot, $firstslots)) ||
            ($slotsonthispage && $slotsonthispage == $slotsperpage)) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('progcontest_slots', 'page', $currentpage, array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();
}

// Functions to do with progcontest grades ////////////////////////////////////////////

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this progcontest.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $progcontest the progcontest object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function progcontest_rescale_grade($rawgrade, $progcontest, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($progcontest->sumgrades >= 0.000005) {
        $grade = $rawgrade * $progcontest->grade / $progcontest->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = progcontest_format_question_grade($progcontest, $grade);
    } else if ($format) {
        $grade = progcontest_format_grade($progcontest, $grade);
    }
    return $grade;
}

/**
 * Get the feedback object for this grade on this progcontest.
 *
 * @param float $grade a grade on this progcontest.
 * @param object $progcontest the progcontest settings.
 * @return false|stdClass the record object or false if there is not feedback for the given grade
 * @since  Moodle 3.1
 */
function progcontest_feedback_record_for_grade($grade, $progcontest) {
    global $DB;

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedback = $DB->get_record_select('progcontest_feedback',
            'progcontestid = ? AND mingrade <= ? AND ? < maxgrade', array($progcontest->id, $grade, $grade));

    return $feedback;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this progcontest. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this progcontest.
 * @param object $progcontest the progcontest settings.
 * @param object $context the progcontest context.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function progcontest_feedback_for_grade($grade, $progcontest, $context) {

    if (is_null($grade)) {
        return '';
    }

    $feedback = progcontest_feedback_record_for_grade($grade, $progcontest);

    if (empty($feedback->feedbacktext)) {
        return '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
            $context->id, 'mod_progcontest', 'feedback', $feedback->id);
    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * @param object $progcontest the progcontest database row.
 * @return bool Whether this progcontest has any non-blank feedback text.
 */
function progcontest_has_feedback($progcontest) {
    global $DB;
    static $cache = array();
    if (!array_key_exists($progcontest->id, $cache)) {
        $cache[$progcontest->id] = progcontest_has_grades($progcontest) &&
                $DB->record_exists_select('progcontest_feedback', "progcontestid = ? AND " .
                    $DB->sql_isnotempty('progcontest_feedback', 'feedbacktext', false, true),
                array($progcontest->id));
    }
    return $cache[$progcontest->id];
}

/**
 * Update the sumgrades field of the progcontest. This needs to be called whenever
 * the grading structure of the progcontest is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * You should call {@link progcontest_delete_previews()} before you call this function.
 *
 * @param object $progcontest a progcontest.
 */
function progcontest_update_sumgrades($progcontest) {
    global $DB;

    $sql = 'UPDATE {progcontest}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {progcontest_slots}
                WHERE progcontestid = {progcontest}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($progcontest->id));
    $progcontest->sumgrades = $DB->get_field('progcontest', 'sumgrades', array('id' => $progcontest->id));

    if ($progcontest->sumgrades < 0.000005 && progcontest_has_attempts($progcontest->id)) {
        // If the progcontest has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        progcontest_set_grade(0, $progcontest);
    }
}

/**
 * Update the sumgrades field of the attempts at a progcontest.
 *
 * @param object $progcontest a progcontest.
 */
function progcontest_update_all_attempt_sumgrades($progcontest) {
    global $DB;
    $dm = new question_engine_data_mapper();
    $timenow = time();

    $sql = "UPDATE {progcontest_attempts}
            SET
                timemodified = :timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE progcontest = :progcontestid AND state = :finishedstate";
    $DB->execute($sql, array('timenow' => $timenow, 'progcontestid' => $progcontest->id,
            'finishedstate' => progcontest_attempt::FINISHED));
}

/**
 * The progcontest grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in progcontest_grades and progcontest_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * progcontest_update_all_attempt_sumgrades, progcontest_update_all_final_grades and
 * progcontest_update_grades.
 *
 * @param float $newgrade the new maximum grade for the progcontest.
 * @param object $progcontest the progcontest we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function progcontest_set_grade($newgrade, $progcontest) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($progcontest->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $progcontest->grade;
    $progcontest->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the progcontest table.
    $DB->set_field('progcontest', 'grade', $newgrade, array('id' => $progcontest->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        progcontest_update_all_final_grades($progcontest);

    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {progcontest_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE progcontest = ?
        ", array($newgrade/$oldgrade, $timemodified, $progcontest->id));
    }

    if ($oldgrade > 1e-7) {
        // Update the progcontest_feedback table.
        $factor = $newgrade/$oldgrade;
        $DB->execute("
                UPDATE {progcontest_feedback}
                SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                WHERE progcontestid = ?
        ", array($factor, $factor, $progcontest->id));
    }

    // Update grade item and send all grades to gradebook.
    progcontest_grade_item_update($progcontest);
    progcontest_update_grades($progcontest);

    $transaction->allow_commit();
    return true;
}

/**
 * Save the overall grade for a user at a progcontest in the progcontest_grades table
 *
 * @param object $progcontest The progcontest for which the best grade is to be calculated and then saved.
 * @param int $userid The userid to calculate the grade for. Defaults to the current user.
 * @param array $attempts The attempts of this user. Useful if you are
 * looping through many users. Attempts can be fetched in one master query to
 * avoid repeated querying.
 * @return bool Indicates success or failure.
 */
function progcontest_save_best_grade($progcontest, $userid = null, $attempts = array()) {
    global $DB, $OUTPUT, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (!$attempts) {
        // Get all the attempts made by the user.
        $attempts = progcontest_get_user_attempts($progcontest->id, $userid);
    }

    // Calculate the best grade.
    $bestgrade = progcontest_calculate_best_grade($progcontest, $attempts);
    $bestgrade = progcontest_rescale_grade($bestgrade, $progcontest, false);

    // Save the best grade in the database.
    if (is_null($bestgrade)) {
        $DB->delete_records('progcontest_grades', array('progcontest' => $progcontest->id, 'userid' => $userid));

    } else if ($grade = $DB->get_record('progcontest_grades',
            array('progcontest' => $progcontest->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->update_record('progcontest_grades', $grade);

    } else {
        $grade = new stdClass();
        $grade->progcontest = $progcontest->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->insert_record('progcontest_grades', $grade);
    }

    progcontest_update_grades($progcontest, $userid);
}

/**
 * Calculate the overall grade for a progcontest given a number of attempts by a particular user.
 *
 * @param object $progcontest    the progcontest settings object.
 * @param array $attempts an array of all the user's attempts at this progcontest in order.
 * @return float          the overall grade
 */
function progcontest_calculate_best_grade($progcontest, $attempts) {

    switch ($progcontest->grademethod) {

        case progcontest_ATTEMPTFIRST:
            $firstattempt = reset($attempts);
            return $firstattempt->sumgrades;

        case progcontest_ATTEMPTLAST:
            $lastattempt = end($attempts);
            return $lastattempt->sumgrades;

        case progcontest_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        case progcontest_GRADEHIGHEST:
        default:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Update the final grade at this progcontest for all students.
 *
 * This function is equivalent to calling progcontest_save_best_grade for all
 * users, but much more efficient.
 *
 * @param object $progcontest the progcontest settings.
 */
function progcontest_update_all_final_grades($progcontest) {
    global $DB;

    if (!$progcontest->sumgrades) {
        return;
    }

    $param = array('iprogcontestid' => $progcontest->id, 'istatefinished' => progcontest_attempt::FINISHED);
    $firstlastattemptjoin = "JOIN (
            SELECT
                iprogcontesta.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {progcontest_attempts} iprogcontesta

            WHERE
                iprogcontesta.state = :istatefinished AND
                iprogcontesta.preview = 0 AND
                iprogcontesta.progcontest = :iprogcontestid

            GROUP BY iprogcontesta.userid
        ) first_last_attempts ON first_last_attempts.userid = progcontesta.userid";

    switch ($progcontest->grademethod) {
        case progcontest_ATTEMPTFIRST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(progcontesta.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'progcontesta.attempt = first_last_attempts.firstattempt AND';
            break;

        case progcontest_ATTEMPTLAST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(progcontesta.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'progcontesta.attempt = first_last_attempts.lastattempt AND';
            break;

        case progcontest_GRADEAVERAGE:
            $select = 'AVG(progcontesta.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case progcontest_GRADEHIGHEST:
            $select = 'MAX(progcontesta.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    if ($progcontest->sumgrades >= 0.000005) {
        $finalgrade = $select . ' * ' . ($progcontest->grade / $progcontest->sumgrades);
    } else {
        $finalgrade = '0';
    }
    $param['progcontestid'] = $progcontest->id;
    $param['progcontestid2'] = $progcontest->id;
    $param['progcontestid3'] = $progcontest->id;
    $param['progcontestid4'] = $progcontest->id;
    $param['statefinished'] = progcontest_attempt::FINISHED;
    $param['statefinished2'] = progcontest_attempt::FINISHED;
    $finalgradesubquery = "
            SELECT progcontesta.userid, $finalgrade AS newgrade
            FROM {progcontest_attempts} progcontesta
            $join
            WHERE
                $where
                progcontesta.state = :statefinished AND
                progcontesta.preview = 0 AND
                progcontesta.progcontest = :progcontestid3
            GROUP BY progcontesta.userid";

    $changedgrades = $DB->get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {progcontest_grades} qg
                WHERE progcontest = :progcontestid
            UNION
                SELECT DISTINCT userid
                FROM {progcontest_attempts} progcontesta2
                WHERE
                    progcontesta2.state = :statefinished2 AND
                    progcontesta2.preview = 0 AND
                    progcontesta2.progcontest = :progcontestid2
            ) users

            LEFT JOIN {progcontest_grades} qg ON qg.userid = users.userid AND qg.progcontest = :progcontestid4

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                          (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
                // The mess on the previous line is detecting where the value is
                // NULL in one column, and NOT NULL in the other, but SQL does
                // not have an XOR operator, and MS SQL server can't cope with
                // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
            $param);

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;

        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass();
            $toinsert->progcontest = $progcontest->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            $DB->insert_record('progcontest_grades', $toinsert);

        } else {
            $toupdate = new stdClass();
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            $DB->update_record('progcontest_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        list($test, $params) = $DB->get_in_or_equal($todelete);
        $DB->delete_records_select('progcontest_grades', 'progcontest = ? AND userid ' . $test,
                array_merge(array($progcontest->id), $params));
    }
}

/**
 * Return summary of the number of settings override that exist.
 *
 * To get a nice display of this, see the progcontest_override_summary_links()
 * progcontest renderer method.
 *
 * @param stdClass $progcontest the progcontest settings. Only $progcontest->id is used at the moment.
 * @param stdClass|cm_info $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *      (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return array like 'group' => 3, 'user' => 12] where 3 is the number of group overrides,
 *      and 12 is the number of user ones.
 */
function progcontest_override_summary(stdClass $progcontest, stdClass $cm, int $currentgroup = 0): array {
    global $DB;

    if ($currentgroup) {
        // Currently only interested in one group.
        $groupcount = $DB->count_records('progcontest_overrides', ['progcontest' => $progcontest->id, 'groupid' => $currentgroup]);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {progcontest_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE o.progcontest = ?
                   AND gm.groupid = ?
                    ", [$progcontest->id, $currentgroup]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'onegroup'];
    }

    $progcontestgroupmode = groups_get_activity_groupmode($cm);
    $accessallgroups = ($progcontestgroupmode == NOGROUPS) ||
            has_capability('moodle/site:accessallgroups', context_module::instance($cm->id));

    if ($accessallgroups) {
        // User can see all groups.
        $groupcount = $DB->count_records_select('progcontest_overrides',
                'progcontest = ? AND groupid IS NOT NULL', [$progcontest->id]);
        $usercount = $DB->count_records_select('progcontest_overrides',
                'progcontest = ? AND userid IS NOT NULL', [$progcontest->id]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'allgroups'];

    } else {
        // User can only see groups they are in.
        $groups = groups_get_activity_allowed_groups($cm);
        if (!$groups) {
            return ['group' => 0, 'user' => 0, 'mode' => 'somegroups'];
        }

        list($groupidtest, $params) = $DB->get_in_or_equal(array_keys($groups));
        $params[] = $progcontest->id;

        $groupcount = $DB->count_records_select('progcontest_overrides',
                "groupid $groupidtest AND progcontest = ?", $params);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {progcontest_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE gm.groupid $groupidtest
                   AND o.progcontest = ?
               ", $params);

        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'somegroups'];
    }
}

/**
 * Efficiently update check state time on all open attempts
 *
 * @param array $conditions optional restrictions on which attempts to update
 *                    Allowed conditions:
 *                      courseid => (array|int) attempts in given course(s)
 *                      userid   => (array|int) attempts for given user(s)
 *                      progcontestid   => (array|int) attempts in given progcontest(s)
 *                      groupid  => (array|int) progcontestzes with some override for given group(s)
 *
 */
function progcontest_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = array($value);
        }
    }

    $params = array();
    $wheres = array("progcontesta.state IN ('inprogress', 'overdue')");
    $iwheres = array("iprogcontesta.state IN ('inprogress', 'overdue')");

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "progcontesta.progcontest IN (SELECT q.id FROM {progcontest} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iprogcontesta.progcontest IN (SELECT q.id FROM {progcontest} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "progcontesta.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iprogcontesta.userid $incond";
    }

    if (isset($conditions['progcontestid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['progcontestid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "progcontesta.progcontest $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['progcontestid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iprogcontesta.progcontest $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "progcontesta.progcontest IN (SELECT qo.progcontest FROM {progcontest_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iprogcontesta.progcontest IN (SELECT qo.progcontest FROM {progcontest_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $progcontestausersql = progcontest_get_attempt_usertime_sql(
            implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN progcontestauser.usertimelimit = 0 AND progcontestauser.usertimeclose = 0 THEN NULL
               WHEN progcontestauser.usertimelimit = 0 THEN progcontestauser.usertimeclose
               WHEN progcontestauser.usertimeclose = 0 THEN progcontesta.timestart + progcontestauser.usertimelimit
               WHEN progcontesta.timestart + progcontestauser.usertimelimit < progcontestauser.usertimeclose THEN progcontesta.timestart + progcontestauser.usertimelimit
               ELSE progcontestauser.usertimeclose END +
          CASE WHEN progcontesta.state = 'overdue' THEN progcontest.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

   /*
    * Each database handles updates with inner joins differently:
    *  - mysql does not allow a FROM clause
    *  - postgres and mssql allow FROM but handle table aliases differently
    *  - oracle requires a subquery
    *
    * Different code for each database.
    */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {progcontest_attempts} progcontesta
                        JOIN {progcontest} progcontest ON progcontest.id = progcontesta.progcontest
                        JOIN ( $progcontestausersql ) progcontestauser ON progcontestauser.id = progcontesta.id
                         SET progcontesta.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {progcontest_attempts} progcontesta
                         SET timecheckstate = $timecheckstatesql
                        FROM {progcontest} progcontest, ( $progcontestausersql ) progcontestauser
                       WHERE progcontest.id = progcontesta.progcontest
                         AND progcontestauser.id = progcontesta.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE progcontesta
                         SET timecheckstate = $timecheckstatesql
                        FROM {progcontest_attempts} progcontesta
                        JOIN {progcontest} progcontest ON progcontest.id = progcontesta.progcontest
                        JOIN ( $progcontestausersql ) progcontestauser ON progcontestauser.id = progcontesta.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {progcontest_attempts} progcontesta
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {progcontest} progcontest, ( $progcontestausersql ) progcontestauser
                            WHERE progcontest.id = progcontesta.progcontest
                              AND progcontestauser.id = progcontesta.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

/**
 * Returns SQL to compute timeclose and timelimit for every attempt, taking into account user and group overrides.
 * The query used herein is very similar to the one in function progcontest_get_user_timeclose, so, in case you
 * would change either one of them, make sure to apply your changes to both.
 *
 * @param string $redundantwhereclauses extra where clauses to add to the subquery
 *      for performance. These can use the table alias iprogcontesta for the progcontest attempts table.
 * @return string SQL select with columns attempt.id, usertimeclose, usertimelimit.
 */
function progcontest_get_attempt_usertime_sql($redundantwhereclauses = '') {
    if ($redundantwhereclauses) {
        $redundantwhereclauses = 'WHERE ' . $redundantwhereclauses;
    }
    // The multiple qgo JOINS are necessary because we want timeclose/timelimit = 0 (unlimited) to supercede
    // any other group override
    $progcontestausersql = "
          SELECT iprogcontesta.id,
           COALESCE(MAX(quo.timeclose), MAX(qgo1.timeclose), MAX(qgo2.timeclose), iprogcontest.timeclose) AS usertimeclose,
           COALESCE(MAX(quo.timelimit), MAX(qgo3.timelimit), MAX(qgo4.timelimit), iprogcontest.timelimit) AS usertimelimit

           FROM {progcontest_attempts} iprogcontesta
           JOIN {progcontest} iprogcontest ON iprogcontest.id = iprogcontesta.progcontest
      LEFT JOIN {progcontest_overrides} quo ON quo.progcontest = iprogcontesta.progcontest AND quo.userid = iprogcontesta.userid
      LEFT JOIN {groups_members} gm ON gm.userid = iprogcontesta.userid
      LEFT JOIN {progcontest_overrides} qgo1 ON qgo1.progcontest = iprogcontesta.progcontest AND qgo1.groupid = gm.groupid AND qgo1.timeclose = 0
      LEFT JOIN {progcontest_overrides} qgo2 ON qgo2.progcontest = iprogcontesta.progcontest AND qgo2.groupid = gm.groupid AND qgo2.timeclose > 0
      LEFT JOIN {progcontest_overrides} qgo3 ON qgo3.progcontest = iprogcontesta.progcontest AND qgo3.groupid = gm.groupid AND qgo3.timelimit = 0
      LEFT JOIN {progcontest_overrides} qgo4 ON qgo4.progcontest = iprogcontesta.progcontest AND qgo4.groupid = gm.groupid AND qgo4.timelimit > 0
          $redundantwhereclauses
       GROUP BY iprogcontesta.id, iprogcontest.id, iprogcontest.timeclose, iprogcontest.timelimit";
    return $progcontestausersql;
}

/**
 * Return the attempt with the best grade for a progcontest
 *
 * Which attempt is the best depends on $progcontest->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return object         The attempt with the best grade
 * @param object $progcontest    The progcontest for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the progcontest
 */
function progcontest_calculate_best_attempt($progcontest, $attempts) {

    switch ($progcontest->grademethod) {

        case progcontest_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case progcontest_GRADEAVERAGE: // We need to do something with it.
        case progcontest_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case progcontest_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}

/**
 * @return array int => lang string the options for calculating the progcontest grade
 *      from the individual attempt grades.
 */
function progcontest_get_grading_options() {
    return array(
        progcontest_GRADEHIGHEST => get_string('gradehighest', 'progcontest'),
        progcontest_GRADEAVERAGE => get_string('gradeaverage', 'progcontest'),
        progcontest_ATTEMPTFIRST => get_string('attemptfirst', 'progcontest'),
        progcontest_ATTEMPTLAST  => get_string('attemptlast', 'progcontest')
    );
}

/**
 * @param int $option one of the values progcontest_GRADEHIGHEST, progcontest_GRADEAVERAGE,
 *      progcontest_ATTEMPTFIRST or progcontest_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function progcontest_get_grading_option_name($option) {
    $strings = progcontest_get_grading_options();
    return $strings[$option];
}

/**
 * @return array string => lang string the options for handling overdue progcontest
 *      attempts.
 */
function progcontest_get_overdue_handling_options() {
    return array(
        'autosubmit'  => get_string('overduehandlingautosubmit', 'progcontest'),
        'graceperiod' => get_string('overduehandlinggraceperiod', 'progcontest'),
        'autoabandon' => get_string('overduehandlingautoabandon', 'progcontest'),
    );
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function progcontest_get_user_image_options() {
    return array(
        progcontest_SHOWIMAGE_NONE  => get_string('shownoimage', 'progcontest'),
        progcontest_SHOWIMAGE_SMALL => get_string('showsmallimage', 'progcontest'),
        progcontest_SHOWIMAGE_LARGE => get_string('showlargeimage', 'progcontest'),
    );
}

/**
 * Return an user's timeclose for all progcontestzes in a course, hereby taking into account group and user overrides.
 *
 * @param int $courseid the course id.
 * @return object An object with of all progcontestids and close unixdates in this course, taking into account the most lenient
 * overrides, if existing and 0 if no close date is set.
 */
function progcontest_get_user_timeclose($courseid) {
    global $DB, $USER;

    // For teacher and manager/admins return timeclose.
    if (has_capability('moodle/course:update', context_course::instance($courseid))) {
        $sql = "SELECT progcontest.id, progcontest.timeclose AS usertimeclose
                  FROM {progcontest} progcontest
                 WHERE progcontest.course = :courseid";

        $results = $DB->get_records_sql($sql, array('courseid' => $courseid));
        return $results;
    }

    $sql = "SELECT q.id,
  COALESCE(v.userclose, v.groupclose, q.timeclose, 0) AS usertimeclose
  FROM (
      SELECT progcontest.id as progcontestid,
             MAX(quo.timeclose) AS userclose, MAX(qgo.timeclose) AS groupclose
       FROM {progcontest} progcontest
  LEFT JOIN {progcontest_overrides} quo on progcontest.id = quo.progcontest AND quo.userid = :userid
  LEFT JOIN {groups_members} gm ON gm.userid = :useringroupid
  LEFT JOIN {progcontest_overrides} qgo on progcontest.id = qgo.progcontest AND qgo.groupid = gm.groupid
      WHERE progcontest.course = :courseid
   GROUP BY progcontest.id) v
       JOIN {progcontest} q ON q.id = v.progcontestid";

    $results = $DB->get_records_sql($sql, array('userid' => $USER->id, 'useringroupid' => $USER->id, 'courseid' => $courseid));
    return $results;

}

/**
 * Get the choices to offer for the 'Questions per page' option.
 * @return array int => string.
 */
function progcontest_questions_per_page_options() {
    $pageoptions = array();
    $pageoptions[0] = get_string('neverallononepage', 'progcontest');
    $pageoptions[1] = get_string('everyquestion', 'progcontest');
    for ($i = 2; $i <= progcontest_MAX_QPP_OPTION; ++$i) {
        $pageoptions[$i] = get_string('everynquestions', 'progcontest', $i);
    }
    return $pageoptions;
}

/**
 * Get the human-readable name for a progcontest attempt state.
 * @param string $state one of the state constants like {@link progcontest_attempt::IN_PROGRESS}.
 * @return string The lang string to describe that state.
 */
function progcontest_attempt_state_name($state) {
    switch ($state) {
        case progcontest_attempt::IN_PROGRESS:
            return get_string('stateinprogress', 'progcontest');
        case progcontest_attempt::OVERDUE:
            return get_string('stateoverdue', 'progcontest');
        case progcontest_attempt::FINISHED:
            return get_string('statefinished', 'progcontest');
        case progcontest_attempt::ABANDONED:
            return get_string('stateabandoned', 'progcontest');
        default:
            throw new coding_exception('Unknown progcontest attempt state.');
    }
}

// Other progcontest functions ////////////////////////////////////////////////////////

/**
 * @param object $progcontest the progcontest.
 * @param int $cmid the course_module object for this progcontest.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param int $variant which question variant to preview (optional).
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function progcontest_question_action_icons($progcontest, $cmid, $question, $returnurl, $variant = null) {
    $html = progcontest_question_preview_button($progcontest, $question, false, $variant) . ' ' .
            progcontest_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param int $cmid the course_module.id for this progcontest.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function progcontest_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit') ||
                    question_has_capability_on($question, 'move'))) {
        $action = $stredit;
        $icon = 't/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view')) {
        $action = $strview;
        $icon = 'i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = $returnurl->out_as_local_url(false);
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '" class="questioneditbutton">' .
                $OUTPUT->pix_icon($icon, $action) . $contentaftericon .
                '</a>';
    } else if ($contentaftericon) {
        return '<span class="questioneditbutton">' . $contentaftericon . '</span>';
    } else {
        return '';
    }
}

/**
 * @param object $progcontest the progcontest settings
 * @param object $question the question
 * @param int $variant which question variant to preview (optional).
 * @return moodle_url to preview this question with the options from this progcontest.
 */
function progcontest_question_preview_url($progcontest, $question, $variant = null) {
    // Get the appropriate display options.
    $displayoptions = mod_progcontest_display_options::make_from_progcontest($progcontest,
            mod_progcontest_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return question_preview_url($question->id, $progcontest->preferredbehaviour,
            $maxmark, $displayoptions, $variant);
}

/**
 * @param object $progcontest the progcontest settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @param int $variant which question variant to preview (optional).
 * @return the HTML for a preview question icon.
 */
function progcontest_question_preview_button($progcontest, $question, $label = false, $variant = null) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use')) {
        return '';
    }

    return $PAGE->get_renderer('mod_progcontest', 'edit')->question_preview_icon($progcontest, $question, $label, $variant);
}

/**
 * @param object $attempt the attempt.
 * @param object $context the progcontest context.
 * @return int whether flags should be shown/editable to the current user for this attempt.
 */
function progcontest_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this progcontest attempt is in - in the sense used by
 * progcontest_get_review_options, not in the sense of $attempt->state.
 * @param object $progcontest the progcontest settings
 * @param object $attempt the progcontest_attempt database row.
 * @return int one of the mod_progcontest_display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function progcontest_attempt_state($progcontest, $attempt) {
    if ($attempt->state == progcontest_attempt::IN_PROGRESS) {
        return mod_progcontest_display_options::DURING;
    } else if ($progcontest->timeclose && time() >= $progcontest->timeclose) {
        return mod_progcontest_display_options::AFTER_CLOSE;
    } else if (time() < $attempt->timefinish + 120) {
        return mod_progcontest_display_options::IMMEDIATELY_AFTER;
    } else {
        return mod_progcontest_display_options::LATER_WHILE_OPEN;
    }
}

/**
 * The the appropraite mod_progcontest_display_options object for this attempt at this
 * progcontest right now.
 *
 * @param stdClass $progcontest the progcontest instance.
 * @param stdClass $attempt the attempt in question.
 * @param context $context the progcontest context.
 *
 * @return mod_progcontest_display_options
 */
function progcontest_get_review_options($progcontest, $attempt, $context) {
    $options = mod_progcontest_display_options::make_from_progcontest($progcontest, progcontest_attempt_state($progcontest, $attempt));

    $options->readonly = true;
    $options->flags = progcontest_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/progcontest/reviewquestion.php',
                array('attempt' => $attempt->id));
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == progcontest_attempt::FINISHED && !$attempt->preview &&
            !is_null($context) && has_capability('mod/progcontest:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/progcontest/comment.php',
                array('attempt' => $attempt->id));
    }

    if (!is_null($context) && !$attempt->preview &&
            has_capability('mod/progcontest:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
        $options->userinfoinhistory = $attempt->userid;

    }

    return $options;
}

/**
 * Combines the review options from a number of different progcontest attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = progcontest_get_combined_reviewoptions(...)
 *
 * @param object $progcontest the progcontest instance.
 * @param array $attempts an array of attempt objects.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function progcontest_get_combined_reviewoptions($progcontest, $attempts) {
    $fields = array('feedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    // This shouldn't happen, but we need to prevent reveal information.
    if (empty($attempts)) {
        return array($someoptions, $someoptions);
    }

    foreach ($attempts as $attempt) {
        $attemptoptions = mod_progcontest_display_options::make_from_progcontest($progcontest,
                progcontest_attempt_state($progcontest, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
        $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
        $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);
    }
    return array($someoptions, $alloptions);
}

// Functions for sending notification messages /////////////////////////////////

/**
 * Sends a confirmation message to the student confirming that the attempt was processed.
 *
 * @param object $a lots of useful information that can be used in the message
 *      subject and body.
 *
 * @return int|false as for {@link message_send()}.
 */
function progcontest_send_confirmation($recipient, $a) {

    // Add information about the recipient to $a.
    // Don't do idnumber. we want idnumber to be the submitter's idnumber.
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_progcontest';
    $eventdata->name              = 'confirmation';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailconfirmsubject', 'progcontest', $a);
    $eventdata->fullmessage       = get_string('emailconfirmbody', 'progcontest', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailconfirmsmall', 'progcontest', $a);
    $eventdata->contexturl        = $a->progcontesturl;
    $eventdata->contexturlname    = $a->progcontestname;
    $eventdata->customdata        = [
        'cmid' => $a->progcontestcmid,
        'instance' => $a->progcontestid,
        'attemptid' => $a->attemptid,
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Sends notification messages to the interested parties that assign the role capability
 *
 * @param object $recipient user object of the intended recipient
 * @param object $a associative array of replaceable fields for the templates
 *
 * @return int|false as for {@link message_send()}.
 */
function progcontest_send_notification($recipient, $submitter, $a) {
    global $PAGE;

    // Recipient info for template.
    $a->useridnumber = $recipient->idnumber;
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_progcontest';
    $eventdata->name              = 'submission';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = $submitter;
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailnotifysubject', 'progcontest', $a);
    $eventdata->fullmessage       = get_string('emailnotifybody', 'progcontest', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailnotifysmall', 'progcontest', $a);
    $eventdata->contexturl        = $a->progcontestreviewurl;
    $eventdata->contexturlname    = $a->progcontestname;
    $userpicture = new user_picture($submitter);
    $userpicture->size = 1; // Use f1 size.
    $userpicture->includetoken = $recipient->id; // Generate an out-of-session token for the user receiving the message.
    $eventdata->customdata        = [
        'cmid' => $a->progcontestcmid,
        'instance' => $a->progcontestid,
        'attemptid' => $a->attemptid,
        'notificationiconurl' => $userpicture->get_url($PAGE)->out(false),
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Send all the requried messages when a progcontest attempt is submitted.
 *
 * @param object $course the course
 * @param object $progcontest the progcontest
 * @param object $attempt this attempt just finished
 * @param object $context the progcontest context
 * @param object $cm the coursemodule for this progcontest
 *
 * @return bool true if all necessary messages were sent successfully, else false.
 */
function progcontest_send_notification_messages($course, $progcontest, $attempt, $context, $cm) {
    global $CFG, $DB;

    // Do nothing if required objects not present.
    if (empty($course) or empty($progcontest) or empty($attempt) or empty($context)) {
        throw new coding_exception('$course, $progcontest, $attempt, $context and $cm must all be set.');
    }

    $submitter = $DB->get_record('user', array('id' => $attempt->userid), '*', MUST_EXIST);

    // Check for confirmation required.
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/progcontest:emailconfirmsubmission', $context, $submitter, false)) {
        $notifyexcludeusers = $submitter->id;
        $sendconfirm = true;
    }

    // Check for notifications required.
    $notifyfields = 'u.id, u.username, u.idnumber, u.email, u.emailstop, u.lang,
            u.timezone, u.mailformat, u.maildisplay, u.auth, u.suspended, u.deleted, ';
    $userfieldsapi = \core_user\fields::for_name();
    $notifyfields .= $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $groups = groups_get_all_groups($course->id, $submitter->id, $cm->groupingid);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the progcontest is set to group mode,
        // then set $groups to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/progcontest:emailnotifysubmission',
            $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    if (empty($userstonotify) && !$sendconfirm) {
        return true; // Nothing to do.
    }

    $a = new stdClass();
    // Course info.
    $a->courseid        = $course->id;
    $a->coursename      = $course->fullname;
    $a->courseshortname = $course->shortname;
    // Programmingcontest info.
    $a->progcontestname        = $progcontest->name;
    $a->progcontestreporturl   = $CFG->wwwroot . '/mod/progcontest/report.php?id=' . $cm->id;
    $a->progcontestreportlink  = '<a href="' . $a->progcontestreporturl . '">' .
            format_string($progcontest->name) . ' report</a>';
    $a->progcontesturl         = $CFG->wwwroot . '/mod/progcontest/view.php?id=' . $cm->id;
    $a->progcontestlink        = '<a href="' . $a->progcontesturl . '">' . format_string($progcontest->name) . '</a>';
    $a->progcontestid          = $progcontest->id;
    $a->progcontestcmid        = $cm->id;
    // Attempt info.
    $a->submissiontime  = userdate($attempt->timefinish);
    $a->timetaken       = format_time($attempt->timefinish - $attempt->timestart);
    $a->progcontestreviewurl   = $CFG->wwwroot . '/mod/progcontest/review.php?attempt=' . $attempt->id;
    $a->progcontestreviewlink  = '<a href="' . $a->progcontestreviewurl . '">' .
            format_string($progcontest->name) . ' review</a>';
    $a->attemptid       = $attempt->id;
    // Student who sat the progcontest info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname     = fullname($submitter);
    $a->studentusername = $submitter->username;

    $allok = true;

    // Send notifications if required.
    if (!empty($userstonotify)) {
        foreach ($userstonotify as $recipient) {
            $allok = $allok && progcontest_send_notification($recipient, $submitter, $a);
        }
    }

    // Send confirmation if required. We send the student confirmation last, so
    // that if message sending is being intermittently buggy, which means we send
    // some but not all messages, and then try again later, then teachers may get
    // duplicate messages, but the student will always get exactly one.
    if ($sendconfirm) {
        $allok = $allok && progcontest_send_confirmation($submitter, $a);
    }

    return $allok;
}

/**
 * Send the notification message when a progcontest attempt becomes overdue.
 *
 * @param progcontest_attempt $attemptobj all the data about the progcontest attempt.
 */
function progcontest_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', array('id' => $attemptobj->get_userid()), '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/progcontest:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $progcontestname = format_string($attemptobj->get_progcontest_name());

    $deadlines = array();
    if ($attemptobj->get_progcontest()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_progcontest()->timelimit;
    }
    if ($attemptobj->get_progcontest()->timeclose) {
        $deadlines[] = $attemptobj->get_progcontest()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_progcontest()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_course()->id;
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname    = format_string($attemptobj->get_course()->shortname);
    // Programmingcontest info.
    $a->progcontestname           = $progcontestname;
    $a->progcontesturl            = $attemptobj->view_url();
    $a->progcontestlink           = '<a href="' . $a->progcontesturl . '">' . $progcontestname . '</a>';
    // Attempt info.
    $a->attemptduedate     = userdate($duedate);
    $a->attemptgraceend    = userdate($graceend);
    $a->attemptsummaryurl  = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $progcontestname . ' review</a>';
    // Student's info.
    $a->studentidnumber    = $submitter->idnumber;
    $a->studentname        = fullname($submitter);
    $a->studentusername    = $submitter->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_progcontest';
    $eventdata->name              = 'attempt_overdue';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $submitter;
    $eventdata->subject           = get_string('emailoverduesubject', 'progcontest', $a);
    $eventdata->fullmessage       = get_string('emailoverduebody', 'progcontest', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailoverduesmall', 'progcontest', $a);
    $eventdata->contexturl        = $a->progcontesturl;
    $eventdata->contexturlname    = $a->progcontestname;
    $eventdata->customdata        = [
        'cmid' => $attemptobj->get_cmid(),
        'instance' => $attemptobj->get_progcontestid(),
        'attemptid' => $attemptobj->get_attemptid(),
    ];

    // Send the message.
    return message_send($eventdata);
}

/**
 * Handle the progcontest_attempt_submitted event.
 *
 * This sends the confirmation and notification messages, if required.
 *
 * @param object $event the event object.
 */
function progcontest_attempt_submitted_handler($event) {
    global $DB;

    $course  = $DB->get_record('course', array('id' => $event->courseid));
    $attempt = $event->get_record_snapshot('progcontest_attempts', $event->objectid);
    $progcontest    = $event->get_record_snapshot('progcontest', $attempt->progcontest);
    $cm      = get_coursemodule_from_id('progcontest', $event->get_context()->instanceid, $event->courseid);

    if (!($course && $progcontest && $cm && $attempt)) {
        // Something has been deleted since the event was raised. Therefore, the
        // event is no longer relevant.
        return true;
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) &&
        ($progcontest->completionattemptsexhausted || $progcontest->completionpass || $progcontest->completionminattempts)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
    }
    return progcontest_send_notification_messages($course, $progcontest, $attempt,
            context_module::instance($cm->id), $cm);
}

/**
 * Handle groups_member_added event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_progcontest\group_observers::group_member_added()}.
 */
function progcontest_groups_member_added_handler($event) {
    debugging('progcontest_groups_member_added_handler() is deprecated, please use ' .
        '\mod_progcontest\group_observers::group_member_added() instead.', DEBUG_DEVELOPER);
    progcontest_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_member_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_progcontest\group_observers::group_member_removed()}.
 */
function progcontest_groups_member_removed_handler($event) {
    debugging('progcontest_groups_member_removed_handler() is deprecated, please use ' .
        '\mod_progcontest\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    progcontest_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_group_deleted event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_progcontest\group_observers::group_deleted()}.
 */
function progcontest_groups_group_deleted_handler($event) {
    global $DB;
    debugging('progcontest_groups_group_deleted_handler() is deprecated, please use ' .
        '\mod_progcontest\group_observers::group_deleted() instead.', DEBUG_DEVELOPER);
    progcontest_process_group_deleted_in_course($event->courseid);
}

/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function progcontest_process_group_deleted_in_course($courseid) {
    global $DB;

    // It would be nice if we got the groupid that was deleted.
    // Instead, we just update all progcontestzes with orphaned group overrides.
    $sql = "SELECT o.id, o.progcontest, o.groupid
              FROM {progcontest_overrides} o
              JOIN {progcontest} progcontest ON progcontest.id = o.progcontest
         LEFT JOIN {groups} grp ON grp.id = o.groupid
             WHERE progcontest.course = :courseid
               AND o.groupid IS NOT NULL
               AND grp.id IS NULL";
    $params = array('courseid' => $courseid);
    $records = $DB->get_records_sql($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('progcontest_overrides', 'id', array_keys($records));
    $cache = cache::make('mod_progcontest', 'overrides');
    foreach ($records as $record) {
        $cache->delete("{$record->progcontest}_g_{$record->groupid}");
    }
    progcontest_update_open_attempts(['progcontestid' => array_unique(array_column($records, 'progcontest'))]);
}

/**
 * Handle groups_members_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_progcontest\group_observers::group_member_removed()}.
 */
function progcontest_groups_members_removed_handler($event) {
    debugging('progcontest_groups_members_removed_handler() is deprecated, please use ' .
        '\mod_progcontest\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    if ($event->userid == 0) {
        progcontest_update_open_attempts(array('courseid'=>$event->courseid));
    } else {
        progcontest_update_open_attempts(array('courseid'=>$event->courseid, 'userid'=>$event->userid));
    }
}

/**
 * Get the information about the standard progcontest JavaScript module.
 * @return array a standard jsmodule structure.
 */
function progcontest_get_js_module() {
    global $PAGE;

    return array(
        'name' => 'mod_progcontest',
        'fullpath' => '/mod/progcontest/module.js',
        'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine', 'moodle-core-formchangechecker'),
        'strings' => array(
            array('cancel', 'moodle'),
            array('flagged', 'question'),
            array('functiondisabledbysecuremode', 'progcontest'),
            array('startattempt', 'progcontest'),
            array('timesup', 'progcontest'),
            array('changesmadereallygoaway', 'moodle'),
        ),
    );
}


/**
 * An extension of question_display_options that includes the extra options used
 * by the progcontest.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_progcontest_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * progcontest attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the progcontest settings, and a time constant.
     * @param object $progcontest the progcontest settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_progcontest_display_options set up appropriately.
     */
    public static function make_from_progcontest($progcontest, $when) {
        $options = new self();

        $options->attempt = self::extract($progcontest->reviewattempt, $when, true, false);
        $options->correctness = self::extract($progcontest->reviewcorrectness, $when);
        $options->marks = self::extract($progcontest->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($progcontest->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($progcontest->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($progcontest->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($progcontest->reviewoverallfeedback, $when);

        $options->numpartscorrect = $options->feedback;
        $options->manualcomment = $options->feedback;

        if ($progcontest->questiondecimalpoints != -1) {
            $options->markdp = $progcontest->questiondecimalpoints;
        } else {
            $options->markdp = $progcontest->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}

/**
 * A {@link qubaid_condition} for finding all the question usages belonging to
 * a particular progcontest.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_progcontest extends qubaid_join {
    public function __construct($progcontestid, $includepreviews = true, $onlyfinished = false) {
        $where = 'progcontesta.progcontest = :progcontestaprogcontest';
        $params = array('progcontestaprogcontest' => $progcontestid);

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = progcontest_attempt::FINISHED;
        }

        parent::__construct('{progcontest_attempts} progcontesta', 'progcontesta.uniqueid', $where, $params);
    }
}

/**
 * A {@link qubaid_condition} for finding all the question usages belonging to a particular user and progcontest combination.
 *
 * @copyright  2018 Andrew Nicols <andrwe@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_progcontest_user extends qubaid_join {
    /**
     * Constructor for this qubaid.
     *
     * @param   int     $progcontestid The progcontest to search.
     * @param   int     $userid The user to filter on
     * @param   bool    $includepreviews Whether to include preview attempts
     * @param   bool    $onlyfinished Whether to only include finished attempts or not
     */
    public function __construct($progcontestid, $userid, $includepreviews = true, $onlyfinished = false) {
        $where = 'progcontesta.progcontest = :progcontestaprogcontest AND progcontesta.userid = :progcontestauserid';
        $params = [
            'progcontestaprogcontest' => $progcontestid,
            'progcontestauserid' => $userid,
        ];

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = progcontest_attempt::FINISHED;
        }

        parent::__construct('{progcontest_attempts} progcontesta', 'progcontesta.uniqueid', $where, $params);
    }
}

/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @param bool $showidnumber If true, show the question's idnumber, if any. False by default.
 * @param core_tag_tag[]|bool $showtags if array passed, show those tags. Else, if true, get and show tags,
 *       else, don't show tags (which is the default).
 * @return string HTML fragment.
 */
function progcontest_question_tostring($question, $showicon = false, $showquestiontext = true,
        $showidnumber = false, $showtags = false) {
    global $OUTPUT;
    $result = '';

    // Question name.
    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    // Question idnumber.
    if ($showidnumber && $question->idnumber !== null && $question->idnumber !== '') {
        $result .= ' ' . html_writer::span(
                html_writer::span(get_string('idnumber', 'question'), 'accesshide') .
                ' ' . s($question->idnumber), 'badge badge-primary');
    }

    // Question tags.
    if (is_array($showtags)) {
        $tags = $showtags;
    } else if ($showtags) {
        $tags = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
    } else {
        $tags = [];
    }
    if ($tags) {
        $result .= $OUTPUT->tag_list($tags, null, 'd-inline', 0, null, true);
    }

    // Question text.
    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext,
                $question->questiontextformat, array('noclean' => true, 'para' => false));
        $questiontext = shorten_text($questiontext, 200);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function progcontest_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * @param object $progcontest the progcontest settings.
 * @param int $slot which question in the progcontest to test.
 * @return bool whether the user can use this question.
 */
function progcontest_has_question_use($progcontest, $slot) {
    global $DB;
    $question = $DB->get_record_sql("
            SELECT q.*
              FROM {progcontest_slots} slot
              JOIN {question} q ON q.id = slot.questionid
             WHERE slot.progcontestid = ? AND slot.slot = ?", array($progcontest->id, $slot));
    if (!$question) {
        return false;
    }
    return question_has_capability_on($question, 'use');
}

/**
 * Add a question to a progcontest
 *
 * Adds a question to a progcontest by updating $progcontest as well as the
 * progcontest and progcontest_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param object $progcontest The extended progcontest object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in progcontest to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the progcontest
 */
function progcontest_add_progcontest_question($questionid, $progcontest, $page = 0, $maxmark = null) {
    global $DB;

    // Make sue the question is not of the "random" type.
    $questiontype = $DB->get_field('question', 'qtype', array('id' => $questionid));
    if ($questiontype == 'random') {
        throw new coding_exception(
                'Adding "random" questions via progcontest_add_progcontest_question() is deprecated. Please use progcontest_add_random_questions().'
        );
    }

    $trans = $DB->start_delegated_transaction();
    $slots = $DB->get_records('progcontest_slots', array('progcontestid' => $progcontest->id),
            'slot', 'questionid, slot, page, id');
    if (array_key_exists($questionid, $slots)) {
        $trans->allow_commit();
        return false;
    }

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new question instance.
    $slot = new stdClass();
    $slot->progcontestid = $progcontest->id;
    $slot->questionid = $questionid;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('progcontest_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

        progcontest_update_section_firstslots($progcontest->id, 1, max($lastslotbefore, 1));

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($progcontest->questionsperpage && $numonlastpage >= $progcontest->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $DB->insert_record('progcontest_slots', $slot);
    $trans->allow_commit();
}

/**
 * Move all the section headings in a certain slot range by a certain offset.
 *
 * @param int $progcontestid the id of a progcontest
 * @param int $direction amount to adjust section heading positions. Normally +1 or -1.
 * @param int $afterslot adjust headings that start after this slot.
 * @param int|null $beforeslot optionally, only adjust headings before this slot.
 */
function progcontest_update_section_firstslots($progcontestid, $direction, $afterslot, $beforeslot = null) {
    global $DB;
    $where = 'progcontestid = ? AND firstslot > ?';
    $params = [$direction, $progcontestid, $afterslot];
    if ($beforeslot) {
        $where .= ' AND firstslot < ?';
        $params[] = $beforeslot;
    }
    $firstslotschanges = $DB->get_records_select_menu('progcontest_sections',
            $where, $params, '', 'firstslot, firstslot + ?');
    update_field_with_unique_index('progcontest_sections', 'firstslot', $firstslotschanges, ['progcontestid' => $progcontestid]);
}

/**
 * Add a random question to the progcontest at a given point.
 * @param stdClass $progcontest the progcontest settings.
 * @param int $addonpage the page on which to add the question.
 * @param int $categoryid the question category to add the question from.
 * @param int $number the number of random questions to add.
 * @param bool $includesubcategories whether to include questoins from subcategories.
 * @param int[] $tagids Array of tagids. The question that will be picked randomly should be tagged with all these tags.
 */
function progcontest_add_random_questions($progcontest, $addonpage, $categoryid, $number,
        $includesubcategories, $tagids = []) {
    global $DB;

    $category = $DB->get_record('question_categories', array('id' => $categoryid));
    if (!$category) {
        print_error('invalidcategoryid', 'error');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    $tags = \core_tag_tag::get_bulk($tagids, 'id, name');
    $tagstrings = [];
    foreach ($tags as $tag) {
        $tagstrings[] = "{$tag->id},{$tag->name}";
    }

    // Find existing random questions in this category that are
    // not used by any progcontest.
    $existingquestions = $DB->get_records_sql(
        "SELECT q.id, q.qtype FROM {question} q
        WHERE qtype = 'random'
            AND category = ?
            AND " . $DB->sql_compare_text('questiontext') . " = ?
            AND NOT EXISTS (
                    SELECT *
                      FROM {progcontest_slots}
                     WHERE questionid = q.id)
        ORDER BY id", array($category->id, $includesubcategories ? '1' : '0'));

    for ($i = 0; $i < $number; $i++) {
        // Take as many of orphaned "random" questions as needed.
        if (!$question = array_shift($existingquestions)) {
            $form = new stdClass();
            $form->category = $category->id . ',' . $category->contextid;
            $form->includesubcategories = $includesubcategories;
            $form->fromtags = $tagstrings;
            $form->defaultmark = 1;
            $form->hidden = 1;
            $form->stamp = make_unique_id_code(); // Set the unique code (not to be changed).
            $question = new stdClass();
            $question->qtype = 'random';
            $question = question_bank::get_qtype('random')->save_question($question, $form);
            if (!isset($question->id)) {
                print_error('cannotinsertrandomquestion', 'progcontest');
            }
        }

        $randomslotdata = new stdClass();
        $randomslotdata->progcontestid = $progcontest->id;
        $randomslotdata->questionid = $question->id;
        $randomslotdata->questioncategoryid = $categoryid;
        $randomslotdata->includingsubcategories = $includesubcategories ? 1 : 0;
        $randomslotdata->maxmark = 1;

        $randomslot = new \mod_progcontest\local\structure\slot_random($randomslotdata);
        $randomslot->set_progcontest($progcontest);
        $randomslot->set_tags($tags);
        $randomslot->insert($addonpage);
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $progcontest       progcontest object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.1
 */
function progcontest_view($progcontest, $course, $cm, $context) {

    $params = array(
        'objectid' => $progcontest->id,
        'context' => $context
    );

    $event = \mod_progcontest\event\course_module_viewed::create($params);
    $event->add_record_snapshot('progcontest', $progcontest);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Validate permissions for creating a new attempt and start a new preview attempt if required.
 *
 * @param  progcontest $progcontestobj progcontest object
 * @param  progcontest_access_manager $accessmanager progcontest access manager
 * @param  bool $forcenew whether was required to start a new preview attempt
 * @param  int $page page to jump to in the attempt
 * @param  bool $redirect whether to redirect or throw exceptions (for web or ws usage)
 * @return array an array containing the attempt information, access error messages and the page to jump to in the attempt
 * @throws moodle_progcontest_exception
 * @since Moodle 3.1
 */
function progcontest_validate_new_attempt(progcontest $progcontestobj, progcontest_access_manager $accessmanager, $forcenew, $page, $redirect) {
    global $DB, $USER;
    $timenow = time();

    if ($progcontestobj->is_preview_user() && $forcenew) {
        $accessmanager->current_attempt_finished();
    }

    // Check capabilities.
    if (!$progcontestobj->is_preview_user()) {
        $progcontestobj->require_capability('mod/progcontest:attempt');
    }

    // Check to see if a new preview was requested.
    if ($progcontestobj->is_preview_user() && $forcenew) {
        // To force the creation of a new preview, we mark the current attempt (if any)
        // as abandoned. It will then automatically be deleted below.
        $DB->set_field('progcontest_attempts', 'state', progcontest_attempt::ABANDONED,
                array('progcontest' => $progcontestobj->get_progcontestid(), 'userid' => $USER->id));
    }

    // Look for an existing attempt.
    $attempts = progcontest_get_user_attempts($progcontestobj->get_progcontestid(), $USER->id, 'all', true);
    $lastattempt = end($attempts);

    $attemptnumber = null;
    // If an in-progress attempt exists, check password then redirect to it.
    if ($lastattempt && ($lastattempt->state == progcontest_attempt::IN_PROGRESS ||
            $lastattempt->state == progcontest_attempt::OVERDUE)) {
        $currentattemptid = $lastattempt->id;
        $messages = $accessmanager->prevent_access();

        // If the attempt is now overdue, deal with that.
        $progcontestobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

        // And, if the attempt is now no longer in progress, redirect to the appropriate place.
        if ($lastattempt->state == progcontest_attempt::ABANDONED || $lastattempt->state == progcontest_attempt::FINISHED) {
            if ($redirect) {
                redirect($progcontestobj->review_url($lastattempt->id));
            } else {
                throw new moodle_progcontest_exception($progcontestobj, 'attemptalreadyclosed');
            }
        }

        // If the page number was not explicitly in the URL, go to the current page.
        if ($page == -1) {
            $page = $lastattempt->currentpage;
        }

    } else {
        while ($lastattempt && $lastattempt->preview) {
            $lastattempt = array_pop($attempts);
        }

        // Get number for the next or unfinished attempt.
        if ($lastattempt) {
            $attemptnumber = $lastattempt->attempt + 1;
        } else {
            $lastattempt = false;
            $attemptnumber = 1;
        }
        $currentattemptid = null;

        $messages = $accessmanager->prevent_access() +
            $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

        if ($page == -1) {
            $page = 0;
        }
    }
    return array($currentattemptid, $attemptnumber, $lastattempt, $messages, $page);
}

/**
 * Prepare and start a new attempt deleting the previous preview attempts.
 *
 * @param progcontest $progcontestobj progcontest object
 * @param int $attemptnumber the attempt number
 * @param object $lastattempt last attempt object
 * @param bool $offlineattempt whether is an offline attempt or not
 * @param array $forcedrandomquestions slot number => question id. Used for random questions,
 *      to force the choice of a particular actual question. Intended for testing purposes only.
 * @param array $forcedvariants slot number => variant. Used for questions with variants,
 *      to force the choice of a particular variant. Intended for testing purposes only.
 * @param int $userid Specific user id to create an attempt for that user, null for current logged in user
 * @return object the new attempt
 * @since  Moodle 3.1
 */
function progcontest_prepare_and_start_new_attempt(progcontest $progcontestobj, $attemptnumber, $lastattempt,
        $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
        $ispreviewuser = $progcontestobj->is_preview_user();
    } else {
        $ispreviewuser = has_capability('mod/progcontest:preview', $progcontestobj->get_context(), $userid);
    }
    // Delete any previous preview attempts belonging to this user.
    progcontest_delete_previews($progcontestobj->get_progcontest(), $userid);

    $quba = question_engine::make_questions_usage_by_activity('mod_progcontest', $progcontestobj->get_context());
    $quba->set_preferred_behaviour($progcontestobj->get_progcontest()->preferredbehaviour);

    // Create the new attempt and initialize the question sessions
    $timenow = time(); // Update time now, in case the server is running really slowly.
    $attempt = progcontest_create_attempt($progcontestobj, $attemptnumber, $lastattempt, $timenow, $ispreviewuser, $userid);

    if (!($progcontestobj->get_progcontest()->attemptonlast && $lastattempt)) {
        $attempt = progcontest_start_new_attempt($progcontestobj, $quba, $attempt, $attemptnumber, $timenow,
                $forcedrandomquestions, $forcedvariants);
    } else {
        $attempt = progcontest_start_attempt_built_on_last($quba, $attempt, $lastattempt);
    }

    $transaction = $DB->start_delegated_transaction();

    // Init the timemodifiedoffline for offline attempts.
    if ($offlineattempt) {
        $attempt->timemodifiedoffline = $attempt->timemodified;
    }
    $attempt = progcontest_attempt_save_started($progcontestobj, $quba, $attempt);

    $transaction->allow_commit();

    return $attempt;
}

/**
 * Check if the given calendar_event is either a user or group override
 * event for progcontest.
 *
 * @param calendar_event $event The calendar event to check
 * @return bool
 */
function progcontest_is_overriden_calendar_event(\calendar_event $event) {
    global $DB;

    if (!isset($event->modulename)) {
        return false;
    }

    if ($event->modulename != 'progcontest') {
        return false;
    }

    if (!isset($event->instance)) {
        return false;
    }

    if (!isset($event->userid) && !isset($event->groupid)) {
        return false;
    }

    $overrideparams = [
        'progcontest' => $event->instance
    ];

    if (isset($event->groupid)) {
        $overrideparams['groupid'] = $event->groupid;
    } else if (isset($event->userid)) {
        $overrideparams['userid'] = $event->userid;
    }

    return $DB->record_exists('progcontest_overrides', $overrideparams);
}

/**
 * Retrieves tag information for the given list of progcontest slot ids.
 * Currently the only slots that have tags are random question slots.
 *
 * Example:
 * If we have 3 slots with id 1, 2, and 3. The first slot has two tags, the second
 * has one tag, and the third has zero tags. The return structure will look like:
 * [
 *      1 => [
 *          progcontest_slot_tags.id => { ...tag data... },
 *          progcontest_slot_tags.id => { ...tag data... },
 *      ],
 *      2 => [
 *          progcontest_slot_tags.id => { ...tag data... },
 *      ],
 *      3 => [],
 * ]
 *
 * @param int[] $slotids The list of id for the progcontest slots.
 * @return array[] List of progcontest_slot_tags records indexed by slot id.
 */
function progcontest_retrieve_tags_for_slot_ids($slotids) {
    global $DB;

    if (empty($slotids)) {
        return [];
    }

    $slottags = $DB->get_records_list('progcontest_slot_tags', 'slotid', $slotids);
    $tagsbyid = core_tag_tag::get_bulk(array_filter(array_column($slottags, 'tagid')), 'id, name');
    $tagsbyname = false; // It will be loaded later if required.
    $emptytagids = array_reduce($slotids, function($carry, $slotid) {
        $carry[$slotid] = [];
        return $carry;
    }, []);

    return array_reduce(
        $slottags,
        function($carry, $slottag) use ($slottags, $tagsbyid, $tagsbyname) {
            if (isset($tagsbyid[$slottag->tagid])) {
                // Make sure that we're returning the most updated tag name.
                $slottag->tagname = $tagsbyid[$slottag->tagid]->name;
            } else {
                if ($tagsbyname === false) {
                    // We were hoping that this query could be avoided, but life
                    // showed its other side to us!
                    $tagcollid = core_tag_area::get_collection('core', 'question');
                    $tagsbyname = core_tag_tag::get_by_name_bulk(
                        $tagcollid,
                        array_column($slottags, 'tagname'),
                        'id, name'
                    );
                }
                if (isset($tagsbyname[$slottag->tagname])) {
                    // Make sure that we're returning the current tag id that matches
                    // the given tag name.
                    $slottag->tagid = $tagsbyname[$slottag->tagname]->id;
                } else {
                    // The tag does not exist anymore (neither the tag id nor the tag name
                    // matches an existing tag).
                    // We still need to include this row in the result as some callers might
                    // be interested in these rows. An example is the editing forms that still
                    // need to display tag names even if they don't exist anymore.
                    $slottag->tagid = null;
                }
            }

            $carry[$slottag->slotid][$slottag->id] = $slottag;
            return $carry;
        },
        $emptytagids
    );
}

/**
 * Retrieves tag information for the given progcontest slot.
 * A progcontest slot have some tags if and only if it is representing a random question by tags.
 *
 * @param int $slotid The id of the progcontest slot.
 * @return stdClass[] List of progcontest_slot_tags records.
 */
function progcontest_retrieve_slot_tags($slotid) {
    $slottags = progcontest_retrieve_tags_for_slot_ids([$slotid]);
    return $slottags[$slotid];
}

/**
 * Retrieves tag ids for the given progcontest slot.
 * A progcontest slot have some tags if and only if it is representing a random question by tags.
 *
 * @param int $slotid The id of the progcontest slot.
 * @return int[]
 */
function progcontest_retrieve_slot_tag_ids($slotid) {
    $tags = progcontest_retrieve_slot_tags($slotid);

    // Only work with tags that exist.
    return array_filter(array_column($tags, 'tagid'));
}

/**
 * Get progcontest attempt and handling error.
 *
 * @param int $attemptid the id of the current attempt.
 * @param int|null $cmid the course_module id for this progcontest.
 * @return progcontest_attempt $attemptobj all the data about the progcontest attempt.
 * @throws moodle_exception
 */
function progcontest_create_attempt_handling_errors($attemptid, $cmid = null) {
    try {
        $attempobj = progcontest_attempt::create($attemptid);
    } catch (moodle_exception $e) {
        if (!empty($cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'progcontest');
            $continuelink = new moodle_url('/mod/progcontest/view.php', array('id' => $cmid));
            $context = context_module::instance($cm->id);
            if (has_capability('mod/progcontest:preview', $context)) {
                throw new moodle_exception('attempterrorcontentchange', 'progcontest', $continuelink);
            } else {
                throw new moodle_exception('attempterrorcontentchangeforuser', 'progcontest', $continuelink);
            }
        } else {
            throw new moodle_exception('attempterrorinvalid', 'progcontest');
        }
    }
    if (!empty($cmid) && $attempobj->get_cmid() != $cmid) {
        throw new moodle_exception('invalidcoursemodule');
    } else {
        return $attempobj;
    }
}

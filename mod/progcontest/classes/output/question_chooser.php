<?php
namespace mod_progcontest\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Dialogue de choix de type de question sans filtrage personnalisé.
 * Utilise le comportement standard de Moodle.
 */
class question_chooser extends \core_question\output\qbank_chooser {
    // Pas de surcharge = pas de filtrage.
}

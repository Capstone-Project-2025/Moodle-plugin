YUI.add('moodle-mod_progcontest-modform', function (Y, NAME) {

/**
 * The modform class has all the JavaScript specific to mod/progcontest/mod_form.php.
 *
 * @module moodle-mod_progcontest-modform
 */

var MODFORM = function() {
    MODFORM.superclass.constructor.apply(this, arguments);
};

/**
 * The coursebase class to provide shared functionality to Modules within
 * Moodle.
 *
 * @class M.course.coursebase
 * @constructor
 */
Y.extend(MODFORM, Y.Base, {
    repaginateCheckbox: null,
    qppSelect: null,
    qppInitialValue: 0,

    initializer: function() {
        // Liste des sections à masquer (par leur ID).
        var hiddenSectionIds = [
            'id_layoutsection',                 // Layout
            'id_interactionhdr',               // Question behaviour
            'id_reviewoptionshdr',             // Review options
            'id_display',                      // Apparence
            'id_safeexambrowsersection',       // Safe Exam Browser
            'id_overallfeedbackhdr',           // Overall feedback
            'id_availabilityconditionsheader', // Restrict access
            'id_competenciessection'           // Competencies
        ];

        hiddenSectionIds.forEach(function(id) {
            var node = Y.one('#' + id);
            if (node) {
                var container = node.ancestor('.fcontainer');
                if (container) {
                    container.setStyle('display', 'none');
                } else {
                    node.setStyle('display', 'none'); // Fallback
                }
            }
        });

        // === Ancien comportement (repagination) ===
        this.repaginateCheckbox = Y.one('#id_repaginatenow');
        if (!this.repaginateCheckbox) {
            // The checkbox only appears when editing an existing progcontest.
            return;
        }

        this.qppSelect = Y.one('#id_questionsperpage');
        this.qppInitialValue = this.qppSelect.get('value');
        this.qppSelect.on('change', this.qppChanged, this);
    },

    qppChanged: function() {
        Y.later(50, this, function() {
            if (!this.repaginateCheckbox.get('disabled')) {
                this.repaginateCheckbox.set('checked', this.qppSelect.get('value') !== this.qppInitialValue);
            }
        });
    }
});

// Ensure that M.mod_progcontest exists and that coursebase is initialised correctly
M.mod_progcontest = M.mod_progcontest || {};
M.mod_progcontest.modform = M.mod_progcontest.modform || new MODFORM();
M.mod_progcontest.modform.init = function() {
    return new MODFORM();
};


}, '@VERSION@', {"requires": ["base", "node", "event"]});

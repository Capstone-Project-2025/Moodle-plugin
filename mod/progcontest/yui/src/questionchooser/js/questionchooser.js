
var CSS = {
    ADDNEWQUESTIONBUTTONS: '.menu [data-action="addquestion"]',
    CREATENEWQUESTION: 'div.createnewquestion',
    CHOOSERDIALOGUE: 'div.chooserdialoguebody',
    CHOOSERHEADER: 'div.choosertitle'
};

var QUESTIONCHOOSER = function() {
    QUESTIONCHOOSER.superclass.constructor.apply(this, arguments);
};

Y.extend(QUESTIONCHOOSER, M.core.chooserdialogue, {
    initializer: function() {
        Y.one('body').delegate('click', this.display_dialogue, CSS.ADDNEWQUESTIONBUTTONS, this);
    },

    display_dialogue: function(e) {
        e.preventDefault();

        var dialogue = Y.one(CSS.CREATENEWQUESTION + ' ' + CSS.CHOOSERDIALOGUE),
            header = Y.one(CSS.CREATENEWQUESTION + ' ' + CSS.CHOOSERHEADER);

        if (this.container === null) {
            this.setup_chooser_dialogue(dialogue, header, {});
            this.prepare_chooser();
        }

        var parameters = Y.QueryString.parse(e.currentTarget.get('search').substring(1));
        parameters.component = 'mod_progcontest'; // Important si ton plugin utilise ce paramètre

        var form = this.container.one('form');
        this.parameters_to_hidden_input(parameters, form, 'returnurl');
        this.parameters_to_hidden_input(parameters, form, 'cmid');
        this.parameters_to_hidden_input(parameters, form, 'category');
        this.parameters_to_hidden_input(parameters, form, 'addonpage');
        this.parameters_to_hidden_input(parameters, form, 'appendqnumstring');

        this.display_chooser(e);
    },

    parameters_to_hidden_input: function(parameters, form, name) {
        var value = parameters.hasOwnProperty(name) ? parameters[name] : '';
        var input = form.one('input[name=' + name + ']');
        if (!input) {
            input = form.appendChild('<input type="hidden">');
            input.set('name', name);
        }
        input.set('value', value);
    }
}, {
    NAME: 'mod_progcontest-questionchooser'
});

M.mod_progcontest = M.mod_progcontest || {};
M.mod_progcontest.init_questionchooser = function() {
    M.mod_progcontest.question_chooser = new QUESTIONCHOOSER({});
    return M.mod_progcontest.question_chooser;
};

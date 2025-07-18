/* eslint-disable no-console */
/* global tinyMCE, Y */

define(['core/ajax', 'core/str', 'core/config'], function(Ajax, Str, Config) {

    return {
        init: function() {
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.querySelector('[name="problemlist"]');
                const nameField = document.querySelector('[name="name"]');
                const codeField = document.querySelector('[name="problemcode"]');
                const nameFieldWrapper = document.querySelector('[id^="fitem_id_name"]');
                const problemIdField = document.querySelector('[name="problemid"]');
                const editorId = 'id_questiontext';

                if (nameFieldWrapper) {
                    nameFieldWrapper.style.display = 'none';
                }

                // Fetch the list of available problems
                fetch(Config.wwwroot + '/question/type/programming/fetchproblems.php')
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(problem => {
                            const option = document.createElement('option');
                            option.value = problem.code;
                            option.textContent = problem.name;
                            select.appendChild(option);
                        });
                    });

                // On selection change, fetch problem info and update fields
                select.addEventListener('change', function() {
                    const code = this.value;
                    codeField.value = code;

                    fetch(Config.wwwroot + '/question/type/programming/fetchproblem.php?code=' + encodeURIComponent(code))
                        .then(response => response.json())
                        .then(data => {
                            if (nameField) {
                                nameField.value = data.name || '';
                            }
                            if (codeField) {
                                codeField.value = data.code || '';
                            }
                            if (problemIdField) {
                                problemIdField.value = data.id || '';
                            }

                            const description = data.description || '';

                            // TinyMCE
                            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                                tinyMCE.get(editorId).setContent(description);
                            }

                            // Atto
                            if (typeof Y !== 'undefined' && Y.one('#' + editorId + '_editable')) {
                                Y.one('#' + editorId + '_editable').setHTML(description);
                            }

                            // Fallback (raw textarea)
                            const rawTextarea = document.getElementById(editorId);
                            if (rawTextarea) {
                                rawTextarea.value = description;
                            }
                        });
                });
            });
        }
    };
});

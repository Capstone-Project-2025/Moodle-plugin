/* global CodeMirror, M */
/**
 * Module AMD Moodle pour charger CodeMirror (core + modes) sans conflit RequireJS.
 * @module qtype_programming/codemirror_loader
 */
define([], function() {

    /**
     * Injecte un script en conservant l'ordre de chargement (async=false).
     * @param {string} url URL absolue ou basée sur M.cfg.wwwroot.
     * @returns {Promise<void>} Promesse résolue quand le script est chargé.
     */
    function loadScript(url) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = url;
            s.async = false; // important pour garder l'ordre
            s.onload = resolve;
            s.onerror = function() {
                reject(new Error('Erreur de chargement : ' + url));
            };
            document.head.appendChild(s);
        });
    }

    /**
     * Charge CodeMirror core + les modes nécessaires en "aveuglant" AMD temporairement
     * pour éviter que le wrapper UMD de CodeMirror ne déclenche un define() anonyme.
     * @returns {Promise<void>} Promesse résolue quand tous les scripts sont chargés.
     */
    function loadCodeMirrorFiles() {
        var baseurl =
            M.cfg.wwwroot + '/question/type/programming/thirdparty/codemirror';

        var scripts = [
            baseurl + '/lib/codemirror.js',
            baseurl + '/mode/clike/clike.js',
            baseurl + '/mode/python/python.js',
            baseurl + '/mode/pascal/pascal.js',
            baseurl + '/mode/perl/perl.js',
            baseurl + '/mode/gas/gas.js',
            baseurl + '/mode/shell/shell.js',
            baseurl + '/mode/brainfuck/brainfuck.js',
        ];

        var savedDefine = window.define;
        var savedModule = window.module;

        return (function() {
            // Désactive AMD/CommonJS pendant le chargement
            try { window.define = undefined; } catch (e) {}
            try { window.module = undefined; } catch (e) {}

            // Chargement séquentiel
            var chain = Promise.resolve();
            scripts.forEach(function(u) {
                chain = chain.then(function() { return loadScript(u); });
            });
            return chain;
        })().finally(function() {
            // Restaure l'environnement (split pour respecter max-len)
            if (typeof savedDefine !== 'undefined') {
                window.define = savedDefine;
            } else {
                try {
                    delete window.define;
                } catch (e) {
                    // ignore
                }
            }

            if (typeof savedModule !== 'undefined') {
                window.module = savedModule;
            } else {
                try {
                    delete window.module;
                } catch (e) {
                    // ignore
                }
            }
        });
    }

    return {
        /**
         * Initialise un éditeur CodeMirror sur un <textarea>.
         * @param {string} editorId ID du textarea.
         * @param {string} initialLanguage Libellé de langue (ex: "python3", "c++17").
         * @param {string} initialTheme Thème initial (ex: "material-darker" ou "eclipse").
         * @param {string|null} toggleBtnId ID d’un bouton pour basculer le thème (ou null).
         * @param {string|null} languageSelectId ID d’un <select> pour changer le mode (ou null).
         * @returns {Promise<CodeMirror.Editor|undefined>} L’éditeur ou undefined si non créé.
         */
        init: async function(editorId, initialLanguage, initialTheme, toggleBtnId, languageSelectId) {
            await loadCodeMirrorFiles();

            if (typeof CodeMirror === 'undefined') {
                return;
            }

            var langMap = {
                python2: 'python',
                python3: 'python',
                c: 'text/x-csrc',
                c11: 'text/x-csrc',
                'c++03': 'text/x-c++src',
                'c++11': 'text/x-c++src',
                'c++14': 'text/x-c++src',
                'c++17': 'text/x-c++src',
                'c++20': 'text/x-c++src',
                java8: 'text/x-java',
                java: 'text/x-java',
                pascal: 'pascal',
                perl: 'perl',
                brainfuck: 'brainfuck',
                assemblyx86: 'gas',
                assemblyx64: 'gas',
                sed: 'shell',
                text: 'text/plain',
                default: 'text/plain'
            };

            var textarea = document.getElementById(editorId);
            if (!textarea) {
                return;
            }

            var currentTheme = initialTheme || 'material-darker';
            var cleanedLanguage =
                (initialLanguage || '').toLowerCase().replace(/\s+/g, '');
            var mode = langMap[cleanedLanguage] || langMap.default;

            var editor = CodeMirror.fromTextArea(textarea, {
                mode: mode,
                theme: currentTheme,
                lineNumbers: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                matchBrackets: true,
                autoCloseBrackets: true,
                lineWrapping: true
            });

            if (toggleBtnId) {
                var toggleBtn = document.getElementById(toggleBtnId);
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function() {
                        currentTheme =
                            (currentTheme === 'eclipse') ? 'material-darker' : 'eclipse';
                        editor.setOption('theme', currentTheme);
                    });
                }
            }

            if (languageSelectId) {
                var select = document.getElementById(languageSelectId);
                if (select) {
                    select.addEventListener('change', function() {
                        var selectedLang =
                            this.options[this.selectedIndex].text
                                .toLowerCase()
                                .replace(/\s+/g, '');
                        var newMode = langMap[selectedLang] || langMap.default;
                        editor.setOption('mode', newMode);
                    });
                }
            }

            window['codemirrorEditor_' + editorId] = editor;
            return editor;
        }
    };
});

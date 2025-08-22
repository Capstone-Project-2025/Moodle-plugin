/* global CodeMirror */
define([], function() {
    return {
        init: function(editorId, initialLanguage, initialTheme, toggleBtnId, languageSelectId) {
            const langMap = {
                'python2': 'python',
                'python3': 'python',
                'c': 'text/x-csrc',
                'c11': 'text/x-csrc',
                'c++03': 'text/x-c++src',
                'c++11': 'text/x-c++src',
                'c++14': 'text/x-c++src',
                'c++17': 'text/x-c++src',
                'c++20': 'text/x-c++src',
                'java8': 'text/x-java',
                'java': 'text/x-java',
                'pascal': 'pascal',
                'perl': 'perl',
                'awk': 'awk',
                'brainfuck': 'brainfuck',
                'assemblyx86': 'gas',
                'assemblyx64': 'gas',
                'sed': 'shell',
                'text': 'text/plain',
                'default': 'text/plain'
            };

            const textarea = document.getElementById(editorId);
            if (!textarea || typeof CodeMirror === 'undefined') {
                return;
            }

            let currentTheme = initialTheme || 'material-darker';
            let cleanedLanguage = initialLanguage?.toLowerCase().replace(/\s+/g, '');
            const mode = langMap[cleanedLanguage] || langMap['default'];


            const editor = CodeMirror.fromTextArea(textarea, {
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

            // 🎨 Toggle theme
            if (toggleBtnId) {
                const toggleBtn = document.getElementById(toggleBtnId);
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        currentTheme = (currentTheme === 'eclipse') ? 'material-darker' : 'eclipse';
                        editor.setOption('theme', currentTheme);
                    });
                }
            }

            // 🌐 Dynamically update language mode
            if (languageSelectId) {
                const select = document.getElementById(languageSelectId);
                if (select) {
                    select.addEventListener('change', function() {
                        const selectedLang = this.options[this.selectedIndex].text.toLowerCase().replace(/\s+/g, '');
                        const newMode = langMap[selectedLang] || langMap['default'];
                        editor.setOption('mode', newMode);
                    });
                }
            }

            // 📦 Stocker dans window si besoin ailleurs
            window['codemirrorEditor_' + editorId] = editor;

            return editor;
        }
    };
});

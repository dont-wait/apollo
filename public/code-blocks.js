(function () {
    var LABELS = {
        js: 'JavaScript', javascript: 'JavaScript', ts: 'TypeScript', typescript: 'TypeScript', jsx: 'JSX', tsx: 'TSX',
        php: 'PHP', py: 'Python', python: 'Python', sh: 'Shell', bash: 'Bash', shell: 'Shell', zsh: 'Zsh',
        json: 'JSON', html: 'HTML', xml: 'XML', css: 'CSS', scss: 'SCSS', sql: 'SQL', yaml: 'YAML', yml: 'YAML',
        md: 'Markdown', markdown: 'Markdown', diff: 'Diff', go: 'Go', rust: 'Rust', rs: 'Rust', java: 'Java',
        c: 'C', cpp: 'C++', csharp: 'C#', cs: 'C#', ruby: 'Ruby', rb: 'Ruby', kotlin: 'Kotlin', swift: 'Swift',
        blade: 'Blade', dockerfile: 'Dockerfile', ini: 'INI', toml: 'TOML', text: 'Text', plaintext: 'Text',
    };

    // Common aliases highlight.js does not know under the same name.
    var ALIASES = { blade: 'php', zsh: 'bash', shell: 'bash', text: 'plaintext' };

    function copy(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
            area.remove();
        });
    }

    window.enhanceCodeBlocks = function (root) {
        root.querySelectorAll('pre').forEach(function (pre) {
            var code = pre.querySelector('code');

            if (!code || pre.parentElement.classList.contains('code-block')) {
                return;
            }

            var match = code.className.match(/language-([\w+#.-]+)/);
            var language = match ? match[1].toLowerCase() : '';

            if (language && window.hljs) {
                var hljsLanguage = window.hljs.getLanguage(language) ? language : ALIASES[language];

                if (hljsLanguage && window.hljs.getLanguage(hljsLanguage)) {
                    code.classList.add('language-' + hljsLanguage);
                    window.hljs.highlightElement(code);
                }
            }

            var wrapper = document.createElement('div');
            wrapper.className = 'code-block';

            var bar = document.createElement('div');
            bar.className = 'code-block__bar';

            var label = document.createElement('span');
            label.className = 'code-block__lang';
            label.textContent = language ? (LABELS[language] || language) : 'Text';

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'code-block__copy';
            button.textContent = 'Copy';
            button.addEventListener('click', function () {
                copy(code.textContent).then(function () {
                    button.textContent = 'Copied!';
                    button.dataset.copied = 'true';
                }, function () {
                    button.textContent = 'Failed';
                }).then(function () {
                    setTimeout(function () {
                        button.textContent = 'Copy';
                        delete button.dataset.copied;
                    }, 1500);
                });
            });

            bar.append(label, button);
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.append(bar, pre);
        });
    };

    function init() {
        document.querySelectorAll('[data-article-content]').forEach(function (el) { window.enhanceCodeBlocks(el); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

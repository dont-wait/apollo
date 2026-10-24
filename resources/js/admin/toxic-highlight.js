const syncOverlay = (editor) => {
    const textarea = editor.querySelector('textarea');
    const overlay = editor.querySelector('[data-toxic-overlay]');

    if (!textarea || !overlay) {
        return;
    }

    overlay.scrollTop = textarea.scrollTop;
    overlay.scrollLeft = textarea.scrollLeft;
};

const initEditor = (editor) => {
    const textarea = editor.querySelector('textarea');
    const overlay = editor.querySelector('[data-toxic-overlay]');

    if (!textarea || !overlay) {
        return;
    }

    if (editor.dataset.toxicBound === 'true') {
        return;
    }

    editor.dataset.toxicBound = 'true';

    textarea.addEventListener('input', () => {
        editor.classList.remove('is-toxic-highlighted');
        overlay.innerHTML = '';
        overlay.classList.add('is-empty');

        const jump = document.querySelector(`[data-toxic-jump="${editor.dataset.toxicField}"]`);

        if (jump) {
            jump.hidden = true;
        }
    });

    textarea.addEventListener('scroll', () => syncOverlay(editor));
    window.addEventListener('resize', () => syncOverlay(editor));

    syncOverlay(editor);
};

const initJumpButton = (editor, fieldName) => {
    const button = document.querySelector(`[data-toxic-jump="${fieldName}"]`);

    if (!button || button.dataset.toxicJumpBound === 'true') {
        return;
    }

    button.dataset.toxicJumpBound = 'true';

    button.addEventListener('click', () => {
        const firstMark = editor.querySelector('[data-toxic-overlay] mark');
        const textarea = editor.querySelector('textarea');

        if (!textarea) {
            return;
        }

        const offset = Number(button.dataset.toxicOffset ?? 0);
        const position = Math.min(Math.max(offset, 0), textarea.value.length);

        textarea.focus();
        textarea.setSelectionRange(position, position);

        if (firstMark) {
            textarea.scrollTop = Math.max(0, firstMark.offsetTop - textarea.clientHeight / 3);
            textarea.scrollLeft = Math.max(0, firstMark.offsetLeft - textarea.clientWidth / 3);
        }

        syncOverlay(editor);
    });
};

const applyField = (fieldName, field) => {
    const editor = document.querySelector(`[data-toxic-editor][data-toxic-field="${fieldName}"]`);
    const snippet = document.querySelector(`[data-toxic-snippet="${fieldName}"]`);

    if (editor) {
        const overlay = editor.querySelector('[data-toxic-overlay]');
        const jump = document.querySelector(`[data-toxic-jump="${fieldName}"]`);

        if (jump) {
            jump.dataset.toxicOffset = String(field.first_offset ?? 0);
            jump.hidden = !field.overlay_html;
        }

        if (overlay) {
            overlay.innerHTML = field.overlay_html ?? '';
            overlay.classList.remove('is-empty');
            editor.classList.add('is-toxic-highlighted');
            initEditor(editor);
            syncOverlay(editor);
            initJumpButton(editor, fieldName);
        }
    }

    if (snippet && Array.isArray(field.snippets) && field.snippets.length > 0) {
        snippet.innerHTML = field.snippets.join(' ');
        snippet.hidden = false;
    }

    const preview = document.querySelector(`[data-toxic-preview="${fieldName}"]`);

    if (preview && typeof field.preview_html === 'string') {
        preview.innerHTML = field.preview_html;
        preview.dataset.toxicPreviewLocked = 'true';
        preview.hidden = false;
        preview.removeAttribute('data-empty');

        const empty = document.querySelector('[data-toxic-preview-empty="' + fieldName + '"]');

        if (empty) {
            empty.hidden = true;
        }
    }
};

const applyModeration = (moderation) => {
    if (!moderation || typeof moderation !== 'object' || !moderation.fields) {
        return;
    }

    document.querySelectorAll('[data-toxic-snippet]').forEach((snippet) => {
        snippet.hidden = true;
        snippet.innerHTML = '';
    });

    document.querySelectorAll('[data-toxic-jump]').forEach((button) => {
        button.hidden = true;
    });

    document.querySelectorAll('[data-toxic-preview] mark.toxic-word-hit').forEach((mark) => {
        mark.replaceWith(...mark.childNodes);
    });

    document.querySelectorAll('[data-toxic-editor]').forEach((editor) => {
        const overlay = editor.querySelector('[data-toxic-overlay]');

        if (overlay) {
            overlay.innerHTML = '';
            overlay.classList.add('is-empty');
        }

        editor.classList.remove('is-toxic-highlighted');
    });

    document.querySelectorAll('[data-toxic-summary]').forEach((summary) => {
        summary.textContent = moderation.summary ?? '';
        summary.hidden = false;
    });

    Object.entries(moderation.fields).forEach(([fieldName, field]) => {
        applyField(fieldName, field ?? {});
    });
};

const initialise = () => {
    document.querySelectorAll('[data-toxic-editor]').forEach(initEditor);

    const payload = document.querySelector('[data-toxic-moderation]');

    if (payload) {
        try {
            applyModeration(JSON.parse(payload.textContent));
        } catch (error) {
            // Ignore an empty or malformed payload; the server still blocks the submit.
        }
    }
};

const initToxicWordsPage = () => {
    const page = document.querySelector('[data-toxic-words-page]');

    if (!page) {
        return;
    }

    const form = page.querySelector('[data-toxic-word-form]');
    const methodInput = page.querySelector('[data-toxic-word-method]');
    const title = page.querySelector('#toxicWordEditorTitle');
    const submitLabel = page.querySelector('[data-toxic-word-submit-label]');
    const cancel = page.querySelector('[data-toxic-word-cancel]');
    const storeUrl = form?.dataset.storeUrl ?? '';

    if (!form || !methodInput || !title || !submitLabel || !cancel) {
        return;
    }

    const reset = () => {
        form.action = storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Thêm từ mới';
        submitLabel.textContent = 'Thêm từ';
        form.reset();

        form.querySelectorAll('input[name="scopes[]"]').forEach((input) => {
            input.checked = true;
        });

        const active = form.querySelector('input[name="is_active"]');
        const fold = form.querySelector('input[name="fold_diacritics"]');

        if (active) {
            active.checked = true;
        }

        if (fold) {
            fold.checked = false;
        }

        cancel.classList.add('hidden');
    };

    page.querySelectorAll('[data-edit-word]').forEach((button) => {
        button.addEventListener('click', () => {
            let word;

            try {
                word = JSON.parse(button.dataset.editWord ?? '{}');
            } catch (error) {
                return;
            }

            form.action = word.update_url;
            methodInput.value = 'PUT';
            title.textContent = 'Sửa từ';

            const term = form.querySelector('input[name="term"]');
            const mode = form.querySelector('select[name="match_mode"]');
            const note = form.querySelector('input[name="note"]');
            const active = form.querySelector('input[name="is_active"]');
            const fold = form.querySelector('input[name="fold_diacritics"]');

            if (term) {
                term.value = word.term ?? '';
            }

            if (mode) {
                mode.value = word.match_mode ?? 'WORD';
            }

            if (note) {
                note.value = word.note ?? '';
            }

            if (active) {
                active.checked = Boolean(word.is_active);
            }

            if (fold) {
                fold.checked = Boolean(word.fold_diacritics);
            }

            form.querySelectorAll('input[name="scopes[]"]').forEach((input) => {
                input.checked = Array.isArray(word.scopes) && word.scopes.includes(input.value);
            });

            submitLabel.textContent = 'Lưu thay đổi';
            cancel.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    cancel.addEventListener('click', reset);
};

window.applyToxicHighlights = applyModeration;

document.addEventListener('input', (event) => {
    const name = event.target?.getAttribute?.('name')?.replace(/\[\]$/, '');

    if (!name) {
        return;
    }

    const snippet = document.querySelector(`[data-toxic-snippet="${name}"]`);

    if (snippet) {
        snippet.hidden = true;
        snippet.innerHTML = '';
    }
});

document.addEventListener('DOMContentLoaded', () => {
    initialise();
    initToxicWordsPage();
});

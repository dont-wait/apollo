import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../resources/views/admin/articles/_form.blade.php', import.meta.url), 'utf8').split('<script>')[1].split('</script>')[0];

function createEditor() {
    const elements = new Map();
    let document;
    class Element {
        constructor(tag = 'div') {
            this.tagName = tag;
            this.children = [];
            this.listeners = {};
            this.attributes = {};
            this.dataset = {};
            this.style = {};
            this.hidden = false;
            this.value = '';
            this.selectionStart = this.selectionEnd = 0;
            this.scrollTop = this.scrollLeft = 0;
            this.clientWidth = 600;
            this.offsetWidth = 320;
            this.scrollHeight = 280;
        }
        addEventListener(type, handler) { (this.listeners[type] ??= []).push(handler); }
        dispatchEvent(event) {
            event.target ??= this;
            event.preventDefault ??= () => { event.defaultPrevented = true; };
            for (const handler of this.listeners[event.type] ?? []) { handler(event); }
            return !event.defaultPrevented;
        }
        setAttribute(name, value) { this.attributes[name] = String(value); }
        removeAttribute(name) { delete this.attributes[name]; }
        append(...items) { this.children.push(...items); }
        replaceChildren(...items) { this.children = items; }
        contains(target) { return this === target || this.children.some(child => child.contains(target)); }
        querySelectorAll(selector) {
            return this.children.flatMap(child => [...(selector === child.tagName ? [child] : []), ...child.querySelectorAll(selector)]);
        }
        querySelector(selector) { return this.querySelectorAll(selector)[0]; }
        focus() { document.activeElement = this; }
        select() {}
        scrollIntoView() {}
        remove() {}
        getBoundingClientRect() { return { left: 100, top: 100, bottom: 550, height: 280 }; }
        getClientRects() { return [{ left: 12, top: 12 }]; }
        setCustomValidity(message) { this.validationMessage = message; }
        reportValidity() {
            if (this.validationMessage) { return false; }
            if (this.type === 'number' && this.value !== '') {
                return Number.isInteger(Number(this.value)) && Number(this.value) >= Number(this.attributes.min) && Number(this.value) <= Number(this.attributes.max);
            }
            return this.type !== 'url' || this.value === '' || /^https?:\/\//.test(this.value);
        }
        setRangeText(text, start, end) {
            this.value = this.value.slice(0, start) + text + this.value.slice(end);
            this.selectionStart = this.selectionEnd = start + text.length;
        }
    }
    const get = selector => {
        if (!elements.has(selector)) { elements.set(selector, new Element()); }
        return elements.get(selector);
    };
    document = Object.assign(new Element(), {
        querySelector: get,
        querySelectorAll: () => [],
        createElement: tag => new Element(tag),
        body: new Element(),
    });
    get('[data-markdown-command-menu]').hidden = true;
    const input = get('#markdown_content');
    const menu = get('[data-markdown-command-menu]');
    const suggestions = get('[data-markdown-suggestions]');
    const fields = get('[data-markdown-command-fields]');
    const config = get('[data-markdown-command-config]');
    menu.append(suggestions, config);
    config.append(fields);
    runInNewContext(source, {
        document, window: Object.assign(new Element(), { innerWidth: 1000, innerHeight: 800 }),
        getComputedStyle: () => ({ borderLeftWidth: '1', borderRightWidth: '1', lineHeight: '24' }),
        Event: class { constructor(type) { this.type = type; } },
        Element, HTMLInputElement: Element,
    });
    const type = (value, cursor = value.length) => {
        input.focus();
        input.value = value;
        input.selectionStart = input.selectionEnd = cursor;
        input.dispatchEvent({ type: 'input' });
    };
    const key = key => input.dispatchEvent({ type: 'keydown', key });
    const configure = values => {
        for (const field of fields.querySelectorAll('input')) {
            if (field.dataset.commandField in values) { field.value = String(values[field.dataset.commandField]); }
        }
        get('[data-markdown-command-insert]').dispatchEvent({ type: 'click' });
    };
    return { get, document, input, menu, suggestions, config, fields, type, key, configure };
}

test('commands stay hidden until a slash token is typed and close for ordinary text and URLs', () => {
    const editor = createEditor();
    assert.equal(editor.menu.hidden, true);
    for (const text of ['ordinary text', 'https://example.com/path', 'path/to/file']) {
        editor.type(text);
        assert.equal(editor.menu.hidden, true);
    }
    editor.type('Hello /');
    assert.equal(editor.menu.hidden, false);
    assert.ok(editor.suggestions.children.length > 1);
    editor.type('Hello /missing');
    assert.equal(editor.get('[data-markdown-no-commands]').hidden, false);
    editor.type('Hello / ');
    assert.equal(editor.menu.hidden, true);
});

test('clicking the editor recalculates suggestions at the caret', () => {
    const editor = createEditor();
    editor.type('Hello /');
    assert.equal(editor.menu.hidden, false);

    editor.input.selectionStart = editor.input.selectionEnd = 3;
    editor.input.dispatchEvent({ type: 'click' });
    assert.equal(editor.menu.hidden, true);

    editor.input.selectionStart = editor.input.selectionEnd = 7;
    editor.input.dispatchEvent({ type: 'click' });
    assert.equal(editor.menu.hidden, false);
});

test('table selection opens configuration and inserts the chosen dimensions at the slash range', () => {
    const editor = createEditor();
    editor.type('Before /table\nAfter', 13);
    editor.key('Enter');
    assert.equal(editor.config.hidden, false);
    assert.equal(editor.suggestions.hidden, true);
    assert.equal(editor.input.value, 'Before /table\nAfter');

    editor.configure({ columns: 2, rows: 1 });

    assert.equal(editor.input.value, 'Before \n| Cột 1 | Cột 2 |\n| --- | --- |\n|   |   |\nAfter');
    assert.equal(editor.menu.hidden, true);
    assert.equal(editor.fields.children.length, 0);
});

test('Escape and cancel dismiss commands without changing Markdown or reopening on navigation', () => {
    const editor = createEditor();
    editor.type('/ta');
    editor.key('Escape');
    editor.input.dispatchEvent({ type: 'keyup', key: 'ArrowLeft' });
    assert.equal(editor.menu.hidden, true);
    assert.equal(editor.input.value, '/ta');
    editor.type('/table');
    editor.key('Enter');
    editor.get('[data-markdown-command-cancel]').dispatchEvent({ type: 'click' });
    assert.equal(editor.menu.hidden, true);
    assert.equal(editor.input.value, '/table');
});

test('simple commands insert directly using keyboard selection', () => {
    const editor = createEditor();
    editor.type('/h');
    editor.key('ArrowDown');
    editor.key('Tab');
    assert.equal(editor.input.value, '## Tiêu đề');
    assert.equal(editor.menu.hidden, true);
});

test('links, images and code use their contextual configuration', () => {
    for (const [command, values, expected] of [
        ['link', { text: 'A [link]', url: 'https://example.com' }, '[A \\[link\\]](<https://example.com>)'],
        ['image', { text: 'Picture', url: 'https://example.com/a.png' }, '![Picture](<https://example.com/a.png>)'],
        ['code', { language: 'php' }, '```php\ncode\n```\n'],
    ]) {
        const editor = createEditor();
        editor.type(`/${command}`);
        editor.suggestions.children.find(item => item.id === `markdown-command-${command}`).dispatchEvent({ type: 'click' });
        assert.equal(editor.config.hidden, false);
        editor.configure(values);
        assert.equal(editor.input.value, expected);
        assert.equal(editor.menu.hidden, true);
    }
});

test('invalid dimensions and missing URL keep the editor unchanged until corrected', () => {
    const editor = createEditor();
    editor.type('/table');
    editor.key('Enter');
    for (const columns of ['0', '13', '1.5', '']) {
        editor.configure({ columns });
        assert.equal(editor.input.value, '/table');
        assert.equal(editor.menu.hidden, false);
        editor.fields.querySelector('input').setCustomValidity('');
    }
    editor.configure({ columns: 1, rows: 1 });
    assert.equal(editor.menu.hidden, true);

    editor.type('/link');
    editor.key('Enter');
    editor.configure({ text: 'Example' });
    assert.equal(editor.input.value, '/link');
    editor.get('[data-markdown-command-cancel]').dispatchEvent({ type: 'click' });
    assert.equal(editor.fields.children.length, 0);
});

test('outside interaction and leaving the editor dismiss the menu', () => {
    const editor = createEditor();
    for (const type of ['pointerdown', 'focusin']) {
        editor.type('/');
        editor.document.dispatchEvent({ type, target: editor.get('#title') });
        assert.equal(editor.menu.hidden, true);
    }
});

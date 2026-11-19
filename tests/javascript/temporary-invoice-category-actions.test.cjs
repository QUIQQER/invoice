const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture() {
    const calls = [];
    const element = () => ({
        dataset: {},
        children: [],
        appendChild(child) { this.children.push(child); },
        setAttribute() {},
        replaceChildren() { this.children = []; }
    });
    class Button {
        constructor(options) { this.options = options; this.element = element(); }
        inject(target) { target.appendChild(this.element); return this; }
        getElm() { return this.element; }
        destroy() { this.destroyed = true; }
    }
    let definition;
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/panels/TemporaryInvoice.js'), 'utf8'), {
        Class: function(value) { return value; },
        define(name, names, factory) {
            definition = factory(...names.map(dependency => {
                if (dependency === 'qui/controls/buttons/Button') return Button;
                if (dependency === 'Locale') return {get: (group, key) => key};
                return {};
            }));
        },
        document: {createElement: element},
        moofx: () => ({animate(values, options) { options?.callback?.(); }}),
        console
    });
    const container = {set() {}, setStyles() {}, scrollTop: 80};
    const content = {getElement: () => container, setStyle() {}};
    const panel = Object.assign({}, definition, {
        $CategoryActions: element(),
        $CategorySummary: element(),
        getContent: () => content,
        $unloadCategory() { calls.push('reserve'); },
        save() { calls.push('save'); return Promise.resolve(); },
        openData() { calls.push('data'); },
        openArticles() { calls.push('articles'); return Promise.resolve(); },
        openVerification() { calls.push('verification'); }
    });
    return {panel, calls, container};
}

test('article footer keeps the existing previous and verification destinations', () => {
    const {panel, calls} = fixture();
    panel.$setCategoryActions('articles');
    const buttons = panel.$categoryButtons;
    assert.equal(buttons.length, 2);
    assert.equal(buttons[0].getElm().dataset.name, 'previous-category');
    assert.equal(buttons[1].getElm().dataset.name, 'next-category');
    assert.equal(buttons[0].options.text, 'erp.panel.temporary.invoice.button.data');
    assert.equal(buttons[1].options.text, 'erp.panel.temporary.invoice.category.review.btnGoto');
    buttons[0].options.events.onClick();
    buttons[1].options.events.onClick();
    assert.deepEqual(calls, ['data', 'verification']);
    assert.equal(panel.$CategoryActions.hidden, false);
});

test('data footer keeps the existing article destination and replaces old controls', async () => {
    const {panel, calls} = fixture();
    panel.$setCategoryActions('articles');
    const old = panel.$categoryButtons.slice();
    panel.$setCategoryActions('data');
    assert.ok(old.every(button => button.destroyed));
    assert.equal(panel.$categoryButtons.length, 1);
    panel.$categoryButtons[0].options.events.onClick();
    await Promise.resolve();
    assert.deepEqual(calls, ['articles']);
    assert.equal(panel.$CategoryActions.children.length, 1);
});

test('leaving a category destroys the summary and buttons before reserving and saving', async () => {
    const {panel, calls, container} = fixture();
    panel.$setCategoryActions('articles');
    const old = panel.$categoryButtons.slice();
    panel.$ArticleListSummary = {destroy() { calls.push('destroy-summary'); }};
    const result = await panel.$closeCategory();
    assert.equal(result, container);
    assert.deepEqual(calls, ['destroy-summary', 'reserve', 'save']);
    assert.ok(old.every(button => button.destroyed));
    assert.equal(panel.$CategoryActions.hidden, true);
    assert.equal(panel.$CategoryActions.children.length, 0);
    assert.equal(panel.$ArticleListSummary, null);
    assert.equal(container.scrollTop, 0);
});

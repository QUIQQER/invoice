const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture() {
    let definition;
    const currencyCallbacks = [];
    const toolbar = [];
    const element = () => ({
        children: [],
        attributes: {},
        style: {removeProperty() {}},
        classList: {add() {}, toggle() {}},
        appendChild(child) { this.children.push(child); },
        setAttribute(name, value) { this.attributes[name] = value; },
        getBoundingClientRect: () => ({right: 1200, top: 120, bottom: 150, width: 200, height: 180})
    });

    class Button {
        constructor(options) {
            this.options = options;
            this.element = element();
            this.items = [];
            this.menu = {
                element: element(),
                events: {},
                getElm() { return this.element; },
                setAttribute() {},
                addEvents(events) { Object.assign(this.events, events); },
                setPosition(left, top) { this.position = [left, top]; }
            };
        }
        getElm() { return this.element; }
        getAttribute(name) { return this.options[name]; }
        setAttribute(name, value) { this.options[name] = value; }
        appendChild(item) { this.items.push(item); }
        getChildren() { return this.items; }
        getContextMenu(callback) { callback(this.menu); }
        disable() { this.options.disabled = true; }
        enable() { this.options.disabled = false; }
    }

    const dependencies = {
        'package/quiqqer/invoice/bin/Invoices': {
            getTemporaryInvoicesList: () => Promise.resolve({data: [], total: 0})
        },
        'qui/controls/buttons/Button': Button,
        'qui/controls/contextmenu/Item': function(options) { Object.assign(this, options); },
        'controls/grid/Grid': class {
            constructor(container, options) { this.options = options; }
            addEvents() {}
            setData() {}
            getButtons() { return this.options.buttons; }
            getButton(name) {
                return this.options.buttons.find(Button => Button.getAttribute('name') === name);
            }
        },
        Locale: {get: (group, key) => key},
        Ajax: {get: (names, callback) => currencyCallbacks.push(callback)}
    };
    vm.runInNewContext(readFileSync(path.join(__dirname, '../../bin/backend/controls/panels/TemporaryInvoices.js'), 'utf8'), {
        Class: function(options) { return options; },
        Element: function() { this.inject = () => this; },
        document: {createElement: element},
        window: {innerWidth: 1280, innerHeight: 720, scrollX: 0, scrollY: 0},
        define(name, names, factory) {
            definition = factory(...names.map(name => dependencies[name] ?? {}));
        }
    });
    const root = element();
    const panel = Object.assign({}, definition, {
        addButton: Button => toolbar.push(Button),
        Loader: {show() {}, hide() {}},
        getElm: () => root,
        getContent: () => ({setStyles() {}}),
        refresh() {}
    });
    panel.$onCreate();
    return {panel, currencyCallbacks, toolbar, refreshPanel: definition.refresh};
}

test('draft currency is in the upper toolbar and Actions stays on the right of the grid', () => {
    const {panel, toolbar} = fixture();
    const buttons = Array.from(panel.$Grid.options.buttons);
    assert.deepEqual(toolbar, [panel.$Currency]);
    assert.equal(buttons[0].name, 'create');
    assert.equal(buttons.includes(panel.$Currency), false);
    assert.equal(panel.$Currency.getAttribute('dropDownIcon'), false);
    assert.equal(panel.$Currency.getElm().children.length, 1);
    assert.equal(buttons[1].name, 'pdf');
    assert.equal(buttons[2].getAttribute('name'), 'actions');
    assert.equal(buttons[2].getAttribute('position'), 'right');
});

test('draft currency remains visible with the default value when changing it is unavailable', () => {
    const eur = {code: 'EUR', sign: '€'};
    const usd = {code: 'USD', sign: '$'};
    for (const currencies of [[], [eur], [eur, usd]]) {
        const {panel, currencyCallbacks, toolbar} = fixture();
        const Currency = panel.$Currency;
        panel.$onInject();
        currencyCallbacks[0](currencies, eur);

        assert.equal(Currency.getAttribute('text'), 'EUR');
        assert.equal(Currency.getAttribute('value'), 'EUR');
        assert.equal(Currency.getAttribute('disabled'), currencies.length <= 1);
        assert.equal(Currency.items.length, currencies.length > 1 ? 3 : 0);
        assert.equal(toolbar[0], Currency);
        if (currencies.length > 1) {
            assert.equal(Currency.items[1].value, 'USD');
            assert.equal(Currency.items[2].value, '');
        }
    }
});

test('draft actions align below the button, open above near the edge and expose open state', () => {
    const {panel} = fixture();
    const Actions = panel.$Grid.options.buttons[2];
    const Menu = Actions.menu;
    Menu.events.onShow();
    assert.deepEqual(Menu.position, [1000, 158]);
    assert.equal(Actions.getElm().attributes['aria-expanded'], 'true');

    Actions.element.getBoundingClientRect = () => ({right: 1200, top: 650, bottom: 680});
    Menu.events.onShow();
    assert.deepEqual(Menu.position, [1000, 462]);
    Menu.events.onHide();
    assert.equal(Actions.getElm().attributes['aria-expanded'], 'false');
});

test('refresh disables PDF and selection actions without disabling Create invoice', async () => {
    const {panel, refreshPanel} = fixture();
    const buttons = panel.$Grid.options.buttons;
    const disabled = new Set();
    const makeButton = name => ({
        getAttribute: () => name,
        disable: () => disabled.add(name)
    });
    const Create = makeButton('create');
    const PDF = makeButton('pdf');
    const Actions = buttons[2];
    Actions.getChildren = () => ['post', 'copy', 'delete'].map(makeButton);
    panel.$Grid.options.buttons = [Create, PDF, Actions];

    await refreshPanel.call(panel);
    assert.deepEqual(disabled, new Set(['pdf', 'copy', 'delete', 'post']));
    assert.equal(disabled.has('create'), false);
});

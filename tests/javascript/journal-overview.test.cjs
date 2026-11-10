const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const source = readFileSync(path.join(__dirname, '../../bin/backend/controls/panels/Journal.js'), 'utf8');

function element() {
    const classes = new Set();

    return {
        hidden: true,
        innerHTML: '',
        offsetHeight: 90,
        attributes: {},
        style: {},
        getBoundingClientRect: () => ({height: 90}),
        classList: {
            add(name) { classes.add(name); },
            remove(name) { classes.delete(name); },
            contains(name) { return classes.has(name); }
        },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        replaceChildren() { this.innerHTML = ''; }
    };
}

function fixture({motion = false, reducedMotion = false} = {}) {
    let definition;
    const requests = [];
    const renders = [];
    const errors = [];
    const animations = [];
    const timers = new Map();
    const dependencies = {
        'package/quiqqer/invoice/bin/Invoices': {
            search(params, filters) {
                return new Promise((resolve, reject) => requests.push({params, filters, resolve, reject}));
            },
            removeEvents() {}
        },
        Locale: {get: (group, key) => key},
        Mustache: {
            render(template, data) {
                renders.push(data);
                return 'Rendered totals';
            }
        }
    };

    vm.runInNewContext(source, {
        Class: function(options) { return options; },
        define(name, names, factory) {
            definition = factory(...names.map(name => dependencies[name] ?? {}));
        },
        document: {createElement: element},
        CSS: {supports: () => false},
        console: {error: error => errors.push(error)},
        window: {matchMedia: () => ({matches: reducedMotion})},
        setTimeout(callback) {
            const id = timers.size + 1;
            timers.set(id, callback);
            return id;
        },
        clearTimeout(id) { timers.delete(id); }
    });

    const buttonElement = element();
    const button = {setActive() {}, setNormal() {}, getElm: () => buttonElement};
    const gridData = [];
    const heights = [];
    const panel = Object.assign({}, definition, {
        $refreshId: 0,
        $totalsOpen: false,
        $destroyed: false,
        $Total: element(),
        $Search: {value: ''},
        $currentSearch: '',
        $Currency: {getAttribute: () => 'EUR'},
        $Status: {getValue: () => '0'},
        $TimeFilter: {$Select: {$placeholderText: 'November'}, getValue: () => ({from: 1, to: 2})},
        $Grid: {
            options: {page: 4, perPage: 10, sortOn: 'id', sortBy: 'DESC'},
            setAttribute() {},
            setData: data => gridData.push(data),
            setHeight: height => heights.push(height),
            setWidth() {}
        },
        Loader: {visible: false, show() { this.visible = true; }, hide() { this.visible = false; }},
        getButtons: () => button,
        getContent: () => ({getSize: () => ({x: 1420, y: 600})}),
        $refreshButtonStatus() {},
        disableFilter() {},
        enableFilter() {}
    });

    if (motion) {
        panel.$Total.animate = (frames, options) => {
            let finish;
            let cancel;
            const animation = {
                frames,
                options,
                finished: new Promise((resolve, reject) => {
                    finish = resolve;
                    cancel = reject;
                }),
                finish: () => finish(),
                cancel: () => cancel(new Error('Animation cancelled'))
            };
            animations.push(animation);
            return animation;
        };
    }

    return {panel, requests, renders, buttonElement, gridData, heights, errors, timers, animations};
}

function response(prefix = 'current') {
    const total = {};
    for (const field of ['netto', 'vat', 'brutto']) {
        for (const group of ['toPay', 'paid', 'total']) {
            total[`display_${field}_${group}`] = `${prefix}-${field}-${group}`;
        }
    }
    return {grid: {data: [], total: 176}, total};
}

test('the hidden overview requests only rows and count, with the current filters', async () => {
    const {panel, requests, renders} = fixture();
    const pending = panel.refresh();
    assert.equal(requests[0].params.calcTotal, 0);
    assert.deepEqual({...requests[0].filters, paid_status: Array.from(requests[0].filters.paid_status)}, {from: 1, to: 2, search: '', currency: 'EUR', paid_status: ['0']});
    requests[0].resolve({grid: {data: [], total: 0}, total: null});
    await pending;
    assert.equal(renders.length, 0);
    assert.equal(panel.$Total.hidden, true);
    assert.equal(panel.Loader.visible, false);
});

test('the calculator fetches and displays all nine values above the grid', async () => {
    const {panel, requests, renders, buttonElement, heights} = fixture();
    const pending = panel.showTotal();
    assert.equal(requests[0].params.calcTotal, 1);
    assert.equal(buttonElement.attributes['aria-pressed'], 'true');
    assert.equal(panel.$Total.attributes['aria-busy'], 'true');
    requests[0].resolve(response());
    await pending;

    assert.equal(panel.$Total.hidden, false);
    assert.equal(panel.$Total.attributes['aria-busy'], undefined);
    assert.equal(heights.at(-1), 490);
    assert.equal(renders[0].count, 176);
    assert.equal(renders[0].currency, 'EUR');
    assert.equal(renders[0].groups.length, 3);
    assert.equal(new Set(renders[0].groups.flatMap(group => group.metrics.map(metric => metric.value))).size, 9);
    assert.equal(renders[0].groups[0].metrics[0].value, 'current-netto-toPay');
    assert.equal(renders[0].groups[2].metrics[2].value, 'current-brutto-total');
    assert.equal(renders[0].groups.flatMap(group => group.metrics).filter(metric => metric.gross).length, 3);
});

test('hiding totals restores grid space and later refreshes skip their calculation', async () => {
    const {panel, requests, buttonElement, heights} = fixture();
    panel.$totalsOpen = true;
    await panel.hideTotal();
    assert.equal(panel.$Total.hidden, true);
    assert.equal(buttonElement.attributes['aria-pressed'], 'false');
    assert.equal(heights.at(-1), 580);
    assert.equal(requests.length, 0);

    const pending = panel.refresh();
    assert.equal(requests[0].params.calcTotal, 0);
    requests[0].resolve({grid: {data: [], total: 0}, total: null});
    await pending;
});

test('a calculation finishing after hide cannot reopen the totals', async () => {
    const {panel, requests, renders} = fixture();
    const pending = panel.showTotal();
    await panel.hideTotal();
    requests[0].resolve(response());
    await pending;
    assert.equal(panel.$Total.hidden, true);
    assert.equal(renders.length, 0);
});

test('outdated responses cannot replace the latest filtered rows or totals', async () => {
    const {panel, requests, renders, gridData} = fixture();
    const old = panel.showTotal();
    panel.$Search.value = ' newest ';
    const latest = panel.search();
    assert.equal(requests[1].params.page, 1);
    assert.equal(requests[1].params.calcTotal, 1);
    assert.equal(requests[1].filters.search, 'newest');
    assert.equal(requests[1].filters.from, '');
    assert.equal(requests[1].filters.paid_status[0], '');
    requests[1].resolve(response('latest'));
    await latest;
    requests[0].resolve(response('old'));
    await old;
    assert.equal(gridData.length, 1);
    assert.equal(renders.length, 1);
    assert.equal(renders[0].groups[0].metrics[0].value, 'latest-netto-toPay');
});

test('a failed current calculation clears loading and lets the calculator retry', async () => {
    const {panel, requests, errors, buttonElement} = fixture();
    const pending = panel.showTotal();
    requests[0].reject(new Error('Calculation failed'));
    await pending;
    assert.equal(panel.Loader.visible, false);
    assert.equal(panel.$totalsOpen, false);
    assert.equal(buttonElement.attributes['aria-pressed'], 'false');
    assert.equal(errors.length, 1);

    const retry = panel.showTotal();
    requests[1].resolve(response());
    await retry;
    assert.equal(panel.$Total.hidden, false);
});

test('closing the panel ignores pending responses and cancels delayed searches', async () => {
    const {panel, requests, renders, gridData, timers} = fixture();
    const pending = panel.showTotal();
    panel.$Search.value = '';
    panel.$currentSearch = 'old search';
    panel.$onSearchKeyUp({type: 'input'});
    assert.equal(timers.size, 1);
    panel.$onDestroy();
    assert.equal(timers.size, 0);
    requests[0].resolve(response());
    await pending;
    assert.equal(renders.length, 0);
    assert.equal(gridData.length, 0);
    await panel.refresh();
    assert.equal(requests.length, 1);
});

test('Enter and clearing search trigger a paginated search, while normal typing waits', async () => {
    const {panel, requests, timers} = fixture();
    panel.$Search.value = 'customer';
    panel.$onSearchKeyUp({key: 'r', type: 'keyup'});
    assert.equal(timers.size, 0);
    panel.$onSearchKeyUp({key: 'Enter', type: 'keyup'});
    assert.equal(timers.size, 1);
    [...timers.values()][0]();
    assert.equal(requests[0].params.page, 1);
    assert.equal(requests[0].filters.search, 'customer');
    requests[0].resolve({grid: {data: [], total: 0}, total: null});
    await Promise.resolve();

    panel.$Search.value = '';
    panel.$onSearchKeyUp({type: 'input'});
    assert.equal(timers.size, 1);
});

test('loaded totals expand smoothly and stay in flow until collapsing finishes', async () => {
    const {panel, requests, animations} = fixture({motion: true});
    const loading = panel.showTotal();
    assert.equal(panel.$Total.hidden, true);
    assert.equal(animations.length, 0);
    requests[0].resolve(response());
    await loading;
    assert.equal(animations[0].frames[0].height, '0px');
    assert.equal(animations[0].frames[1].height, '90px');
    assert.equal(animations[0].options.duration, 200);
    animations[0].finish();
    await animations[0].finished;
    await Promise.resolve();

    const hiding = panel.hideTotal();
    assert.equal(panel.$Total.hidden, false);
    assert.equal(animations[1].frames[1].height, '0px');
    animations[1].finish();
    await hiding;
    assert.equal(panel.$Total.hidden, true);
    assert.equal(panel.$Total.style.overflow, '');
});

test('reduced motion shows and hides totals without starting animations', async () => {
    const {panel, requests, animations} = fixture({motion: true, reducedMotion: true});
    const loading = panel.showTotal();
    requests[0].resolve(response());
    await loading;
    assert.equal(panel.$Total.hidden, false);
    await panel.hideTotal();
    assert.equal(panel.$Total.hidden, true);
    assert.equal(animations.length, 0);
});

test('an interrupted opening animation cannot reopen a hidden totals area', async () => {
    const {panel, requests, animations} = fixture({motion: true});
    const loading = panel.showTotal();
    requests[0].resolve(response());
    await loading;
    const hiding = panel.hideTotal();
    animations[1].finish();
    await hiding;
    assert.equal(panel.$Total.hidden, true);
    assert.equal(panel.$totalsAnimation, null);
});

test('totals content stays hidden until both expansion and grid resizing have finished', async () => {
    const {panel, requests, animations} = fixture({motion: true});
    let finishResize;
    const resized = new Promise(resolve => { finishResize = resolve; });
    panel.$Grid.setHeight = () => resized;

    const loading = panel.showTotal();
    requests[0].resolve(response());
    await loading;
    assert.equal(panel.$Total.classList.contains('quiqqer-invoice-totals--resizing'), true);
    animations[0].finish();
    await animations[0].finished;
    await Promise.resolve();
    assert.equal(panel.$Total.classList.contains('quiqqer-invoice-totals--resizing'), true);

    finishResize();
    await resized;
    await new Promise(setImmediate);
    assert.equal(panel.$Total.classList.contains('quiqqer-invoice-totals--resizing'), false);
});

test('collapse holds its zero-height end state while waiting for the grid width', async () => {
    const {panel, animations} = fixture({motion: true});
    panel.$totalsOpen = true;
    panel.$Total.hidden = false;
    let finishWidth;
    panel.$Grid.setWidth = () => new Promise(resolve => { finishWidth = resolve; });

    const hiding = panel.hideTotal();
    const animation = animations[0];
    animation.finish();
    await animation.finished;
    await new Promise(setImmediate);

    assert.equal(panel.$Total.hidden, false);
    assert.equal(animation.frames.at(-1).height, '0px');
    assert.equal(animation.options.fill, 'forwards');
    assert.equal(panel.$totalsAnimation, animation);

    finishWidth();
    await hiding;
    assert.equal(panel.$Total.hidden, true);
    assert.equal(panel.$totalsAnimation, null);
    assert.equal(panel.$Total.classList.contains('quiqqer-invoice-totals--resizing'), false);
});

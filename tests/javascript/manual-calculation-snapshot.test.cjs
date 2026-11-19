const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture(restored = Promise.resolve(), loaded = snapshot) {
    let definition;
    const calls = [];
    const Box = function() {
        this.inject = () => this;
        this.getElm = () => ({});
    };
    const List = function() {
        Box.call(this);
        this.setUser = () => {};
        this.unserialize = () => { calls.push('restore'); return restored; };
    };
    const api = {getTemporaryInvoice: () => Promise.resolve({articles: loaded})};
    const modules = {
        Locale: {get: (group, key) => key},
        'qui/controls/buttons/Button': Box,
        'package/quiqqer/invoice/bin/Invoices': api
    };
    vm.runInNewContext(readFileSync(resolve(__dirname, '../../bin/backend/controls/panels/TemporaryInvoice.js'), 'utf8'), {
        Class: function(value) { return value; },
        define(name, names, factory) { definition = factory(...names.map(name => modules[name] || {})); },
        require(names, callback) { callback(List, Box); },
        moofx: () => ({animate() {}})
    });
    const attributes = {articles: [{title: 'legacy'}], priceFactors: []};
    const container = {style: {}, setStyle() {}, getParent: () => ({})};
    const control = {show() {}};
    const panel = {...definition, $serializedList: {}, $ArticleList: null,
        getAttribute: key => attributes[key],
        setAttribute(key, value) { attributes[key] = value; },
        setAttributes(values) { Object.assign(attributes, values); },
        refresh() { calls.push('refresh'); },
        $closeCategory: () => Promise.resolve(container),
        $openCategory: () => { calls.push('open'); return Promise.resolve(); },
        getCategory: () => ({setActive() {}}),
        $AddProduct: control, $AddSeparator: control, $SortSeparator: control, $ArticleSort: control,
        Loader: {show() { calls.push('show'); }, hide() { calls.push('hide'); return Promise.resolve(); }}};
    return {panel, attributes, calls};
}

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return {promise, resolve, reject};
}

async function settle() {
    for (let i = 0; i < 8; i++) await Promise.resolve();
}

const snapshot = {
    articles: [{unitPrice: 8.395, quantity: 1, calculated: {nettoSum: 8.395, vatArray: {sum: 1.5951}}}],
    calculationMode: 'manual',
    calculations: {sum: 9.99, grandSubSum: 9.9901, nettoSum: 8.395},
    priceFactors: [{sum: 0.12345}]
};

test('active article controls save the full live manual snapshot', () => {
    const {panel} = fixture();
    panel.$serializedList = {...snapshot, calculations: {sum: 999}};
    panel.$ArticleList = {getCalculationMode: () => 'manual', serialize: () => snapshot};
    assert.equal(panel.getCurrentData().articles, snapshot);
});

test('switching categories retains the saved policy, amounts and factors', () => {
    const {panel} = fixture();
    panel.$serializedList = snapshot;
    assert.equal(panel.getCurrentData().articles, snapshot);
    assert.equal(panel.getCurrentData().articles.calculations.grandSubSum, 9.9901);
});

test('legacy position arrays remain usable without fabricating a manual policy', () => {
    const {panel, attributes} = fixture();
    assert.equal(panel.getCurrentData().articles, attributes.articles);
    assert.equal(panel.getCurrentData().articles.calculationMode, undefined);
});

test('empty manual documents retain their independent totals', () => {
    const {panel} = fixture();
    panel.$serializedList = {...snapshot, articles: []};
    assert.equal(panel.getCurrentData().articles.calculationMode, 'manual');
    assert.equal(panel.getCurrentData().articles.calculations.sum, 9.99);
});

test('opening articles waits for asynchronous restoration before hiding the loader or resolving', async () => {
    const pending = deferred();
    const {panel, calls} = fixture(pending.promise);
    panel.$serializedList = snapshot;
    let complete = false;
    const opening = panel.openArticles().then(() => { complete = true; });
    await settle();
    assert.ok(calls.includes('restore'));
    assert.equal(calls.includes('hide'), false);
    assert.equal(calls.includes('open'), false);
    assert.equal(complete, false);
    pending.resolve();
    await opening;
    assert.ok(calls.indexOf('hide') > calls.indexOf('restore'));
    assert.ok(calls.indexOf('open') > calls.indexOf('hide'));
    assert.equal(complete, true);
});

test('restoration failure rejects opening instead of reporting an empty list as ready', async () => {
    const pending = deferred();
    const {panel, calls} = fixture(pending.promise);
    panel.$serializedList = snapshot;
    const opening = panel.openArticles();
    await settle();
    const error = new Error('Unable to restore article');
    pending.reject(error);
    await assert.rejects(opening, failure => failure === error);
    assert.equal(calls.includes('hide'), false);
    assert.equal(calls.includes('open'), false);
});

test('refreshing an existing article list waits for its restored snapshot', async () => {
    const pending = deferred();
    const {panel, calls} = fixture();
    panel.$ArticleList = {unserialize: () => pending.promise};
    let complete = false;
    const refreshing = panel.doRefresh().then(() => { complete = true; });
    await settle();
    assert.equal(complete, false);
    assert.equal(calls.includes('refresh'), false);
    pending.resolve();
    await refreshing;
    assert.equal(complete, true);
    assert.equal(calls.includes('refresh'), true);
});

test('automatic panels keep legacy positions and separate factors instead of serializing metadata', () => {
    const {panel, attributes} = fixture();
    panel.$serializedList = {...snapshot, calculationMode: 'automatic'};
    panel.$ArticleList = {
        getCalculationMode: () => 'automatic',
        serialize() { throw new Error('Automatic save must use its historical position payload'); }
    };
    const data = panel.getCurrentData();
    assert.equal(data.articles, attributes.articles);
    assert.equal(Array.isArray(data.articles), true);
    assert.equal(data.articles.calculationMode, undefined);
    assert.ok('priceFactors' in data);
});

test('automatic panels retain the historical opening completion while positions restore asynchronously', async () => {
    const pending = deferred();
    const {panel, calls} = fixture(pending.promise);
    panel.$serializedList = {...snapshot, calculationMode: 'automatic'};
    await panel.openArticles();
    assert.ok(calls.includes('hide'));
    assert.ok(calls.includes('open'));
    pending.resolve();
});

test('automatic refresh retains its historical refresh timing', async () => {
    const pending = deferred();
    const {panel, calls} = fixture(Promise.resolve(), {...snapshot, calculationMode: 'automatic'});
    panel.$ArticleList = {unserialize: () => pending.promise};
    await panel.doRefresh();
    assert.ok(calls.includes('refresh'));
    pending.resolve();
});

test('unopened automatic or legacy snapshots keep the historical position-array save contract', () => {
    const {panel, attributes} = fixture();
    for (const calculationMode of ['automatic', undefined]) {
        panel.$serializedList = {...snapshot, calculationMode};
        const data = panel.getCurrentData();
        assert.equal(data.articles, attributes.articles);
        assert.equal(Array.isArray(data.articles), true);
        assert.ok('priceFactors' in data);
    }
});

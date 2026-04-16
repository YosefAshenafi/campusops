/**
 * Jest setup: provide a minimal layui stub so the Layui module pattern
 * (layui.define([deps], factory)) can be loaded in Node/jsdom.
 *
 * The stub captures the factory return and makes it available via
 * window.__layuiModules[name] for test assertions.
 */

window.__layuiModules = {};

window.layui = {
    define: function (deps, factory) {
        var captured = {};
        function exporter(name, obj) {
            captured[name] = obj;
            window.__layuiModules[name] = obj;
        }
        factory(exporter);
        return captured;
    },
    // Minimal stubs used by modules
    jquery: {
        ajax: jest.fn(),
        extend: function (deep, target, src) {
            return Object.assign({}, target, src);
        }
    },
    layer: {
        msg: jest.fn()
    }
};

// Provide localStorage backed by an in-memory store
const _store = {};
Object.defineProperty(window, 'localStorage', {
    value: {
        getItem:    function (k) { return _store[k] !== undefined ? _store[k] : null; },
        setItem:    function (k, v) { _store[k] = String(v); },
        removeItem: function (k) { delete _store[k]; },
        clear:      function () { Object.keys(_store).forEach(k => delete _store[k]); }
    },
    writable: true
});

// Provide a minimal CampusOps config global used by the request() helper
window.CampusOps = {
    config: { apiBase: 'http://localhost:8080/api/v1' }
};

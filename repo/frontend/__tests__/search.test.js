/**
 * Unit tests for src/modules/search.js
 *
 * Covers:
 *   - escapeHtml() — HTML entity escaping to prevent XSS
 *   - suggest()    — skips API call when query is too short
 */

// Stub for common dependency
window.__layuiModules['common'] = window.__layuiModules['common'] || {
    getToken: () => null,
    getUser: () => ({}),
    request: jest.fn(),
    uuid: () => '00000000-0000-4000-8000-000000000000',
    formatDateTime: (s) => s || '',
    formatDate: (s) => s || ''
};
window.layui.common = window.__layuiModules['common'];

require('../src/modules/search');

const search = window.__layuiModules['search'];

// ============================================================
// escapeHtml
// ============================================================

describe('search.escapeHtml', () => {
    test('returns empty string for falsy input', () => {
        expect(search.escapeHtml(null)).toBe('');
        expect(search.escapeHtml('')).toBe('');
        expect(search.escapeHtml(undefined)).toBe('');
    });

    test('escapes < and > characters', () => {
        const result = search.escapeHtml('<script>alert(1)</script>');
        expect(result).not.toContain('<script>');
        expect(result).not.toContain('</script>');
        expect(result).toContain('&lt;');
        expect(result).toContain('&gt;');
    });

    test('escapes & character', () => {
        const result = search.escapeHtml('Tom & Jerry');
        expect(result).toContain('&amp;');
    });

    test('does not double-escape already safe text', () => {
        const safe = 'Hello World 123';
        expect(search.escapeHtml(safe)).toBe(safe);
    });

    test('escapes quotes', () => {
        const result = search.escapeHtml('"quoted"');
        expect(result).not.toContain('"quoted"');
    });

    test('escapes XSS payload', () => {
        const payload = '"><img src=x onerror=alert(1)>';
        const escaped = search.escapeHtml(payload);
        expect(escaped).not.toContain('<img');
        expect(escaped).not.toContain('onerror');
    });
});

// ============================================================
// suggest — short-circuit for short queries
// ============================================================

describe('search.suggest — short-circuit logic', () => {
    beforeEach(() => {
        // Reset mock call count
        window.layui.common.request.mockClear();

        // Provide a stub DOM element that suggest() manipulates
        document.body.innerHTML = '<div id="search-suggestions"></div>';
    });

    test('does not call common.request when query length < 2', () => {
        search.suggest('a');
        expect(window.layui.common.request).not.toHaveBeenCalled();
    });

    test('does not call common.request for empty query', () => {
        search.suggest('');
        expect(window.layui.common.request).not.toHaveBeenCalled();
    });

    test('calls common.request when query length >= 2', () => {
        search.suggest('te');
        expect(window.layui.common.request).toHaveBeenCalledTimes(1);
        const callArg = window.layui.common.request.mock.calls[0][0];
        expect(callArg.url).toContain('/search/suggest');
        expect(callArg.url).toContain('te');
    });
});

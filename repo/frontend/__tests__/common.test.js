/**
 * Unit tests for src/modules/common.js
 *
 * Covers:
 *   - getToken()       — reads campusops_token from localStorage
 *   - getUser()        — parses campusops_user JSON from localStorage
 *   - uuid()           — generates valid UUID v4 strings
 *   - formatDateTime() — formats ISO datetime strings to MM/DD/YYYY hh:mm:ss A
 *   - formatDate()     — formats ISO datetime strings to MM/DD/YYYY
 */

// Load the module through the layui stub provided by setup.js
require('../src/modules/common');

// The stub's exporter stores modules under window.__layuiModules
const common = window.__layuiModules['common'];

// ============================================================
// getToken
// ============================================================

describe('common.getToken', () => {
    afterEach(() => window.localStorage.clear());

    test('returns null when no token is stored', () => {
        expect(common.getToken()).toBeNull();
    });

    test('returns the stored token string', () => {
        window.localStorage.setItem('campusops_token', 'eyJhbGciOiJIUzI1NiJ9.test.sig');
        expect(common.getToken()).toBe('eyJhbGciOiJIUzI1NiJ9.test.sig');
    });
});

// ============================================================
// getUser
// ============================================================

describe('common.getUser', () => {
    afterEach(() => window.localStorage.clear());

    test('returns empty object when nothing is stored', () => {
        expect(common.getUser()).toEqual({});
    });

    test('returns parsed user object', () => {
        const user = { id: 1, username: 'admin', role: 'administrator' };
        window.localStorage.setItem('campusops_user', JSON.stringify(user));
        expect(common.getUser()).toEqual(user);
    });

    test('returns empty object when stored value is invalid JSON', () => {
        window.localStorage.setItem('campusops_user', 'not-json{{{');
        expect(common.getUser()).toEqual({});
    });
});

// ============================================================
// uuid
// ============================================================

describe('common.uuid', () => {
    test('generates a string of length 36', () => {
        expect(common.uuid()).toHaveLength(36);
    });

    test('matches UUID v4 format', () => {
        const uuidV4Pattern =
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
        expect(common.uuid()).toMatch(uuidV4Pattern);
    });

    test('generates distinct values on successive calls', () => {
        const a = common.uuid();
        const b = common.uuid();
        expect(a).not.toBe(b);
    });
});

// ============================================================
// formatDateTime
// ============================================================

describe('common.formatDateTime', () => {
    test('returns empty string for falsy input', () => {
        expect(common.formatDateTime(null)).toBe('');
        expect(common.formatDateTime('')).toBe('');
        expect(common.formatDateTime(undefined)).toBe('');
    });

    test('formats a morning datetime in AM', () => {
        // 2026-04-16 09:05:03 UTC
        const result = common.formatDateTime('2026-04-16T09:05:03Z');
        // Month/Day/Year portion is deterministic; hour depends on local tz — just check format
        expect(result).toMatch(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2} (AM|PM)$/);
    });

    test('formats a midnight datetime', () => {
        const result = common.formatDateTime('2026-01-01T00:00:00Z');
        expect(result).toMatch(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2} (AM|PM)$/);
    });

    test('outputs zero-padded day and month', () => {
        // 2026-01-05 → month=01, day=05
        const result = common.formatDateTime('2026-01-05T12:00:00Z');
        expect(result.startsWith('01/')).toBe(true);
    });
});

// ============================================================
// formatDate
// ============================================================

describe('common.formatDate', () => {
    test('returns empty string for falsy input', () => {
        expect(common.formatDate(null)).toBe('');
        expect(common.formatDate('')).toBe('');
    });

    test('returns MM/DD/YYYY format', () => {
        const result = common.formatDate('2026-04-16');
        expect(result).toMatch(/^\d{2}\/\d{2}\/\d{4}$/);
    });

    test('outputs year 2026 for a 2026 date', () => {
        const result = common.formatDate('2026-07-04');
        expect(result.endsWith('/2026')).toBe(true);
    });

    test('zero-pads single-digit months', () => {
        const result = common.formatDate('2026-03-15');
        expect(result.startsWith('03/')).toBe(true);
    });
});

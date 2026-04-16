/**
 * Unit tests for src/modules/orders.js
 *
 * Covers:
 *   - getStateBadge() — returns correct badge HTML for each known order state
 */

// Provide a stub for common module (used inside orders.js)
window.__layuiModules['common'] = {
    getToken: () => null,
    getUser: () => ({}),
    request: jest.fn(),
    uuid: () => '00000000-0000-4000-8000-000000000000',
    formatDateTime: (s) => s || '',
    formatDate: (s) => s || ''
};
window.layui.common = window.__layuiModules['common'];
// Stub form (used in some callbacks)
window.layui.form = { render: jest.fn(), on: jest.fn() };

require('../src/modules/orders');

const orders = window.__layuiModules['orders'];

// ============================================================
// getStateBadge
// ============================================================

describe('orders.getStateBadge', () => {
    test('returns HTML string for "placed" state', () => {
        const html = orders.getStateBadge('placed');
        expect(html).toContain('Placed');
        expect(html).toContain('layui-bg-blue');
    });

    test('returns HTML string for "pending_payment" state', () => {
        const html = orders.getStateBadge('pending_payment');
        expect(html).toContain('Pending Payment');
        expect(html).toContain('layui-bg-orange');
    });

    test('returns HTML string for "paid" state', () => {
        const html = orders.getStateBadge('paid');
        expect(html).toContain('Paid');
        expect(html).toContain('layui-bg-green');
    });

    test('returns HTML string for "canceled" state', () => {
        const html = orders.getStateBadge('canceled');
        expect(html).toContain('Canceled');
        expect(html).toContain('layui-bg-red');
    });

    test('returns HTML string for "closed" state', () => {
        const html = orders.getStateBadge('closed');
        expect(html).toContain('Closed');
        expect(html).toContain('layui-bg-cyan');
    });

    test('returns HTML string for "ticketing" state', () => {
        const html = orders.getStateBadge('ticketing');
        expect(html).toContain('Ticketing');
        expect(html).toContain('layui-bg-purple');
    });

    test('returns HTML string for "ticketed" state', () => {
        const html = orders.getStateBadge('ticketed');
        expect(html).toContain('Ticketed');
        expect(html).toContain('layui-bg-black');
    });

    test('falls back to raw state name for unknown state', () => {
        const html = orders.getStateBadge('unknown_state');
        expect(html).toContain('unknown_state');
        expect(html).toContain('layui-badge');
    });

    test('returns a layui-badge span element', () => {
        const html = orders.getStateBadge('placed');
        expect(html).toMatch(/^<span class="layui-badge/);
        expect(html).toMatch(/<\/span>$/);
    });
});

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { setImmediate } from 'node:timers/promises';

const template = readFileSync(new URL('../../resources/views/billing/payments/checkout.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

async function harness(responses) {
    const timers = new Map();
    let sequence = 0;
    let requests = 0;
    const clock = { now: 0 };
    const element = (properties = {}) => ({ ...properties, listeners: {}, addEventListener(name, handler) { this.listeners[name] = handler; } });
    const elements = {
        'check-payment': element({ action: 'https://udworkspace.com/check?signature=test' }),
        'check-button': element(),
        'payment-status': element(),
        'open-checkout': element({ href: 'https://checkout.paystack.com/existing-code' }),
    };
    const popup = { closed: false, opener: {}, location: { replace(url) { assert.equal(popup.opener, null); popup.url = url; } }, close() { this.closed = true; } };
    const location = { href: 'https://udworkspace.com/checkout', origin: 'https://udworkspace.com', replace(url) { this.destination = url; } };
    const context = {
        document: { getElementById: (id) => elements[id] },
        window: { location, open: () => popup }, URL, AbortController,
        FormData: class { constructor(form) { this.form = form; } },
        Date: class extends Date { static now() { return clock.now; } },
        setTimeout(fn, delay) { const id = ++sequence; timers.set(id, { fn, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        async fetch() {
            const data = responses[Math.min(requests++, responses.length - 1)];
            return { ok: true, status: 200, json: async () => data };
        },
    };
    vm.runInNewContext(script, context);
    await setImmediate();
    return {
        clock, elements, popup, location,
        get requests() { return requests; },
        async clickCheckout() { elements['open-checkout'].listeners.click({ preventDefault() {} }); await setImmediate(); },
        async tick() {
            const next = [...timers].find(([, timer]) => timer.delay === 10000);
            assert.ok(next, 'Automatic checks must remain scheduled after an unconfirmed provider status');
            timers.delete(next[0]);
            clock.now += next[1].delay;
            await next[1].fn();
            await setImmediate();
        },
        async manualCheck() { elements['check-payment'].listeners.submit({ preventDefault() {} }); await setImmediate(); },
    };
}

for (const status of ['failed', 'pending']) {
    test(`an early ${status} response must not prevent later confirmation and receipt return`, async () => {
        const page = await harness([{ status, message: 'Not yet confirmed' }, { status: 'paid', redirect: 'https://udworkspace.com/receipt' }]);
        await page.tick();
        assert.equal(page.location.destination, 'https://udworkspace.com/receipt');
        assert.equal(page.requests, 2);
    });
}

test('opening checkout after automatic checks paused restarts confirmation and closes the paid popup', async () => {
    const page = await harness([{ status: 'pending' }, { status: 'pending' }, { status: 'paid', redirect: 'https://udworkspace.com/receipt' }]);
    page.clock.now = 11 * 60 * 1000;
    await page.tick();
    await page.clickCheckout();
    if (!page.location.destination) await page.tick();
    assert.equal(page.popup.url, 'https://checkout.paystack.com/existing-code');
    assert.equal(page.popup.closed, true);
    assert.equal(page.location.destination, 'https://udworkspace.com/receipt');
});

test('a mismatch cannot redirect or automatically continue verification', async () => {
    const page = await harness([{ status: 'mismatch', message: 'Contact support' }]);
    assert.equal(page.location.destination, undefined);
    await assert.rejects(page.tick(), /Automatic checks must remain scheduled/);
});

test('manual verification can recover a page whose checks paused', async () => {
    const page = await harness([{ status: 'pending' }, { status: 'pending' }, { status: 'paid', redirect: 'https://udworkspace.com/receipt' }]);
    page.clock.now = 11 * 60 * 1000;
    await page.tick();
    await page.manualCheck();
    assert.equal(page.location.destination, 'https://udworkspace.com/receipt');
});

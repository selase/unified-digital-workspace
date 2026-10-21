// Run with the Playwright browser runner after loading a rendered homepage.
async (page) => {
    const results = [];
    for (const width of [320, 390, 720, 768, 1080, 1100, 1280, 1440]) {
        await page.setViewportSize({ width, height: 844 });
        for (const panel of ['overview', 'projects', 'documents', 'people', 'sites']) {
            await page.locator('#tab-' + panel).click();
            const result = await page.evaluate((panel) => {
                const viewport = document.documentElement.clientWidth;
                const main = document.querySelector('.app-main').getBoundingClientRect();
                const frame = document.querySelector('.app-body').getBoundingClientRect();
                const active = document.querySelector('#view-' + panel);
                const account = document.querySelector('.account-cell').getBoundingClientRect();
                return {
                    viewport, scrollWidth: document.documentElement.scrollWidth,
                    previewFits: main.left >= frame.left - 1 && main.right <= frame.right + 1,
                    accountFits: account.left >= 0 && account.right <= viewport + 1,
                    panelVisible: getComputedStyle(active).display !== 'none',
                    tableFits: [...active.querySelectorAll('table')].every(table => {
                        const scroll = table.closest('.ws-table-scroll');
                        return scroll && scroll.tabIndex === 0 && getComputedStyle(scroll).overflowX === 'auto' && scroll.getBoundingClientRect().right <= main.right + 1;
                    }),
                    selected: document.querySelector('#tab-' + panel).getAttribute('aria-selected'),
                };
            }, panel);
            if (result.scrollWidth > result.viewport + 1 || !result.previewFits || !result.tableFits || !result.accountFits || !result.panelVisible || result.selected !== 'true') {
                throw new Error('Homepage responsive regression at ' + width + 'px / ' + panel + ': ' + JSON.stringify(result));
            }
        }
        results.push({ width, panels: 5, passed: true });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#tab-overview').focus();
    await page.keyboard.press('Enter');
    if (await page.locator('#tab-overview').getAttribute('aria-selected') !== 'true') {
        throw new Error('Keyboard activation failed.');
    }
    await page.locator('#view-overview .ws-table-scroll').focus();
    await page.keyboard.press('ArrowRight');
    await page.waitForFunction(() => document.activeElement.scrollLeft > 0, null, { timeout: 3000 });
    return results;
}

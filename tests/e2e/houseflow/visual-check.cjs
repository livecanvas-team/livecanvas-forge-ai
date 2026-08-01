const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.LCFA_HOUSEFLOW_URL || 'http://test-ai-forge.local').replace(/\/$/, '');
const outputDir = process.env.LCFA_VISUAL_OUTPUT
    || path.resolve(__dirname, '../../../docs/screenshots');

const routes = {
    home: '/',
    journal: '/journal/',
    single: '/a-ten-minute-family-reset/',
};

const viewports = {
    desktop: { width: 1440, height: 1000 },
    mobile: { width: 390, height: 844 },
};

async function revealPage(page) {
    await page.evaluate(async () => {
        const pause = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
        const distance = Math.max(320, Math.floor(window.innerHeight * 0.72));

        for (let y = 0; y < document.documentElement.scrollHeight; y += distance) {
            window.scrollTo(0, y);
            await pause(80);
        }

        window.scrollTo(0, 0);
        await pause(180);
    });
}

async function inspectPage(page) {
    return page.evaluate(() => {
        const images = Array.from(document.images);
        const visibleImages = images.filter((image) => {
            const rect = image.getBoundingClientRect();
            return rect.width > 1 && rect.height > 1;
        });
        const overflowElements = Array.from(document.querySelectorAll('body *'))
            .map((element) => {
                const rect = element.getBoundingClientRect();
                const style = window.getComputedStyle(element);
                const classNames = Array.from(element.classList || []).slice(0, 3).join('.');

                return {
                    selector: element.id
                        ? `${element.tagName.toLowerCase()}#${element.id}`
                        : `${element.tagName.toLowerCase()}${classNames ? `.${classNames}` : ''}`,
                    left: Math.round(rect.left * 10) / 10,
                    right: Math.round(rect.right * 10) / 10,
                    width: Math.round(rect.width * 10) / 10,
                    position: style.position,
                };
            })
            .filter((item) => item.width > 1 && (item.left < -1 || item.right > window.innerWidth + 1))
            .slice(0, 20);

        const desktopNavLinks = Array.from(document.querySelectorAll('.houseflow-header .nav-link'));
        const visibleDesktopNavLinks = desktopNavLinks.filter((link) => {
            const rect = link.getBoundingClientRect();
            const style = window.getComputedStyle(link);
            return rect.width > 1
                && rect.height > 1
                && style.display !== 'none'
                && style.visibility === 'visible'
                && Number(style.opacity) > 0;
        });
        const shownAccordion = document.querySelector('.houseflow-accordion .accordion-collapse.show');
        const shownAccordionStyle = shownAccordion ? window.getComputedStyle(shownAccordion) : null;

        return {
            title: document.title,
            h1: document.querySelector('h1')?.textContent?.trim() || '',
            viewport_width: window.innerWidth,
            document_width: document.documentElement.scrollWidth,
            document_height: document.documentElement.scrollHeight,
            horizontal_overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            image_count: images.length,
            visible_image_count: visibleImages.length,
            broken_images: images
                .filter((image) => image.complete && image.naturalWidth === 0)
                .map((image) => image.currentSrc || image.src),
            overflow_elements: overflowElements,
            header_count: document.querySelectorAll('header.houseflow-header').length,
            footer_count: document.querySelectorAll('footer.houseflow-footer').length,
            main_count: document.querySelectorAll('main').length,
            desktop_nav_link_count: desktopNavLinks.length,
            visible_desktop_nav_link_count: visibleDesktopNavLinks.length,
            shown_accordion_visible: !shownAccordion || (
                shownAccordionStyle.display !== 'none'
                && shownAccordionStyle.visibility === 'visible'
            ),
        };
    });
}

async function run() {
    await fs.mkdir(outputDir, { recursive: true });
    const browser = await chromium.launch({ headless: true });
    const report = {
        checked_at: new Date().toISOString(),
        base_url: baseUrl,
        pages: {},
        ok: true,
    };

    try {
        for (const [routeName, route] of Object.entries(routes)) {
            report.pages[routeName] = {};

            for (const [viewportName, viewport] of Object.entries(viewports)) {
                const context = await browser.newContext({
                    viewport,
                    deviceScaleFactor: 1,
                    reducedMotion: 'reduce',
                });
                const page = await context.newPage();
                const consoleErrors = [];
                const pageErrors = [];
                page.on('console', (message) => {
                    if (message.type() === 'error') {
                        consoleErrors.push(message.text());
                    }
                });
                page.on('pageerror', (error) => pageErrors.push({
                    message: error.message,
                    stack: error.stack || '',
                }));

                const response = await page.goto(`${baseUrl}${route}`, {
                    waitUntil: 'networkidle',
                    timeout: 30000,
                });
                await revealPage(page);

                const screenshotName = `houseflow-${routeName}-${viewportName}.png`;
                const screenshotPath = path.join(outputDir, screenshotName);
                await page.screenshot({ path: screenshotPath, fullPage: true });
                const inspection = await inspectPage(page);

                if (routeName === 'home' && viewportName === 'mobile') {
                    const toggle = page.locator('.navbar-toggler');
                    if (await toggle.count()) {
                        await toggle.click();
                        await page.screenshot({
                            path: path.join(outputDir, 'houseflow-home-mobile-menu.png'),
                            fullPage: false,
                        });
                        inspection.mobile_menu_expanded = await toggle.getAttribute('aria-expanded');
                        inspection.mobile_menu_visible_links = await page.locator('.houseflow-header .nav-link:visible').count();
                    }
                }

                const result = {
                    url: `${baseUrl}${route}`,
                    status: response?.status() || 0,
                    screenshot: screenshotPath,
                    console_errors: consoleErrors,
                    page_errors: pageErrors,
                    ...inspection,
                };
                result.ok = result.status === 200
                    && !result.horizontal_overflow
                    && result.broken_images.length === 0
                    && result.page_errors.length === 0
                    && result.header_count === 1
                    && result.footer_count === 1
                    && result.shown_accordion_visible
                    && (viewportName !== 'desktop' || result.visible_desktop_nav_link_count === result.desktop_nav_link_count)
                    && (routeName !== 'home' || viewportName !== 'mobile' || (
                        result.mobile_menu_expanded === 'true'
                        && result.mobile_menu_visible_links === result.desktop_nav_link_count
                    ));
                report.pages[routeName][viewportName] = result;
                report.ok = report.ok && result.ok;

                await context.close();
            }
        }
    } finally {
        await browser.close();
    }

    const reportPath = path.join(outputDir, 'houseflow-visual-report.json');
    await fs.writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
    process.exitCode = report.ok ? 0 : 1;
}

run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});

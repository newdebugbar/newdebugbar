<?php

it('moves the compact toolbar away from host dialogs at either screen edge', function () {
    $page = visit('/profiled')
        ->resize(1440, 900)
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom');

    $page->script(<<<'JS'
        const dialog = document.createElement('dialog');
        dialog.dataset.testid = 'host-dialog';
        dialog.setAttribute('open', '');
        Object.assign(dialog.style, {
            position: 'fixed',
            inset: 'auto 0 0 0',
            width: '100vw',
            height: '180px',
            margin: '0',
        });
        document.body.append(dialog);
        JS);

    $page
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().top <= 13');

    $page->script(<<<'JS'
        const dialog = document.querySelector('[data-testid="host-dialog"]');
        dialog.style.inset = '0 0 auto 0';
        JS);

    $page
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().bottom >= window.innerHeight - 13')
        ->assertNoJavaScriptErrors();
});

it('opens the inspector from the active toolbar anchor', function () {
    $page = visit('/profiled')->resize(1440, 900);

    $page
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertAttribute('[role="dialog"][aria-label="Request inspector"]', 'data-ndb-placement', 'bottom')
        ->assertScript(<<<'JS'
            (() => {
                const fact = document.querySelector('[data-ndb-header-fact="duration"]');
                const value = Array.from(fact.querySelectorAll('[x-text]')).at(-1)?.textContent.trim();
                window.__ndbBottomDuration = value;

                return /^(?:<1|\d+(?:\.\d+)?) (?:µs|ms|s)$/.test(value ?? '');
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const box = panel.getBoundingClientRect();
                const styles = getComputedStyle(panel);

                return Math.abs(box.bottom - window.innerHeight) <= 1
                    && box.top > 0
                    && styles.borderTopLeftRadius !== '0px'
                    && styles.borderBottomLeftRadius === '0px';
            })()
            JS)
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                Alpine.$data(toolbar).pinToolbar('top');

                return true;
            })()
            JS)
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertScript(<<<'JS'
            (() => {
                const control = document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
                const panel = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                window.__ndbInspectorSamples = [];

                control.addEventListener('click', () => {
                    let remaining = 20;
                    const sample = () => {
                        if (getComputedStyle(panel).display !== 'none') {
                            const box = panel.getBoundingClientRect();
                            window.__ndbInspectorSamples.push({ top: box.top, bottom: box.bottom });
                        }

                        remaining -= 1;
                        if (remaining > 0) requestAnimationFrame(sample);
                    };

                    requestAnimationFrame(sample);
                }, { capture: true, once: true });

                return panel.dataset.ndbPlacement === 'top'
                    && panel.getAttribute('x-transition:enter-start') === 'ndb-inspector-offscreen'
                    && panel.getAttribute('x-transition:leave-end') === 'ndb-inspector-offscreen';
            })()
            JS)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->assertAttribute('[role="dialog"][aria-label="Request inspector"]', 'data-ndb-placement', 'top')
        ->assertScript(<<<'JS'
            (() => {
                const fact = document.querySelector('[data-ndb-header-fact="duration"]');
                const value = Array.from(fact.querySelectorAll('[x-text]')).at(-1)?.textContent.trim();

                return value === window.__ndbBottomDuration
                    && /^(?:<1|\d+(?:\.\d+)?) (?:µs|ms|s)$/.test(value ?? '');
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const box = panel.getBoundingClientRect();
                const styles = getComputedStyle(panel);
                const samples = window.__ndbInspectorSamples ?? [];

                return Math.abs(box.top) <= 1
                    && box.bottom < window.innerHeight
                    && styles.borderTopLeftRadius === '0px'
                    && styles.borderBottomLeftRadius !== '0px'
                    && samples.length >= 8
                    && samples.some((sample) => sample.top < -20)
                    && samples.every((sample) => sample.top <= 1)
                    && samples.at(-1).top >= -2;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('drags the compact toolbar between animated persistent anchors', function () {
    $page = visit('/profiled')
        ->resize(1440, 900)
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom')
        ->assertScript(<<<'JS'
            (() => {
                const top = document.createElement('div');
                top.dataset.testid = 'toolbar-top-drop-target';
                Object.assign(top.style, {
                    position: 'fixed',
                    top: '0',
                    left: '50%',
                    width: '48px',
                    height: '48px',
                    zIndex: '1',
                });
                const bottom = top.cloneNode();
                bottom.dataset.testid = 'toolbar-bottom-drop-target';
                bottom.style.top = 'auto';
                bottom.style.bottom = '0';
                document.body.append(top, bottom);

                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                const hint = document.getElementById(toolbar.getAttribute('aria-describedby'));

                return getComputedStyle(toolbar).transitionProperty.includes('transform')
                    && hint.textContent.includes('or any corner');
            })()
            JS);

    $page
        ->drag('[data-ndb-toolbar-shell]', '[data-testid="toolbar-top-drop-target"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-preferred-placement', 'top')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().top <= 13')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").dataset.ndbDragging !== "true"')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").dataset.ndbSnapping !== "true"')
        ->assertScript("JSON.parse(localStorage.getItem('newdebugbar.preferences.v1')).toolbarAnchor === 'top'")
        ->refresh()
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().top <= 13');

    $page->script(<<<'JS'
        const bottom = document.createElement('div');
        bottom.dataset.testid = 'toolbar-bottom-drop-target';
        Object.assign(bottom.style, {
            position: 'fixed',
            bottom: '0',
            left: '50%',
            width: '48px',
            height: '48px',
            zIndex: '1',
        });
        document.body.append(bottom);
        JS);

    $page
        ->drag('[data-ndb-toolbar-shell]', '[data-testid="toolbar-bottom-drop-target"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-preferred-placement', 'bottom')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().bottom >= window.innerHeight - 13')
        ->assertNoJavaScriptErrors();
});

it('uses only the existing request split button at every corner', function () {
    $page = visit('/profiled')->resize(1440, 900);

    foreach (['top-left', 'top-right', 'bottom-left', 'bottom-right'] as $placement) {
        $page
            ->assertScript(<<<JS
                (() => {
                    const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                    Alpine.\$data(toolbar).pinToolbar('{$placement}');

                    return true;
                })()
                JS)
            ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', $placement)
            ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-form', 'corner')
            ->assertScript(<<<'JS'
                (() => {
                    const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                    const corner = toolbar.querySelector('[data-ndb-corner-toolbar]');
                    const center = toolbar.querySelector('[data-ndb-center-toolbar]');
                    const switcher = corner.querySelector('[data-ndb-request-switcher="corner"]');
                    const primary = corner.querySelector('[data-ndb-corner-request]');
                    const trigger = corner.querySelector('[data-ndb-request-picker-trigger="corner"]');
                    const method = corner.querySelector('[data-ndb-request-method="corner"]');
                    const path = corner.querySelector('[data-ndb-corner-request-path]');
                    const status = corner.querySelector('[data-ndb-corner-request-status]');
                    const box = toolbar.getBoundingClientRect();
                    const triggerStyle = getComputedStyle(trigger);
                    const methodStyle = getComputedStyle(method);
                    const statusStyle = getComputedStyle(status);
                    const methodBox = method.getBoundingClientRect();
                    const pathBox = path.getBoundingClientRect();
                    const statusBox = status.getBoundingClientRect();
                    const placement = toolbar.dataset.ndbPlacement;
                    const topInset = placement.startsWith('top') ? box.top : window.innerHeight - box.bottom;
                    const sideInset = placement.endsWith('left') ? box.left : window.innerWidth - box.right;

                    return Math.abs(box.width - 196) <= 1
                        && Math.abs(box.height - 56) <= 1
                        && Math.abs(topInset - 12) <= 1
                        && Math.abs(sideInset - 12) <= 1
                        && getComputedStyle(toolbar).borderRadius === '18px'
                        && getComputedStyle(corner).display !== 'none'
                        && getComputedStyle(center).display === 'none'
                        && getComputedStyle(switcher).display !== 'none'
                        && primary !== null
                        && trigger !== null
                        && method.textContent.trim() === method.textContent.trim().toUpperCase()
                        && path.textContent.trim().startsWith('/')
                        && /^\d{3}$/.test(status.textContent.trim())
                        && Number.parseFloat(triggerStyle.paddingLeft) === 2
                        && Number.parseFloat(triggerStyle.paddingRight) === 2
                        && Number.parseFloat(methodStyle.paddingLeft) === 0
                        && Number.parseFloat(methodStyle.paddingRight) === 0
                        && methodStyle.backgroundColor === 'rgba(0, 0, 0, 0)'
                        && methodStyle.borderRadius === '0px'
                        && methodStyle.textTransform === 'uppercase'
                        && !method.className.includes('indigo')
                        && methodStyle.color !== statusStyle.color
                        && method.getBoundingClientRect().width < 48
                        && methodBox.right < pathBox.left
                        && Math.abs((methodBox.top + methodBox.height / 2) - (pathBox.top + pathBox.height / 2)) <= 1
                        && methodBox.top < statusBox.top
                        && corner.querySelector('[data-ndb-corner-toolbar-trigger]') === null
                        && corner.querySelector('[data-ndb-mobile-toolbar-menu]') === null
                        && JSON.parse(localStorage.getItem('newdebugbar.preferences.v1')).toolbarAnchor === placement;
                })()
                JS);
    }

    $page
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                const state = Alpine.$data(toolbar);
                state.toolbarDragging = true;
                state.toolbarDragTarget = 'bottom-right';

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const preview = document.querySelector('[data-ndb-toolbar-anchor="bottom-right"]');
                const box = preview.getBoundingClientRect();
                const styles = getComputedStyle(preview);

                return preview.dataset.ndbActive === 'true'
                    && Math.abs(box.width - 224) <= 1
                    && Math.abs(box.height - 224) <= 1
                    && Math.abs(box.left - (window.innerWidth - box.width / 2)) <= 1
                    && Math.abs(box.top - (window.innerHeight - box.height / 2)) <= 1
                    && Number.parseFloat(styles.borderRadius) >= box.width / 2
                    && Number.parseFloat(styles.borderTopWidth) === 0
                    && styles.backgroundImage.includes('radial-gradient')
                    && Number.parseFloat(styles.opacity) === 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                const state = Alpine.$data(toolbar);
                state.toolbarDragging = false;

                return true;
            })()
            JS)
        ->click('[data-ndb-corner-request]')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->assertAttribute('[role="dialog"][aria-label="Request inspector"]', 'data-ndb-placement', 'bottom')
        ->assertVisible('[data-ndb-inspector-panel="request"]')
        ->assertScript('document.querySelector("[data-ndb-inspector-heading]").textContent.trim() === "Requests"')
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->assertVisible('[data-ndb-corner-toolbar]')
        ->assertNoJavaScriptErrors();
});

it('animates from the toolbar release point to the nearest anchor', function () {
    $page = visit('/profiled')->resize(1440, 900);

    $page->script(<<<'JS'
        const target = document.createElement('div');
        target.dataset.testid = 'toolbar-middle-top-target';
        Object.assign(target.style, {
            position: 'fixed',
            top: '300px',
            left: '50%',
            width: '48px',
            height: '48px',
            zIndex: '1',
        });
        document.body.append(target);

        const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
        toolbar.addEventListener('pointerup', () => {
            window.__ndbToolbarDropTop = toolbar.getBoundingClientRect().top;
            window.__ndbToolbarSnapSamples = [];
            let remaining = 36;
            const sample = () => {
                window.__ndbToolbarSnapSamples.push(toolbar.getBoundingClientRect().top);
                remaining -= 1;
                if (remaining > 0) requestAnimationFrame(sample);
            };
            requestAnimationFrame(sample);
        }, { capture: true, once: true });
        JS);

    $page
        ->drag('[data-ndb-toolbar-shell]', '[data-testid="toolbar-middle-top-target"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertScript(<<<'JS'
            (() => {
                const drop = window.__ndbToolbarDropTop;
                const samples = window.__ndbToolbarSnapSamples ?? [];

                return samples.length >= 20
                    && Math.abs(samples[0] - drop) <= 3
                    && samples.every((top) => top >= 10 && top <= drop + 3)
                    && samples.every((top, index) => index === 0 || top <= samples[index - 1] + 2)
                    && samples.at(-1) <= 13;
            })()
            JS);

    $page->script(<<<'JS'
        const target = document.createElement('div');
        target.dataset.testid = 'toolbar-middle-bottom-target';
        Object.assign(target.style, {
            position: 'fixed',
            top: '550px',
            left: '50%',
            width: '48px',
            height: '48px',
            zIndex: '1',
        });
        document.body.append(target);

        const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
        toolbar.addEventListener('pointerup', () => {
            window.__ndbToolbarDropTop = toolbar.getBoundingClientRect().top;
            window.__ndbToolbarSnapSamples = [];
            let remaining = 36;
            const sample = () => {
                window.__ndbToolbarSnapSamples.push(toolbar.getBoundingClientRect().top);
                remaining -= 1;
                if (remaining > 0) requestAnimationFrame(sample);
            };
            requestAnimationFrame(sample);
        }, { capture: true, once: true });
        JS);

    $page
        ->drag('[data-ndb-toolbar-shell]', '[data-testid="toolbar-middle-bottom-target"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom')
        ->assertScript(<<<'JS'
            (() => {
                const drop = window.__ndbToolbarDropTop;
                const samples = window.__ndbToolbarSnapSamples ?? [];

                return samples.length >= 20
                    && Math.abs(samples[0] - drop) <= 3
                    && samples.every((top) => top >= drop - 3 && top <= 830)
                    && samples.every((top, index) => index === 0 || top >= samples[index - 1] - 2)
                    && samples.at(-1) >= 827;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

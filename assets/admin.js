(function () {
    function getAdminConfig() {
        return window.lcfaAdmin && typeof window.lcfaAdmin === 'object' ? window.lcfaAdmin : {};
    }

    function copyText(button) {
        var value = button.getAttribute('data-lcfa-copy-text') || '';
        if (!value || !navigator.clipboard || !navigator.clipboard.writeText) {
            return;
        }

        var copiedLabel = button.getAttribute('data-lcfa-copied-label') || 'Copied';
        var originalLabel = button.getAttribute('data-lcfa-copy-label') || button.textContent || 'Copy';

        navigator.clipboard.writeText(value).then(function () {
            button.textContent = copiedLabel;
            window.setTimeout(function () {
                button.textContent = originalLabel;
            }, 1800);
        });
    }

    function highlightBlocks(root) {
        if (!window.Prism || !root) {
            return;
        }

        var blocks = root.querySelectorAll('.lcfa-code-block code[class*="language-"]');
        blocks.forEach(function (block) {
            window.Prism.highlightElement(block);
        });
    }

    function buildAjaxBody(config) {
        var params = new URLSearchParams();
        params.set('action', config.connectionsSecondaryAction || 'lcfa_connections_secondary');
        params.set('nonce', config.connectionsSecondaryNonce || '');

        return params.toString();
    }

    function setSecondaryPanelState(panel, state, content) {
        if (!panel) {
            return null;
        }

        panel.classList.remove('is-loading', 'is-error', 'is-ready');
        panel.classList.add(state);
        panel.setAttribute('aria-busy', state === 'is-loading' ? 'true' : 'false');

        if (typeof content === 'string') {
            panel.innerHTML = content;
        }

        return panel;
    }

    function replaceSecondaryPanel(panel, html) {
        var wrapper;
        var replacement;

        if (!panel || typeof html !== 'string' || html === '') {
            return panel;
        }

        wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        replacement = wrapper.firstElementChild;

        if (!replacement) {
            return setSecondaryPanelState(panel, 'is-error');
        }

        panel.replaceWith(replacement);

        return replacement;
    }

    function renderSecondaryError(root, message) {
        var panels = root.querySelectorAll('[data-lcfa-connections-panel]');

        panels.forEach(function (panel) {
            setSecondaryPanelState(panel, 'is-error', [
                '<div class="lcfa-card-head">',
                '<span class="lcfa-icon lcfa-icon-plug" aria-hidden="true"></span>',
                '<div><h2>Connection details unavailable</h2><p>' + message + '</p></div>',
                '</div>'
            ].join(''));
        });
    }

    function loadConnectionsSecondaryPanels(root) {
        var config = getAdminConfig();

        if (!root || root.dataset.loaded === '1' || !config.ajaxUrl) {
            return;
        }

        root.dataset.loaded = '1';

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: buildAjaxBody(config)
        }).then(function (response) {
            return response.text().then(function (text) {
                var payload = {};

                try {
                    payload = text ? JSON.parse(text) : {};
                } catch (error) {
                    payload = {};
                }

                if (!response.ok || !payload.success || !payload.data || !payload.data.panels) {
                    throw new Error(
                        payload && payload.data && payload.data.message
                            ? payload.data.message
                            : ((config.labels && config.labels.loadFailed) || 'Failed to load connection details.')
                    );
                }

                return payload.data.panels;
            });
        }).then(function (panels) {
            Object.keys(panels).forEach(function (key) {
                var panel = root.querySelector('[data-lcfa-connections-panel="' + key + '"]');

                if (!panel) {
                    return;
                }

                panel = replaceSecondaryPanel(panel, String(panels[key] || ''));
                highlightBlocks(panel);
            });
        }).catch(function (error) {
            renderSecondaryError(
                root,
                (error && error.message) || ((config.labels && config.labels.loadFailed) || 'Failed to load connection details.')
            );
        });
    }

    function bootstrapConnectionsSecondaryPanels() {
        var roots = document.querySelectorAll('[data-lcfa-connections-secondary-root]');

        roots.forEach(function (root) {
            window.setTimeout(function () {
                loadConnectionsSecondaryPanels(root);
            }, 120);
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target instanceof Element ? event.target.closest('[data-lcfa-copy-text]') : null;
        if (!button) {
            return;
        }

        event.preventDefault();
        copyText(button);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            highlightBlocks(document);
            bootstrapConnectionsSecondaryPanels();
        });
    } else {
        highlightBlocks(document);
        bootstrapConnectionsSecondaryPanels();
    }
})();

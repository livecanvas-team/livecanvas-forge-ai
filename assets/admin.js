(function () {
    function getAdminConfig() {
        return window.lcfaAdmin && typeof window.lcfaAdmin === 'object' ? window.lcfaAdmin : {};
    }

    function setCopiedFeedback(button, copiedLabel, originalLabel) {
        button.textContent = copiedLabel;
        window.setTimeout(function () {
            button.textContent = originalLabel;
        }, 1800);
    }

    function legacyCopyText(value) {
        var textarea;

        if (!document.body || !document.createElement || !document.execCommand) {
            return false;
        }

        textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '-9999px';
        textarea.style.opacity = '0';
        textarea.style.pointerEvents = 'none';

        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        if (typeof textarea.setSelectionRange === 'function') {
            textarea.setSelectionRange(0, textarea.value.length);
        }

        try {
            return !!document.execCommand('copy');
        } catch (error) {
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }

    function copyText(button) {
        var value = button.getAttribute('data-lcfa-copy-text') || '';
        var clipboard = typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function'
            ? navigator.clipboard
            : null;
        var didCopy = false;

        if (!value || !clipboard) {
            didCopy = legacyCopyText(value);

            if (didCopy) {
                setCopiedFeedback(
                    button,
                    button.getAttribute('data-lcfa-copied-label') || 'Copied',
                    button.getAttribute('data-lcfa-copy-label') || button.textContent || 'Copy'
                );
            }

            return;
        }

        var copiedLabel = button.getAttribute('data-lcfa-copied-label') || 'Copied';
        var originalLabel = button.getAttribute('data-lcfa-copy-label') || button.textContent || 'Copy';

        clipboard.writeText(value).then(function () {
            setCopiedFeedback(button, copiedLabel, originalLabel);
        }).catch(function () {
            if (legacyCopyText(value)) {
                setCopiedFeedback(button, copiedLabel, originalLabel);
            }
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

    function bootstrapReadMore(root) {
        var panels;

        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }

        panels = root.querySelectorAll('[data-lcfa-read-more]');
        panels.forEach(function (panel) {
            var body = panel.querySelector('[data-lcfa-read-more-body]');
            var toggle = panel.querySelector('[data-lcfa-read-more-toggle]');
            var styles = body && typeof window.getComputedStyle === 'function' ? window.getComputedStyle(body) : null;
            var lineHeight = styles ? parseFloat(styles.lineHeight || '0') : 0;
            var maxHeight = lineHeight > 0 ? lineHeight * 3 : 64;
            var bodyHeight = body && typeof body.scrollHeight === 'number' ? body.scrollHeight : 0;

            if (!body || !toggle) {
                return;
            }

            if (bodyHeight <= maxHeight + 1) {
                panel.classList.remove('is-overflowing', 'is-collapsed');
                toggle.hidden = true;
                return;
            }

            panel.classList.add('is-overflowing', 'is-collapsed');
            toggle.hidden = false;
            toggle.textContent = toggle.getAttribute('data-lcfa-collapsed-label') || 'Read more';
        });
    }

    function toggleReadMore(button) {
        var panel = button.closest('[data-lcfa-read-more]');
        var collapsedLabel = button.getAttribute('data-lcfa-collapsed-label') || 'Read more';
        var expandedLabel = button.getAttribute('data-lcfa-expanded-label') || 'Show less';

        if (!panel) {
            return;
        }

        if (panel.classList.toggle('is-collapsed')) {
            button.textContent = collapsedLabel;
        } else {
            button.textContent = expandedLabel;
        }
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
                bootstrapReadMore(panel);
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
        var readMoreButton = event.target instanceof Element ? event.target.closest('[data-lcfa-read-more-toggle]') : null;

        if (button) {
            event.preventDefault();
            copyText(button);
            return;
        }

        if (readMoreButton) {
            event.preventDefault();
            toggleReadMore(readMoreButton);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            highlightBlocks(document);
            bootstrapReadMore(document);
            bootstrapConnectionsSecondaryPanels();
        });
    } else {
        highlightBlocks(document);
        bootstrapReadMore(document);
        bootstrapConnectionsSecondaryPanels();
    }
})();

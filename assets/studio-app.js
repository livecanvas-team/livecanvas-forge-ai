(function (window, document) {
    var wp = window.wp || {};
    var element = wp.element || {};
    var apiFetch = wp.apiFetch;
    var h = element.createElement;
    var useEffect = element.useEffect;
    var useMemo = element.useMemo;
    var useState = element.useState;
    var STORAGE_PREFIX = 'lcfaStudio.';
    var DEFAULT_ABILITY_COLUMNS = {
        name: true,
        exposure: true,
        kind: true,
        actions: true
    };
    var DEFAULT_RUN_COLUMNS = {
        summary: true,
        status: true,
        audit: true,
        actions: true
    };

    function getConfig(root) {
        var config = window.lcfaStudio && typeof window.lcfaStudio === 'object' ? window.lcfaStudio : {};

        return {
            endpoint: config.endpoint || root.getAttribute('data-lcfa-studio-endpoint') || '',
            nonce: config.nonce || '',
            labels: config.labels || {},
            links: config.links || {}
        };
    }

    function setFallbackHidden(hidden) {
        document.querySelectorAll('[data-lcfa-studio-fallback]').forEach(function (fallback) {
            fallback.hidden = !!hidden;
        });
    }

    function fetchStudioState(config) {
        if (apiFetch && typeof apiFetch === 'function') {
            return apiFetch({ url: config.endpoint });
        }

        return window.fetch(config.endpoint, {
            credentials: 'same-origin',
            headers: config.nonce ? { 'X-WP-Nonce': config.nonce } : {}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Forge Studio REST request failed.');
            }

            return response.json();
        });
    }

    function className(parts) {
        return parts.filter(Boolean).join(' ');
    }

    function storageAvailable() {
        try {
            return !!window.localStorage;
        } catch (error) {
            return false;
        }
    }

    function mergeDefaults(value, fallback) {
        var next = {};

        Object.keys(fallback || {}).forEach(function (key) {
            next[key] = fallback[key];
        });

        if (value && typeof value === 'object' && !Array.isArray(value)) {
            Object.keys(value).forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(next, key)) {
                    next[key] = !!value[key];
                }
            });
        }

        return next;
    }

    function getStoredValue(key, fallback) {
        var raw;
        var parsed;

        if (!storageAvailable()) {
            return fallback;
        }

        try {
            raw = window.localStorage.getItem(STORAGE_PREFIX + key);
            if (!raw) {
                return fallback;
            }

            parsed = JSON.parse(raw);
            if (fallback && typeof fallback === 'object' && !Array.isArray(fallback)) {
                return mergeDefaults(parsed, fallback);
            }

            return parsed === undefined || parsed === null ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    function setStoredValue(key, value) {
        if (!storageAvailable()) {
            return;
        }

        try {
            window.localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value));
        } catch (error) {}
    }

    function clearStoredViewPreferences() {
        if (!storageAvailable()) {
            return;
        }

        [
            'abilityFilter',
            'abilitySort',
            'abilityColumns',
            'runFilter',
            'runSort',
            'runColumns'
        ].forEach(function (key) {
            try {
                window.localStorage.removeItem(STORAGE_PREFIX + key);
            } catch (error) {}
        });
    }

    function useStoredState(key, fallback) {
        var state = useState(function () {
            return getStoredValue(key, fallback);
        });
        var value = state[0];
        var setValue = state[1];

        useEffect(function () {
            setStoredValue(key, value);
        }, [key, value]);

        return [value, setValue];
    }

    function asArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function normalizeSearch(value) {
        return String(value || '').toLowerCase().trim();
    }

    function count(value) {
        return Number.isFinite(Number(value)) ? Number(value) : 0;
    }

    function toJson(value) {
        try {
            return JSON.stringify(value || {}, null, 2);
        } catch (error) {
            return '{}';
        }
    }

    function commandUrl(baseUrl, action, auditId) {
        if (!baseUrl || !auditId) {
            return '';
        }

        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';

        return baseUrl + separator + 'suggest_action=' + encodeURIComponent(action) + '&audit_id=' + encodeURIComponent(auditId);
    }

    function runKey(run) {
        if (!run || typeof run !== 'object') {
            return '';
        }

        return String(run.audit_id || [
            run.time || '',
            run.action || '',
            run.target_id || '',
            run.summary || run.message || ''
        ].join('|'));
    }

    function compareString(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, { sensitivity: 'base' });
    }

    function sortAbilities(items, sort) {
        return items.slice().sort(function (left, right) {
            if (sort === 'name') {
                return compareString(left.name, right.name);
            }

            if (sort === 'exposure') {
                return compareString(left.mcp_public ? '0-public' : '1-private', right.mcp_public ? '0-public' : '1-private')
                    || compareString(left.label || left.name, right.label || right.name);
            }

            if (sort === 'kind') {
                return compareString(left.readonly ? '1-read' : '0-write', right.readonly ? '1-read' : '0-write')
                    || compareString(left.label || left.name, right.label || right.name);
            }

            return compareString(left.label || left.name, right.label || right.name);
        });
    }

    function sortRuns(items, sort) {
        return items.slice().sort(function (left, right) {
            if (sort === 'action') {
                return compareString(left.action, right.action) || compareString(right.time, left.time);
            }

            if (sort === 'status') {
                return compareString(left.ok ? '1-ok' : '0-error', right.ok ? '1-ok' : '0-error') || compareString(right.time, left.time);
            }

            if (sort === 'rollback') {
                return compareString(left.rollback_available ? '0-rollback' : '1-none', right.rollback_available ? '0-rollback' : '1-none') || compareString(right.time, left.time);
            }

            return compareString(right.time, left.time);
        });
    }

    function toggleColumn(columns, key) {
        var next = {};

        Object.keys(columns || {}).forEach(function (columnKey) {
            next[columnKey] = columns[columnKey];
        });
        next[key] = !next[key];

        return next;
    }

    function Card(props) {
        return h('section', { className: className(['lcfa-card', props.className || '']) },
            props.title ? h('div', { className: 'lcfa-card-head' },
                h('span', { className: 'lcfa-icon lcfa-icon-sparkles', 'aria-hidden': 'true' }),
                h('div', null,
                    h('h2', null, props.title),
                    props.description ? h('p', null, props.description) : null
                )
            ) : null,
            props.children
        );
    }

    function SummaryTile(props) {
        return h('div', { className: 'lcfa-summary-tile' },
            h('span', null, props.label),
            h('strong', null, String(props.value === '' || props.value === undefined ? '0' : props.value))
        );
    }

    function Chip(props) {
        var attrs = { className: className(['lcfa-chip', props.tone ? 'is-' + props.tone : '']) };

        if (props['data-lcfa-studio-column']) {
            attrs['data-lcfa-studio-column'] = props['data-lcfa-studio-column'];
        }

        return h('span', attrs, props.children);
    }

    function Overview(props) {
        var summary = props.summary || {};

        return h(Card, {
            title: 'Forge Studio app',
            description: 'WordPress-native Studio state loaded through the REST endpoint.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button',
                    disabled: !!props.loading,
                    onClick: props.onRefresh
                }, props.loading ? 'Refreshing...' : 'Refresh'),
                h('button', {
                    type: 'button',
                    className: 'button',
                    onClick: props.onResetView
                }, 'Reset view'),
                h('button', {
                    type: 'button',
                    className: 'button',
                    'data-lcfa-copy-text': props.copyText || '{}',
                    'data-lcfa-copy-label': 'Copy Studio state',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy Studio state')
            ),
            props.generatedAt ? h('p', { className: 'lcfa-studio-meta' }, 'State generated at ' + props.generatedAt) : null,
            h('div', { className: 'lcfa-summary-grid' },
                h(SummaryTile, { label: 'Abilities', value: count(summary.abilities) }),
                h(SummaryTile, { label: 'MCP public', value: count(summary.mcp_public) }),
                h(SummaryTile, { label: 'Public writes', value: count(summary.public_writes) }),
                h(SummaryTile, { label: 'Runs', value: count(summary.runs) }),
                h(SummaryTile, { label: 'Run errors', value: count(summary.run_errors) }),
                h(SummaryTile, { label: 'Rollbacks', value: count(summary.rollbacks) }),
                h(SummaryTile, { label: 'Framework', value: summary.framework || 'Auto' })
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, { tone: summary.mcp_adapter_ready ? 'positive' : '' }, summary.mcp_adapter_ready ? 'MCP Adapter ready' : 'MCP Adapter pending'),
                h(Chip, { tone: summary.ai_text_ready ? 'positive' : '' }, summary.ai_text_ready ? 'AI text ready' : 'AI text unavailable'),
                h(Chip, { tone: summary.setup_complete ? 'positive' : 'negative' }, summary.setup_complete ? 'Setup complete' : 'Setup incomplete')
            ),
            h('div', { className: 'lcfa-cta-row' },
                props.links.connections ? h('a', { className: 'button', href: props.links.connections }, 'Connection settings') : null,
                props.links.command ? h('a', { className: 'button button-primary', href: props.links.command }, 'Open Command Deck') : null
            )
        );
    }

    function ContractPanel(props) {
        var contract = props.contract || {};
        var readiness = contract.readiness || {};
        var sections = asArray(contract.sections);
        var fingerprint = String(contract.fingerprint || '');

        return h(Card, {
            className: 'lcfa-studio-contract',
            title: 'Studio contract',
            description: 'Stable REST payload metadata for frontend and agent integrations.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(contract),
                    'data-lcfa-copy-label': 'Copy contract',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy contract')
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Schema: ' + (contract.schema_version || 'studio.v1')),
                h(Chip, null, 'Payload: v' + count(contract.payload_version || 1)),
                h(Chip, null, 'Sections: ' + count(contract.section_count)),
                h(Chip, null, 'Run limit: ' + count(contract.limits && contract.limits.runs))
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, { tone: readiness.setup_complete ? 'positive' : 'negative' }, readiness.setup_complete ? 'Setup complete' : 'Setup incomplete'),
                h(Chip, { tone: readiness.mcp_adapter_ready ? 'positive' : '' }, readiness.mcp_adapter_ready ? 'MCP ready' : 'MCP pending'),
                h(Chip, { tone: readiness.ai_text_ready ? 'positive' : '' }, readiness.ai_text_ready ? 'AI text ready' : 'AI text unavailable')
            ),
            fingerprint ? h('code', { className: 'lcfa-studio-fingerprint', 'data-lcfa-studio-contract': 'fingerprint' }, fingerprint) : null,
            sections.length ? h('div', { className: 'lcfa-studio-contract-sections', 'data-lcfa-studio-contract': 'sections' },
                sections.map(function (section) {
                    return h(Chip, { key: section }, section);
                })
            ) : null
        );
    }

    function HandoffReadinessPanel(props) {
        var readiness = props.readiness || {};
        var gates = asArray(readiness.gates);
        var blockers = asArray(readiness.blockers);
        var warnings = asArray(readiness.warnings);
        var status = String(readiness.status || 'unknown');

        return h(Card, {
            className: 'lcfa-studio-handoff-readiness',
            title: 'Handoff readiness',
            description: 'Backend-calculated gates for Codex and MCP agent handoff.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(readiness),
                    'data-lcfa-copy-label': 'Copy readiness',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy readiness')
            ),
            h('div', { className: 'lcfa-studio-readiness-score', 'data-lcfa-studio-handoff-readiness': 'score' },
                h('strong', null, String(count(readiness.score))),
                h('span', null, status.charAt(0).toUpperCase() + status.slice(1)),
                h(Chip, { tone: status === 'ready' ? 'positive' : (status === 'blocked' ? 'negative' : 'warning') }, 'Mode: ' + (readiness.recommended_mode || 'read_only_only'))
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Gates: ' + gates.length),
                h(Chip, { tone: blockers.length > 0 ? 'negative' : 'positive' }, 'Blockers: ' + blockers.length),
                h(Chip, { tone: warnings.length > 0 ? 'warning' : 'positive' }, 'Warnings: ' + warnings.length)
            ),
            gates.length ? h('div', { className: 'lcfa-studio-gate-list', 'data-lcfa-studio-handoff-readiness': 'gates' },
                gates.map(function (gate) {
                    var gateStatus = String(gate.status || 'unknown');

                    return h('div', { key: gate.id || gate.label, className: className(['lcfa-studio-gate-item', 'is-' + gateStatus]) },
                        h('div', { className: 'lcfa-studio-gate-item__head' },
                            h('strong', null, gate.label || gate.id || 'Gate'),
                            h(Chip, { tone: gateStatus === 'pass' ? 'positive' : (gateStatus === 'fail' ? 'negative' : 'warning') }, gateStatus)
                        ),
                        gate.detail ? h('p', null, gate.detail) : null
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No handoff readiness gates are available.')
        );
    }

    function AbilityExplorer(props) {
        var labels = props.labels || {};
        var abilities = props.abilities || {};
        var items = asArray(abilities.items);
        var columns = props.columns || {};
        var filtered = useMemo(function () {
            var query = normalizeSearch(props.search);

            return sortAbilities(items.filter(function (item) {
                var name = String(item.name || '');
                var label = String(item.label || name);
                var haystack = normalizeSearch(label + ' ' + name);
                var isPublic = !!item.mcp_public;
                var isWrite = !item.readonly;
                var isDestructive = !!item.destructive;

                if (query && haystack.indexOf(query) === -1) {
                    return false;
                }

                if (props.filter === 'public') {
                    return isPublic;
                }

                if (props.filter === 'private') {
                    return !isPublic;
                }

                if (props.filter === 'write') {
                    return isWrite;
                }

                if (props.filter === 'destructive') {
                    return isDestructive;
                }

                return true;
            }), props.sort);
        }, [items, props.search, props.filter, props.sort]);

        return h(Card, {
            title: 'Abilities',
            description: 'REST-backed ability inventory with MCP exposure and write flags.'
        },
            h('div', { className: 'lcfa-studio-toolbar' },
                h('label', { className: 'lcfa-studio-search' },
                    h('span', null, 'Search abilities'),
                    h('input', {
                        type: 'search',
                        value: props.search,
                        placeholder: 'Search name or label',
                        onChange: function (event) {
                            props.onSearch(event.target.value);
                        }
                    })
                ),
                h('label', { className: 'lcfa-studio-control' },
                    h('span', null, 'Sort abilities'),
                    h('select', {
                        value: props.sort,
                        onChange: function (event) {
                            props.onSort(event.target.value);
                        }
                    },
                        h('option', { value: 'label' }, 'Label'),
                        h('option', { value: 'name' }, 'Name'),
                        h('option', { value: 'exposure' }, 'MCP exposure'),
                        h('option', { value: 'kind' }, 'Read/write')
                    )
                ),
                h('div', { className: 'lcfa-studio-segmented', role: 'group', 'aria-label': 'Ability filters' },
                    ['all', 'public', 'private', 'write', 'destructive'].map(function (filter) {
                        return h('button', {
                            key: filter,
                            type: 'button',
                            className: className(['button', props.filter === filter ? 'is-current' : '']),
                            onClick: function () {
                                props.onFilter(filter);
                            }
                        }, filter === 'public' ? 'MCP public' : filter.charAt(0).toUpperCase() + filter.slice(1));
                    })
                )
            ),
            h('div', { className: 'lcfa-studio-columns', role: 'group', 'aria-label': 'Ability columns' },
                [
                    ['name', 'Name'],
                    ['exposure', 'Exposure'],
                    ['kind', 'Kind'],
                    ['actions', 'Actions']
                ].map(function (column) {
                    return h('label', { key: column[0], 'data-lcfa-studio-column': column[0] },
                        h('input', {
                            type: 'checkbox',
                            checked: !!columns[column[0]],
                            onChange: function () {
                                props.onToggleColumn(column[0]);
                            }
                        }),
                        column[1]
                    );
                })
            ),
            filtered.length ? h('div', { className: 'lcfa-target-list' },
                filtered.slice(0, 40).map(function (item) {
                    var name = String(item.name || '');
                    var label = String(item.label || name);
                    var isPublic = !!item.mcp_public;
                    var isReadonly = !!item.readonly;
                    var isDestructive = !!item.destructive;

                    return h('div', { key: name, className: className(['lcfa-target-item', props.selectedName === name ? 'is-current' : '']) },
                        columns.name ? h('div', { className: 'lcfa-target-copy', 'data-lcfa-studio-column': 'name' },
                            h('strong', null, label),
                            h('span', null, h('code', null, name))
                        ) : null,
                        h('div', { className: 'lcfa-chip-row' },
                            columns.exposure ? h(Chip, { tone: isPublic ? 'positive' : '', 'data-lcfa-studio-column': 'exposure' }, isPublic ? 'MCP public' : 'MCP private') : null,
                            columns.kind ? h(Chip, { tone: isReadonly ? 'positive' : 'warning', 'data-lcfa-studio-column': 'kind' }, isReadonly ? 'Read-only' : 'Write') : null,
                            columns.kind ? h(Chip, { tone: isDestructive ? 'negative' : '', 'data-lcfa-studio-column': 'kind' }, isDestructive ? 'Destructive' : 'Non-destructive') : null,
                            columns.actions ? h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-copy-text': name,
                                'data-lcfa-copy-label': 'Copy name',
                                'data-lcfa-copied-label': 'Copied',
                                'data-lcfa-studio-column': 'actions'
                            }, 'Copy name') : null,
                            columns.actions ? h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-studio-column': 'actions',
                                onClick: function () {
                                    props.onInspect(name);
                                }
                            }, 'Inspect') : null
                        )
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, labels.emptyAbilities || 'No abilities match the current filters.')
        );
    }

    function AlertsPanel(props) {
        var alerts = asArray(props.alerts);
        var diagnostics = props.diagnostics || {};
        var adapter = diagnostics.mcp_adapter || {};
        var aiClient = diagnostics.ai_client || {};
        var connectors = aiClient.connectors || {};

        return h(Card, {
            className: 'lcfa-studio-readiness',
            title: 'Readiness',
            description: 'Configuration and run-health checks from the Studio endpoint.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson({
                        alerts: alerts,
                        diagnostics: diagnostics
                    }),
                    'data-lcfa-copy-label': 'Copy diagnostics',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy diagnostics')
            ),
            alerts.length ? h('div', { className: 'lcfa-studio-alert-list' },
                alerts.map(function (alert, index) {
                    var severity = String(alert.severity || 'info');
                    var code = String(alert.code || index);

                    return h('div', {
                        key: code,
                        className: className(['lcfa-studio-alert', 'is-' + severity]),
                        'data-lcfa-studio-alert': code
                    },
                        h('strong', null, alert.title || code),
                        alert.message ? h('p', null, alert.message) : null
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No readiness alerts are available.'),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, { tone: adapter.available ? 'positive' : '' }, adapter.available ? 'MCP Adapter ready' : 'MCP Adapter pending'),
                h(Chip, { tone: aiClient.text_generation_supported ? 'positive' : '' }, aiClient.text_generation_supported ? 'AI text ready' : 'AI text unavailable'),
                h(Chip, null, 'Connectors: ' + count(connectors.count)),
                h(Chip, null, 'Text connectors: ' + count(connectors.text_generation_count))
            )
        );
    }

    function OperatorBriefingPanel(props) {
        var briefing = props.briefing || {};
        var summary = asArray(briefing.summary);
        var risks = asArray(briefing.risks);
        var actions = asArray(briefing.next_actions);
        var prompt = String(briefing.agent_prompt || '');

        return h(Card, {
            className: 'lcfa-studio-operator-briefing',
            title: 'Operator briefing',
            description: 'Copy-ready context for connected coding agents.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    disabled: !prompt,
                    'data-lcfa-copy-text': prompt,
                    'data-lcfa-copy-label': 'Copy agent prompt',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy agent prompt'),
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(briefing),
                    'data-lcfa-copy-label': 'Copy operator briefing',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy operator briefing')
            ),
            summary.length ? h('ul', { className: 'lcfa-studio-briefing-list', 'data-lcfa-studio-operator-briefing': 'summary' },
                summary.map(function (line, index) {
                    return h('li', { key: String(index) }, line);
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No briefing summary is available.'),
            h('div', { className: 'lcfa-studio-briefing-grid' },
                h('div', { className: 'lcfa-studio-briefing-block' },
                    h('h3', null, 'Risks'),
                    risks.length ? risks.slice(0, 6).map(function (risk) {
                        return h('div', { key: risk.code || risk.title, className: className(['lcfa-studio-briefing-row', risk.severity ? 'is-' + risk.severity : '']) },
                            h('strong', null, risk.title || risk.code || 'Risk'),
                            risk.message ? h('span', null, risk.message) : null
                        );
                    }) : h('p', { className: 'lcfa-empty' }, 'No active risks.')
                ),
                h('div', { className: 'lcfa-studio-briefing-block' },
                    h('h3', null, 'Next actions'),
                    actions.length ? actions.slice(0, 6).map(function (action) {
                        return h('div', { key: action.code || action.label, className: 'lcfa-studio-briefing-row' },
                            h('strong', null, action.label || action.code || 'Action'),
                            action.detail ? h('span', null, action.detail) : null
                        );
                    }) : h('p', { className: 'lcfa-empty' }, 'No next actions.')
                )
            ),
            prompt ? h('pre', { className: 'lcfa-studio-briefing-prompt', 'data-lcfa-studio-operator-briefing': 'agent-prompt' }, prompt) : null
        );
    }

    function AgentSmokeTestsPanel(props) {
        var plan = props.plan || {};
        var counts = plan.counts || {};
        var tests = asArray(plan.tests);

        return h(Card, {
            className: 'lcfa-studio-agent-smoke-tests',
            title: 'Agent smoke tests',
            description: 'Ordered read-only and preview checks for MCP-connected agents.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(plan),
                    'data-lcfa-copy-label': 'Copy smoke tests',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy smoke tests')
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Mode: ' + (plan.mode || 'read_only_first')),
                h(Chip, { tone: count(counts.available) === count(counts.total) ? 'positive' : 'warning' }, 'Available: ' + count(counts.available) + '/' + count(counts.total)),
                h(Chip, null, 'Read-only: ' + count(counts.read_only)),
                h(Chip, null, 'Preview: ' + count(counts.preview)),
                h(Chip, { tone: count(counts.write_guarded) > 0 ? 'warning' : '' }, 'Write guards: ' + count(counts.write_guarded))
            ),
            tests.length ? h('div', { className: 'lcfa-studio-smoke-list', 'data-lcfa-studio-agent-smoke-tests': 'list' },
                tests.map(function (test, index) {
                    var payload = toJson(test.payload || {});

                    return h('div', { key: test.id || String(index), className: className(['lcfa-studio-smoke-item', test.available ? 'is-available' : 'is-missing']) },
                        h('div', { className: 'lcfa-studio-smoke-item__head' },
                            h('strong', null, String(index + 1) + '. ' + (test.label || test.id || 'Smoke test')),
                            h('div', { className: 'lcfa-chip-row' },
                                h(Chip, { tone: test.available ? 'positive' : 'warning' }, test.available ? 'Available' : 'Missing'),
                                h(Chip, { tone: test.phase === 'write_guard' ? 'warning' : '' }, test.phase || 'read_only'),
                                test.public_write_exposed ? h(Chip, { tone: 'negative' }, 'Public write') : null
                            )
                        ),
                        h('code', null, test.ability || ''),
                        test.intent ? h('p', null, test.intent) : null,
                        h('small', null, 'Expected: ' + (test.expected || 'No expected result specified.')),
                        h('div', { className: 'lcfa-studio-actions' },
                            h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-copy-text': toJson({
                                    ability: test.ability || '',
                                    payload: test.payload || {}
                                }),
                                'data-lcfa-copy-label': 'Copy test payload',
                                'data-lcfa-copied-label': 'Copied'
                            }, 'Copy test payload'),
                            h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-copy-text': payload,
                                'data-lcfa-copy-label': 'Copy payload JSON',
                                'data-lcfa-copied-label': 'Copied'
                            }, 'Copy payload JSON')
                        )
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No agent smoke tests are available.')
        );
    }

    function AgentRunbookPanel(props) {
        var runbook = props.runbook || {};
        var checklist = asArray(runbook.checklist);
        var markdown = String(runbook.markdown || '');

        return h(Card, {
            className: 'lcfa-studio-agent-runbook',
            title: 'Agent runbook',
            description: 'Markdown handoff for a new Codex or MCP agent session.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    disabled: !markdown,
                    'data-lcfa-copy-text': markdown,
                    'data-lcfa-copy-label': 'Copy runbook markdown',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy runbook markdown'),
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(runbook),
                    'data-lcfa-copy-label': 'Copy agent runbook',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy agent runbook')
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Format: ' + (runbook.format || 'markdown')),
                h(Chip, null, 'Lines: ' + count(runbook.line_count)),
                h(Chip, null, 'Checklist: ' + checklist.length)
            ),
            checklist.length ? h('ul', { className: 'lcfa-studio-runbook-checklist' },
                checklist.map(function (item, index) {
                    return h('li', { key: String(index) }, item);
                })
            ) : null,
            markdown ? h('pre', { className: 'lcfa-studio-runbook-markdown', 'data-lcfa-studio-agent-runbook': 'markdown' }, markdown) : h('p', { className: 'lcfa-empty' }, 'No agent runbook is available.')
        );
    }

    function AgentHandoffPackagePanel(props) {
        var bundle = props.bundle || {};
        var summary = bundle.summary || {};
        var manifest = bundle.manifest || {};
        var files = asArray(bundle.files);
        var checksum = String(summary.checksum || manifest.package_checksum || '');
        var endpoint = String(props.endpoint || '');

        return h(Card, {
            className: 'lcfa-studio-agent-handoff-package',
            title: 'Agent handoff package',
            description: 'Virtual files for Codex and MCP agent handoff.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    disabled: !files.length,
                    'data-lcfa-copy-text': toJson(bundle),
                    'data-lcfa-copy-label': 'Copy handoff package',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy handoff package'),
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    disabled: !files.length,
                    'data-lcfa-copy-text': toJson(manifest),
                    'data-lcfa-copy-label': 'Copy package manifest',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy package manifest'),
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    disabled: !endpoint,
                    'data-lcfa-copy-text': endpoint,
                    'data-lcfa-copy-label': 'Copy package endpoint',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy package endpoint')
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Version: ' + count(bundle.package_version || 1)),
                h(Chip, { tone: bundle.status === 'ready' ? 'positive' : (bundle.status === 'blocked' ? 'negative' : 'warning') }, 'Status: ' + (bundle.status || 'unknown')),
                h(Chip, null, 'Mode: ' + (bundle.recommended_mode || 'read_only_first')),
                h(Chip, null, 'Files: ' + count(summary.files || files.length)),
                h(Chip, null, 'Bytes: ' + count(summary.bytes))
            ),
            checksum ? h('code', { className: 'lcfa-studio-package-checksum', 'data-lcfa-studio-handoff-package': 'checksum' }, checksum) : null,
            files.length ? h('div', { className: 'lcfa-studio-package-list', 'data-lcfa-studio-handoff-package': 'files' },
                files.map(function (file) {
                    var path = String(file.path || '');
                    var sha = String(file.sha256 || '');
                    var content = String(file.content || '');

                    return h('div', { key: path || sha, className: 'lcfa-studio-package-item' },
                        h('div', { className: 'lcfa-studio-package-item__head' },
                            h('div', null,
                                h('strong', null, path || 'virtual-file'),
                                h('span', null, file.media_type || 'text/plain')
                            ),
                            h('div', { className: 'lcfa-chip-row' },
                                h(Chip, null, count(file.bytes) + ' bytes'),
                                sha ? h(Chip, null, sha.slice(0, 12) + '...') : null
                            )
                        ),
                        h('div', { className: 'lcfa-studio-actions' },
                            h('button', {
                                type: 'button',
                                className: 'button button-small',
                                disabled: !content,
                                'data-lcfa-copy-text': content,
                                'data-lcfa-copy-label': 'Copy file',
                                'data-lcfa-copied-label': 'Copied'
                            }, 'Copy file'),
                            h('button', {
                                type: 'button',
                                className: 'button button-small',
                                disabled: !sha,
                                'data-lcfa-copy-text': sha,
                                'data-lcfa-copy-label': 'Copy checksum',
                                'data-lcfa-copied-label': 'Copied'
                            }, 'Copy checksum')
                        )
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No agent handoff package is available.')
        );
    }

    function RunHealthPanel(props) {
        var analysis = props.analysis || {};
        var totals = analysis.totals || {};
        var actions = asArray(analysis.by_action);
        var origins = asArray(analysis.by_origin);
        var timeline = asArray(analysis.timeline);

        function metricRows(items, emptyLabel) {
            return items.length ? h('div', { className: 'lcfa-studio-metric-list' },
                items.slice(0, 6).map(function (item) {
                    return h('div', { key: String(item.name || ''), className: 'lcfa-studio-metric-row' },
                        h('span', null, item.name || 'unknown'),
                        h('strong', null, String(count(item.count))),
                        count(item.errors) > 0 ? h(Chip, { tone: 'negative' }, count(item.errors) + ' errors') : null
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, emptyLabel);
        }

        return h(Card, {
            className: 'lcfa-studio-run-health',
            title: 'Run health',
            description: 'Recent run analytics from the sanitized Studio audit state.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(analysis),
                    'data-lcfa-copy-label': 'Copy run analysis',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy run analysis')
            ),
            h('div', { className: 'lcfa-summary-grid lcfa-studio-health-grid' },
                h(SummaryTile, { label: 'OK', value: count(totals.ok) }),
                h(SummaryTile, { label: 'Errors', value: count(totals.errors) }),
                h(SummaryTile, { label: 'Apply', value: count(totals.apply) }),
                h(SummaryTile, { label: 'Preview', value: count(totals.preview) }),
                h(SummaryTile, { label: 'Audited', value: count(totals.audited) }),
                h(SummaryTile, { label: 'Rollbacks', value: count(totals.rollbacks) })
            ),
            h('div', { className: 'lcfa-studio-analysis-grid' },
                h('div', { className: 'lcfa-studio-analysis-block' },
                    h('h3', null, 'Action mix'),
                    metricRows(actions, 'No action metrics are available.')
                ),
                h('div', { className: 'lcfa-studio-analysis-block' },
                    h('h3', null, 'Origin mix'),
                    metricRows(origins, 'No origin metrics are available.')
                )
            ),
            h('div', { className: 'lcfa-studio-timeline', 'data-lcfa-studio-timeline': 'recent' },
                h('h3', null, 'Recent timeline'),
                timeline.length ? timeline.slice(0, 8).map(function (item, index) {
                    var title = item.summary || item.action || 'Unnamed run';

                    return h('div', { key: String(item.time || index), className: className(['lcfa-studio-timeline-row', item.status === 'error' ? 'is-error' : 'is-ok']) },
                        h('span', null, item.time || ''),
                        h('strong', null, title),
                        h('div', { className: 'lcfa-chip-row' },
                            h(Chip, { tone: item.status === 'error' ? 'negative' : 'positive' }, item.status === 'error' ? 'Error' : 'OK'),
                            item.action ? h(Chip, null, item.action) : null,
                            item.mode ? h(Chip, null, item.mode) : null,
                            item.audit_id ? h(Chip, null, item.audit_id) : null
                        )
                    );
                }) : h('p', { className: 'lcfa-empty' }, 'No run timeline is available.')
            )
        );
    }

    function AbilityManifestPanel(props) {
        var manifest = props.manifest || {};
        var counts = manifest.counts || {};
        var items = asArray(manifest.items);

        return h(Card, {
            className: 'lcfa-studio-ability-manifest',
            title: 'Ability manifest',
            description: 'MCP-facing ability inventory with compact schema hints.'
        },
            h('div', { className: 'lcfa-studio-actions' },
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(manifest),
                    'data-lcfa-copy-label': 'Copy ability manifest',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy ability manifest')
            ),
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, null, 'Source: ' + (manifest.source || 'diagnostics')),
                h(Chip, { tone: count(counts.public) > 0 ? 'positive' : '' }, 'Public: ' + count(counts.public)),
                h(Chip, { tone: count(counts.write) > 0 ? 'warning' : 'positive' }, 'Write: ' + count(counts.write)),
                h(Chip, null, 'Read-only: ' + count(counts.readonly))
            ),
            items.length ? h('div', { className: 'lcfa-studio-manifest-list', 'data-lcfa-studio-ability-manifest': 'list' },
                items.slice(0, 10).map(function (item) {
                    var properties = asArray(item.input_properties);
                    var required = asArray(item.input_required);
                    var name = String(item.name || '');

                    return h('div', { key: name, className: 'lcfa-studio-manifest-item' },
                        h('strong', null, item.label || name),
                        h('code', null, name),
                        item.description ? h('p', null, item.description) : null,
                        h('div', { className: 'lcfa-chip-row' },
                            h(Chip, { tone: item.mcp_public ? 'positive' : '' }, item.mcp_public ? 'MCP public' : 'MCP private'),
                            h(Chip, { tone: item.readonly ? 'positive' : 'warning' }, item.readonly ? 'Read-only' : 'Write'),
                            h(Chip, { tone: item.destructive ? 'negative' : '' }, item.destructive ? 'Destructive' : 'Non-destructive'),
                            h(Chip, null, 'Input: ' + (item.input_schema_type || 'object'))
                        ),
                        h('small', null, 'Input properties: ' + (properties.length ? properties.slice(0, 8).join(', ') : 'none')),
                        required.length ? h('small', null, 'Required: ' + required.join(', ')) : null
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No ability manifest entries are available.')
        );
    }

    function WritePolicy(props) {
        var policy = props.policy || {};
        var counts = policy.counts || {};
        var allowlist = asArray(policy.allowlist);

        return h(Card, {
            title: 'MCP write policy',
            description: 'Destructive ability exposure is controlled by the master opt-in and the per-ability allowlist.'
        },
            h('div', { className: 'lcfa-chip-row' },
                h(Chip, { tone: policy.master_enabled ? 'warning' : 'positive' }, policy.master_enabled ? 'Master opt-in enabled' : 'Master opt-in disabled'),
                h(Chip, null, 'Allowed: ' + count(counts.allowed)),
                h(Chip, { tone: count(counts.exposed) > 0 ? 'negative' : 'positive' }, 'Exposed: ' + count(counts.exposed))
            ),
            allowlist.length ? h('ul', { className: 'lcfa-check-list' },
                allowlist.map(function (name) {
                    return h('li', { key: name }, h('code', null, name));
                })
            ) : h('p', { className: 'lcfa-empty' }, 'No write ability is currently allowed for MCP exposure.'),
            props.links.connections ? h('p', null, h('a', { className: 'button', href: props.links.connections }, 'Edit write allowlist')) : null
        );
    }

    function InspectorPanel(props) {
        var ability = props.ability || null;
        var run = props.run || null;
        var restoreUrl = run && run.rollback_available && run.execution_target === 'local'
            ? commandUrl(props.links.command || '', 'restore_audit_rollback', run.audit_id)
            : '';

        return h(Card, {
            className: 'lcfa-studio-inspector',
            title: 'Inspector',
            description: 'Selected ability or run details from the sanitized Studio state.'
        },
            !ability && !run ? h('p', { className: 'lcfa-empty' }, 'Select an ability or run to inspect details here.') : null,
            ability ? h('div', { className: 'lcfa-studio-inspector__section', 'data-lcfa-studio-inspector': 'ability' },
                h('h3', null, 'Ability'),
                h('p', null, h('code', null, ability.name || '')),
                h('div', { className: 'lcfa-chip-row' },
                    h(Chip, { tone: ability.mcp_public ? 'positive' : '' }, ability.mcp_public ? 'MCP public' : 'MCP private'),
                    h(Chip, { tone: ability.readonly ? 'positive' : 'warning' }, ability.readonly ? 'Read-only' : 'Write'),
                    h(Chip, { tone: ability.destructive ? 'negative' : '' }, ability.destructive ? 'Destructive' : 'Non-destructive')
                ),
                h('button', {
                    type: 'button',
                    className: 'button button-small',
                    'data-lcfa-copy-text': toJson(ability),
                    'data-lcfa-copy-label': 'Copy ability JSON',
                    'data-lcfa-copied-label': 'Copied'
                }, 'Copy ability JSON')
            ) : null,
            run ? h('div', { className: 'lcfa-studio-inspector__section', 'data-lcfa-studio-inspector': 'run' },
                h('h3', null, 'Run'),
                h('p', null, run.summary || run.message || 'Unnamed operation'),
                h('div', { className: 'lcfa-chip-row' },
                    run.action ? h(Chip, null, run.action) : null,
                    run.mode ? h(Chip, null, run.mode) : null,
                    h(Chip, { tone: run.ok ? 'positive' : 'negative' }, run.ok ? 'OK' : 'Error'),
                    run.audit_id ? h(Chip, null, run.audit_id) : null,
                    run.rollback_available ? h(Chip, { tone: 'positive' }, 'Rollback ready') : null
                ),
                h('div', { className: 'lcfa-studio-actions' },
                    h('button', {
                        type: 'button',
                        className: 'button button-small',
                        'data-lcfa-copy-text': toJson(run),
                        'data-lcfa-copy-label': 'Copy run JSON',
                        'data-lcfa-copied-label': 'Copied'
                    }, 'Copy run JSON'),
                    restoreUrl ? h('a', { className: 'button button-small', href: restoreUrl }, 'Restore') : null,
                    props.links.command ? h('a', { className: 'button button-small', href: props.links.command }, 'Open Deck') : null
                )
            ) : null
        );
    }

    function RunsExplorer(props) {
        var labels = props.labels || {};
        var runs = asArray(props.runs && props.runs.items);
        var columns = props.columns || {};
        var filtered = useMemo(function () {
            var query = normalizeSearch(props.search);

            return sortRuns(runs.filter(function (run) {
                var haystack = normalizeSearch([
                    run.summary,
                    run.message,
                    run.action,
                    run.audit_id,
                    run.target_title
                ].join(' '));

                if (query && haystack.indexOf(query) === -1) {
                    return false;
                }

                if (props.filter === 'rollback') {
                    return !!run.rollback_available;
                }

                if (props.filter === 'error') {
                    return !run.ok;
                }

                if (props.filter === 'apply') {
                    return run.mode === 'apply';
                }

                return true;
            }), props.sort);
        }, [runs, props.search, props.filter, props.sort]);

        return h(Card, {
            title: 'Runs & audit',
            description: 'Recent sanitized run metadata from the Studio REST endpoint.'
        },
            h('div', { className: 'lcfa-studio-toolbar' },
                h('label', { className: 'lcfa-studio-search' },
                    h('span', null, 'Search runs'),
                    h('input', {
                        type: 'search',
                        value: props.search,
                        placeholder: 'Search action, audit ID, or target',
                        onChange: function (event) {
                            props.onSearch(event.target.value);
                        }
                    })
                ),
                h('label', { className: 'lcfa-studio-control' },
                    h('span', null, 'Sort runs'),
                    h('select', {
                        value: props.sort,
                        onChange: function (event) {
                            props.onSort(event.target.value);
                        }
                    },
                        h('option', { value: 'time' }, 'Newest'),
                        h('option', { value: 'action' }, 'Action'),
                        h('option', { value: 'status' }, 'Status'),
                        h('option', { value: 'rollback' }, 'Rollback')
                    )
                ),
                h('div', { className: 'lcfa-studio-segmented', role: 'group', 'aria-label': 'Run filters' },
                    ['all', 'rollback', 'error', 'apply'].map(function (filter) {
                        return h('button', {
                            key: filter,
                            type: 'button',
                            className: className(['button', props.filter === filter ? 'is-current' : '']),
                            onClick: function () {
                                props.onFilter(filter);
                            }
                        }, filter.charAt(0).toUpperCase() + filter.slice(1));
                    })
                )
            ),
            h('div', { className: 'lcfa-studio-columns', role: 'group', 'aria-label': 'Run columns' },
                [
                    ['summary', 'Summary'],
                    ['status', 'Status'],
                    ['audit', 'Audit'],
                    ['actions', 'Actions']
                ].map(function (column) {
                    return h('label', { key: column[0], 'data-lcfa-studio-column': column[0] },
                        h('input', {
                            type: 'checkbox',
                            checked: !!columns[column[0]],
                            onChange: function () {
                                props.onToggleColumn(column[0]);
                            }
                        }),
                        column[1]
                    );
                })
            ),
            filtered.length ? h('div', { className: 'lcfa-history-list' },
                filtered.slice(0, 20).map(function (run, index) {
                    var title = run.summary || run.message || 'Unnamed operation';
                    var key = runKey(run) || String(index);
                    var restoreUrl = run.rollback_available && run.execution_target === 'local'
                        ? commandUrl(props.links.command || '', 'restore_audit_rollback', run.audit_id)
                        : '';

                    return h('div', { key: key, className: className(['lcfa-history-item', props.selectedKey === key ? 'is-current' : '']) },
                        columns.summary ? h('div', { className: 'lcfa-history-copy', 'data-lcfa-studio-column': 'summary' },
                            h('strong', null, title),
                            run.time ? h('span', null, run.time) : null,
                            run.target_title ? h('span', null, run.target_title) : null
                        ) : null,
                        h('div', { className: 'lcfa-chip-row' },
                            columns.status && run.action ? h(Chip, { 'data-lcfa-studio-column': 'status' }, run.action) : null,
                            columns.status && run.mode ? h(Chip, { 'data-lcfa-studio-column': 'status' }, run.mode) : null,
                            columns.status ? h(Chip, { tone: run.ok ? 'positive' : 'negative', 'data-lcfa-studio-column': 'status' }, run.ok ? 'OK' : 'Error') : null,
                            columns.audit && run.audit_id ? h(Chip, { 'data-lcfa-studio-column': 'audit' }, run.audit_id) : null,
                            columns.audit && run.rollback_available ? h(Chip, { tone: 'positive', 'data-lcfa-studio-column': 'audit' }, 'Rollback ready') : null,
                            columns.actions && run.audit_id ? h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-copy-text': run.audit_id,
                                'data-lcfa-copy-label': 'Copy audit',
                                'data-lcfa-copied-label': 'Copied',
                                'data-lcfa-studio-column': 'actions'
                            }, 'Copy audit') : null,
                            columns.actions && restoreUrl ? h('a', { className: 'button button-small', href: restoreUrl, 'data-lcfa-studio-column': 'actions' }, 'Restore') : null,
                            columns.actions && props.links.command ? h('a', { className: 'button button-small', href: props.links.command, 'data-lcfa-studio-column': 'actions' }, 'Open Deck') : null,
                            columns.actions ? h('button', {
                                type: 'button',
                                className: 'button button-small',
                                'data-lcfa-studio-column': 'actions',
                                onClick: function () {
                                    props.onInspect(key);
                                }
                            }, 'Inspect') : null
                        )
                    );
                })
            ) : h('p', { className: 'lcfa-empty' }, labels.emptyRuns || 'No runs match the current filters.')
        );
    }

    function App(props) {
        var config = props.config;
        var labels = config.labels || {};
        var state = useState(null);
        var data = state[0];
        var setData = state[1];
        var errorState = useState('');
        var error = errorState[0];
        var setError = errorState[1];
        var loadingState = useState(true);
        var loading = loadingState[0];
        var setLoading = loadingState[1];
        var refreshedAtState = useState('');
        var refreshedAt = refreshedAtState[0];
        var setRefreshedAt = refreshedAtState[1];
        var selectedAbilityState = useState('');
        var selectedAbilityName = selectedAbilityState[0];
        var setSelectedAbilityName = selectedAbilityState[1];
        var selectedRunState = useState('');
        var selectedRunKey = selectedRunState[0];
        var setSelectedRunKey = selectedRunState[1];
        var abilitySearchState = useState('');
        var abilitySearch = abilitySearchState[0];
        var setAbilitySearch = abilitySearchState[1];
        var abilityFilterState = useStoredState('abilityFilter', 'all');
        var abilityFilter = abilityFilterState[0];
        var setAbilityFilter = abilityFilterState[1];
        var abilitySortState = useStoredState('abilitySort', 'label');
        var abilitySort = abilitySortState[0];
        var setAbilitySort = abilitySortState[1];
        var abilityColumnsState = useStoredState('abilityColumns', DEFAULT_ABILITY_COLUMNS);
        var abilityColumns = abilityColumnsState[0];
        var setAbilityColumns = abilityColumnsState[1];
        var runSearchState = useState('');
        var runSearch = runSearchState[0];
        var setRunSearch = runSearchState[1];
        var runFilterState = useStoredState('runFilter', 'all');
        var runFilter = runFilterState[0];
        var setRunFilter = runFilterState[1];
        var runSortState = useStoredState('runSort', 'time');
        var runSort = runSortState[0];
        var setRunSort = runSortState[1];
        var runColumnsState = useStoredState('runColumns', DEFAULT_RUN_COLUMNS);
        var runColumns = runColumnsState[0];
        var setRunColumns = runColumnsState[1];
        var selectedAbility = useMemo(function () {
            return asArray(data && data.abilities && data.abilities.items).filter(function (ability) {
                return String(ability.name || '') === selectedAbilityName;
            })[0] || null;
        }, [data, selectedAbilityName]);
        var selectedRun = useMemo(function () {
            return asArray(data && data.runs && data.runs.items).filter(function (run) {
                return runKey(run) === selectedRunKey;
            })[0] || null;
        }, [data, selectedRunKey]);

        function load() {
            setLoading(true);
            setError('');
            fetchStudioState(config).then(function (payload) {
                setData(payload || {});
                setRefreshedAt(String(payload && payload.studio && payload.studio.generated_at ? payload.studio.generated_at : ''));
                setFallbackHidden(true);
            }).catch(function (loadError) {
                setError(loadError && loadError.message ? loadError.message : (labels.loadFailed || 'Forge Studio app could not load.'));
                setFallbackHidden(false);
            }).finally(function () {
                setLoading(false);
            });
        }

        useEffect(function () {
            load();
        }, []);

        function resetView() {
            clearStoredViewPreferences();
            setAbilityFilter('all');
            setAbilitySort('label');
            setAbilityColumns(DEFAULT_ABILITY_COLUMNS);
            setRunFilter('all');
            setRunSort('time');
            setRunColumns(DEFAULT_RUN_COLUMNS);
            setSelectedAbilityName('');
            setSelectedRunKey('');
        }

        if (loading && !data) {
            return h(Card, { className: 'lcfa-studio-react', title: 'Forge Studio app' },
                h('p', { className: 'lcfa-empty' }, labels.loading || 'Loading Forge Studio...')
            );
        }

        if (error && !data) {
            return h(Card, { className: 'lcfa-studio-react', title: 'Forge Studio app' },
                h('p', { className: 'lcfa-empty' }, labels.loadFailed || error),
                h('button', { className: 'button', type: 'button', onClick: load }, labels.retry || 'Retry')
            );
        }

        data = data || {};

        return h('div', { className: 'lcfa-studio-react' },
            h('div', { className: 'lcfa-grid' },
                h('div', { className: 'lcfa-main' },
                    h(Overview, {
                        summary: data.summary || {},
                        links: config.links || {},
                        loading: loading,
                        generatedAt: refreshedAt || String(data.studio && data.studio.generated_at ? data.studio.generated_at : ''),
                        copyText: toJson(data),
                        onRefresh: load,
                        onResetView: resetView
                    }),
                    h(ContractPanel, {
                        contract: data.contract || {}
                    }),
                    h(HandoffReadinessPanel, {
                        readiness: data.handoff_readiness || {}
                    }),
                    h(OperatorBriefingPanel, {
                        briefing: data.operator_briefing || {}
                    }),
                    h(AgentSmokeTestsPanel, {
                        plan: data.agent_smoke_tests || {}
                    }),
                    h(AgentRunbookPanel, {
                        runbook: data.agent_runbook || {}
                    }),
                    h(AgentHandoffPackagePanel, {
                        bundle: data.agent_handoff_package || {},
                        endpoint: String(data.studio && data.studio.handoff_package_route ? data.studio.handoff_package_route : '')
                    }),
                    h(RunHealthPanel, {
                        analysis: data.run_analysis || {}
                    }),
                    h(AbilityManifestPanel, {
                        manifest: data.ability_manifest || {}
                    }),
                    h(AbilityExplorer, {
                        abilities: data.abilities || {},
                        search: abilitySearch,
                        filter: abilityFilter,
                        sort: abilitySort,
                        columns: abilityColumns,
                        selectedName: selectedAbilityName,
                        labels: labels,
                        onSearch: setAbilitySearch,
                        onFilter: setAbilityFilter,
                        onSort: setAbilitySort,
                        onInspect: function (name) {
                            setSelectedAbilityName(name);
                            setSelectedRunKey('');
                        },
                        onToggleColumn: function (key) {
                            setAbilityColumns(toggleColumn(abilityColumns, key));
                        }
                    })
                ),
                h('aside', { className: 'lcfa-sidebar' },
                    h(AlertsPanel, {
                        alerts: data.alerts || [],
                        diagnostics: data.diagnostics || {}
                    }),
                    h(WritePolicy, { policy: data.mcp_write_policy || {}, links: config.links || {} }),
                    h(InspectorPanel, {
                        ability: selectedAbility,
                        run: selectedRun,
                        links: config.links || {}
                    }),
                    h(RunsExplorer, {
                        runs: data.runs || {},
                        search: runSearch,
                        filter: runFilter,
                        sort: runSort,
                        columns: runColumns,
                        selectedKey: selectedRunKey,
                        labels: labels,
                        links: config.links || {},
                        onSearch: setRunSearch,
                        onFilter: setRunFilter,
                        onSort: setRunSort,
                        onInspect: function (key) {
                            setSelectedRunKey(key);
                            setSelectedAbilityName('');
                        },
                        onToggleColumn: function (key) {
                            setRunColumns(toggleColumn(runColumns, key));
                        }
                    })
                )
            )
        );
    }

    function mount(root) {
        var config;
        var app;

        if (!root || root.dataset.lcfaStudioMounted === '1' || !h || !useState || !useEffect || !useMemo) {
            return;
        }

        config = getConfig(root);
        if (!config.endpoint) {
            return;
        }

        root.dataset.lcfaStudioMounted = '1';
        app = h(App, { config: config });

        if (element.createRoot) {
            element.createRoot(root).render(app);
        } else if (element.render) {
            element.render(app, root);
        }
    }

    function bootstrap() {
        document.querySelectorAll('[data-lcfa-studio-app-root]').forEach(mount);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})(window, document);

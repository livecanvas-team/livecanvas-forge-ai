const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.js',
  'utf8'
);

class MockElement {
  constructor(tagName = 'div') {
    this.tagName = tagName.toUpperCase();
    this.attributes = {};
    this.children = [];
    this.listeners = {};
    this.dataset = {};
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this.href = '';
    this.rel = '';
    this.target = '';
    this.src = '';
    this.parentNode = null;
    this.files = [];
    this._queryMap = {};
    this._innerHTML = '';
    this._textContent = '';
    this.className = '';
    const classes = new Set();

    this.classList = {
      add: (...tokens) => {
        tokens.forEach((token) => classes.add(String(token)));
        this.className = Array.from(classes).join(' ');
      },
      remove: (...tokens) => {
        tokens.forEach((token) => classes.delete(String(token)));
        this.className = Array.from(classes).join(' ');
      },
      toggle: (token, force) => {
        const value = String(token);
        const enabled = typeof force === 'boolean' ? force : !classes.has(value);

        if (enabled) {
          classes.add(value);
        } else {
          classes.delete(value);
        }

        this.className = Array.from(classes).join(' ');

        return enabled;
      },
      contains: (token) => classes.has(String(token)),
    };
  }

  set textContent(value) {
    this._textContent = String(value);
  }

  get textContent() {
    return this._textContent;
  }

  set innerHTML(value) {
    this._innerHTML = String(value);

    if (value === '') {
      this.children = [];
    }
  }

  get innerHTML() {
    return this._innerHTML;
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);

    return child;
  }

  cloneNode(deep = false) {
    const clone = new MockElement(this.tagName.toLowerCase());
    clone.attributes = { ...this.attributes };
    clone.dataset = { ...this.dataset };
    clone.hidden = this.hidden;
    clone.disabled = this.disabled;
    clone.value = this.value;
    clone.href = this.href;
    clone.rel = this.rel;
    clone.target = this.target;
    clone.src = this.src;
    clone.className = this.className;
    clone.textContent = this.textContent;
    clone.innerHTML = this.innerHTML;

    if (deep) {
      this.children.forEach((child) => {
        clone.appendChild(child.cloneNode(true));
      });
    }

    return clone;
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : '';
  }

  addEventListener(type, handler) {
    this.listeners[type] = handler;
  }

  click() {
    this.clicked = (this.clicked || 0) + 1;
  }

  querySelector(selector) {
    return this._queryMap[selector] || null;
  }

  closest(selector) {
    if (selector === '[data-lcfa-editor-thread-apply]' && this.getAttribute('data-lcfa-editor-thread-apply')) {
      return this;
    }

    return this.parentNode && typeof this.parentNode.closest === 'function'
      ? this.parentNode.closest(selector)
      : null;
  }

  contains(node) {
    let current = node;

    while (current) {
      if (current === this) {
        return true;
      }

      current = current.parentNode;
    }

    return false;
  }
}

function createButton(label) {
  const button = new MockElement('button');
  const icon = new MockElement('span');
  const span = new MockElement('span');
  span.textContent = label;
  button.appendChild(icon);
  button.appendChild(span);

  return button;
}

function createShell(config) {
  const shell = new MockElement('div');
  const configNode = new MockElement('script');
  configNode.textContent = JSON.stringify(config);

  const nodes = {
    drawer: new MockElement('aside'),
    openButton: createButton('Open'),
    closeButton: createButton('Close'),
    configNode,
    threadSelect: new MockElement('select'),
    targetSelect: new MockElement('select'),
    promptInput: new MockElement('textarea'),
    analyzeButton: createButton('Send'),
    createThreadButton: createButton('New thread'),
    duplicateThreadButton: createButton('Duplicate current'),
    renameThreadButton: createButton('Rename current'),
    clearThreadButton: createButton('Clear current'),
    deleteThreadButton: createButton('Delete current'),
    openDeckLink: new MockElement('a'),
    attachmentInput: new MockElement('input'),
    attachmentTriggerButton: createButton('Upload image'),
    attachmentClearButton: createButton('Remove image'),
    attachmentPreview: new MockElement('div'),
    attachmentPreviewImage: new MockElement('img'),
    attachmentPreviewMeta: new MockElement('div'),
    connectionMedia: new MockElement('span'),
    sessionDetails: new MockElement('details'),
    supportDetails: new MockElement('details'),
    resultBox: new MockElement('div'),
    resultSummary: new MockElement('div'),
    resultMeta: new MockElement('div'),
    statusNode: new MockElement('div'),
    threadLog: new MockElement('div'),
    threadEmpty: new MockElement('p'),
    reasonsWrap: new MockElement('div'),
    reasonsList: new MockElement('ul'),
    warningsWrap: new MockElement('div'),
    warningsList: new MockElement('ul'),
    workflowWrap: new MockElement('div'),
    workflowList: new MockElement('ol'),
    preflightWrap: new MockElement('div'),
    preflightNode: new MockElement('pre'),
    diffWrap: new MockElement('div'),
    diffNode: new MockElement('div'),
    existingWrap: new MockElement('div'),
    existingNode: new MockElement('pre'),
    proposedWrap: new MockElement('div'),
    proposedNode: new MockElement('pre'),
  };

  nodes.threadSelect.value = config.threadId;
  nodes.targetSelect.value = 'local';
  nodes.analyzeButton.disabled = true;
  nodes.attachmentClearButton.hidden = true;
  nodes.attachmentPreview.hidden = true;
  nodes.connectionMedia.className = 'lcfa-editor-bridge__connection-media';
  const connectionIcon = new MockElement('img');
  connectionIcon.className = 'lcfa-agent-icon lcfa-agent-icon--editor-status';
  connectionIcon.src = '/assets/agent-icons/codex-color.svg';
  nodes.connectionMedia.appendChild(connectionIcon);
  nodes.statusNode.textContent = config.labels.idleState;

  Object.values(nodes).forEach((node) => {
    if (node instanceof MockElement) {
      shell.appendChild(node);
    }
  });

  shell._queryMap = {
    '.lcfa-editor-drawer': nodes.drawer,
    '[data-lcfa-editor-open]': nodes.openButton,
    '[data-lcfa-editor-close]': nodes.closeButton,
    '[data-lcfa-editor-config]': nodes.configNode,
    '[data-lcfa-editor-thread]': nodes.threadSelect,
    '[data-lcfa-editor-target]': nodes.targetSelect,
    '[data-lcfa-editor-prompt]': nodes.promptInput,
    '[data-lcfa-editor-analyze]': nodes.analyzeButton,
    '[data-lcfa-editor-thread-create]': nodes.createThreadButton,
    '[data-lcfa-editor-thread-duplicate]': nodes.duplicateThreadButton,
    '[data-lcfa-editor-thread-rename]': nodes.renameThreadButton,
    '[data-lcfa-editor-thread-clear]': nodes.clearThreadButton,
    '[data-lcfa-editor-thread-delete]': nodes.deleteThreadButton,
    '[data-lcfa-editor-open-deck]': nodes.openDeckLink,
    '[data-lcfa-editor-attachment]': nodes.attachmentInput,
    '[data-lcfa-editor-attachment-trigger]': nodes.attachmentTriggerButton,
    '[data-lcfa-editor-attachment-clear]': nodes.attachmentClearButton,
    '[data-lcfa-editor-attachment-preview]': nodes.attachmentPreview,
    '[data-lcfa-editor-attachment-preview-image]': nodes.attachmentPreviewImage,
    '[data-lcfa-editor-attachment-preview-meta]': nodes.attachmentPreviewMeta,
    '.lcfa-editor-bridge__connection-media': nodes.connectionMedia,
    '[data-lcfa-editor-session-details]': nodes.sessionDetails,
    '[data-lcfa-editor-support-details]': nodes.supportDetails,
    '[data-lcfa-editor-result]': nodes.resultBox,
    '[data-lcfa-editor-result-summary]': nodes.resultSummary,
    '[data-lcfa-editor-result-meta]': nodes.resultMeta,
    '[data-lcfa-editor-status]': nodes.statusNode,
    '[data-lcfa-editor-thread-log]': nodes.threadLog,
    '[data-lcfa-editor-thread-empty]': nodes.threadEmpty,
    '[data-lcfa-editor-result-reasons-wrap]': nodes.reasonsWrap,
    '[data-lcfa-editor-result-reasons]': nodes.reasonsList,
    '[data-lcfa-editor-result-warnings-wrap]': nodes.warningsWrap,
    '[data-lcfa-editor-result-warnings]': nodes.warningsList,
    '[data-lcfa-editor-result-workflow-wrap]': nodes.workflowWrap,
    '[data-lcfa-editor-result-workflow]': nodes.workflowList,
    '[data-lcfa-editor-result-preflight-wrap]': nodes.preflightWrap,
    '[data-lcfa-editor-result-preflight]': nodes.preflightNode,
    '[data-lcfa-editor-result-diff-wrap]': nodes.diffWrap,
    '[data-lcfa-editor-result-diff]': nodes.diffNode,
    '[data-lcfa-editor-result-existing-wrap]': nodes.existingWrap,
    '[data-lcfa-editor-result-existing]': nodes.existingNode,
    '[data-lcfa-editor-result-proposed-wrap]': nodes.proposedWrap,
    '[data-lcfa-editor-result-proposed]': nodes.proposedNode,
  };

  return { shell, ...nodes };
}

function buildResponse(data, ok = true) {
  return {
    ok,
    text: async () => JSON.stringify(data),
  };
}

async function flush() {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 5));
  await Promise.resolve();
}

(async () => {
  const agentBodies = [];
  const localChatBodies = [];
  const commandBodies = [];
  const liveCanvasRefreshCalls = [];
  const localStorageState = {};

  const editorConfig = {
    postId: 5964,
    targetId: 5964,
    variant: '1',
    threadId: 'default',
    threads: {
      default: {
        id: 'default',
        title: 'Default',
        state: 'idle',
        messages: [
          {
            role: 'user',
            label: 'Older request',
            time: '2026-04-22 10:00:00',
            content: 'Create a hero section',
          },
          {
            role: 'tool_result',
            label: 'Older result',
            time: '2026-04-22 10:01:00',
            content: 'Hero section created.',
            meta: { ok: true },
          },
          {
            role: 'user',
            label: 'Newest request',
            time: '2026-04-22 11:00:00',
            content: 'Add a pricing section',
          },
        ],
      },
    },
    defaultAction: 'site_audit',
    restEndpoint: 'http://example.test/wp-json/lcfa/v1/chat/send',
    agentRequestEndpoint: 'http://example.test/wp-json/lcfa/v1/agent/request',
    commandEndpoint: 'http://example.test/wp-json/lcfa/v1/command',
    commandExecutionEndpoint: 'http://example.test/wp-json/lcfa/v1/command/execution',
    commandBaseUrl: 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=command',
    restNonce: 'nonce',
    agentPollDelayMs: 1,
    agentPollMaxAttempts: 5,
    agent: {
      client: 'codex',
      state: 'connected',
      enabled: true,
      processor: 'codex_mcp',
      displayLabel: 'Codex',
    },
    labels: {
      idleState: 'Ready for a new request.',
      thinkingState: 'Sending request...',
      appliedState: 'Change applied inline.',
      failedState: 'The current request failed. Review the support details below.',
      queuedState: 'Queued for inline execution.',
      runningState: 'Running inline execution...',
      agentQueuedState: 'Waiting for the connected coding agent...',
      agentRunningState: 'The coding agent is processing this request...',
      agentTimeoutState: 'Request queued. Keep the coding agent open.',
      analyzing: 'Sending...',
      analyzeSuggestion: 'Send',
      analysisFailed: 'The request analysis failed.',
      applyFailed: 'The inline execution failed.',
    },
  };

  const shellNodes = createShell(editorConfig);

  const fetch = async (url, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();

    if (url === editorConfig.restEndpoint && method === 'POST') {
      localChatBodies.push(JSON.parse(options.body || '{}'));

      return buildResponse({});
    }

    if (url === editorConfig.commandExecutionEndpoint && method === 'POST') {
      commandBodies.push(JSON.parse(options.body || '{}'));

      return buildResponse({});
    }

    if (url === editorConfig.agentRequestEndpoint && method === 'POST') {
      const body = JSON.parse(options.body || '{}');
      agentBodies.push(body);

      return buildResponse({
        request: {
          id: 'req_frontend_1',
          status: 'queued',
        },
        thread: {
          id: 'default',
          title: 'Default',
          messages: [
            {
              role: 'user',
              label: 'You',
              time: '2026-04-22 11:00:00',
              content: body.user_prompt,
            },
          ],
        },
      });
    }

    if (url.startsWith(`${editorConfig.agentRequestEndpoint}?request_id=req_frontend_1`) && method === 'GET') {
      return buildResponse({
        request: {
          id: 'req_frontend_1',
          status: 'completed',
          result: {
            ok: true,
            summary: 'Codex applied the page update.',
            message: 'Codex applied the page update.',
            action: 'page_upsert',
            mode: 'apply',
            execution_target: 'local',
            diff_html: '<div class="diff">Codex diff</div>',
            proposed_html: '<section>Codex result</section>',
            provenance: {
              origin: 'mcp_agent',
              agent: 'codex',
              processed_by: 'codex_mcp',
              transport: 'mcp_stdio',
            },
          },
          thread: {
            id: 'default',
            title: 'Default',
            messages: [
              {
                role: 'tool_result',
                label: 'Codex result',
                time: '2026-04-22 11:00:01',
                content: 'Codex applied the page update.',
                meta: {
                  ok: true,
                  mode: 'apply',
                  action: 'page_upsert',
                  processed_by: 'codex_mcp',
                },
              },
            ],
          },
        },
      });
    }

    throw new Error(`Unexpected fetch URL: ${url}`);
  };

  const context = {
    console,
    URL,
    fetch,
    FileReader: class MockFileReader {},
    window: {
      fetch,
      location: { origin: 'http://example.test' },
      lc_editor_url_to_load: 'http://example.test/?page_id=5964&lc_page_editing_mode=1',
      loadURLintoEditor(url) {
        liveCanvasRefreshCalls.push(url);
      },
      localStorage: {
        getItem(key) {
          return Object.prototype.hasOwnProperty.call(localStorageState, key) ? localStorageState[key] : null;
        },
        setItem(key, value) {
          localStorageState[key] = String(value);
        },
      },
    },
    document: {
      querySelector(selector) {
        return selector === '[data-lcfa-editor-shell]' ? shellNodes.shell : null;
      },
      addEventListener() {},
      createElement(tagName) {
        return new MockElement(tagName);
      },
    },
    setTimeout,
    clearTimeout,
  };

  context.window.document = context.document;

  vm.createContext(context);
  vm.runInContext(script, context);

  assert.strictEqual(
    shellNodes.threadLog.children[0].children[1].textContent,
    'Add a pricing section',
    'frontend thread log should render newest visible message first'
  );

  shellNodes.promptInput.value = 'Add a new pricing section';
  shellNodes.promptInput.listeners.input({
    target: shellNodes.promptInput,
    preventDefault() {},
  });
  shellNodes.analyzeButton.listeners.click({
    target: shellNodes.analyzeButton,
    preventDefault() {},
  });

  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'queueing', 'connected agent flow should enter the queueing state immediately after send');
  assert.ok(
    shellNodes.statusNode.children.some((child) => child.className === 'lcfa-editor-bridge__agent-loader'),
    'active connected agent states should render the LiveCanvas/Codex loader'
  );
  assert.ok(
    shellNodes.statusNode.children.some((child) => child.className === 'lcfa-editor-bridge__thread-status-label' && child.textContent === 'Waiting for the connected coding agent...'),
    'active connected agent states should keep the human-readable processing label'
  );

  await flush();

  assert.strictEqual(localChatBodies.length, 0, 'connected agent flow must not call the local chat/send fallback');
  assert.strictEqual(commandBodies.length, 0, 'connected agent flow must not run the local inline command directly');
  assert.strictEqual(agentBodies.length, 1, 'connected agent flow should enqueue one frontend prompt request');
  assert.strictEqual(agentBodies[0]._lcfa_agent, 'codex', 'connected agent flow should declare Codex as the target agent');
  assert.strictEqual(agentBodies[0]._lcfa_processed_by, 'agent_queue', 'connected agent flow should mark the prompt as queued for an MCP agent');
  assert.strictEqual(agentBodies[0].user_prompt, 'Add a new pricing section', 'connected agent flow should preserve the user prompt');
  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'applied', 'connected agent flow should finish in the applied state when Codex completes the request');
  assert.ok(shellNodes.diffNode.innerHTML.includes('Codex diff'), 'connected agent flow should render Codex support details');
  assert.strictEqual(shellNodes.proposedNode.textContent, '<section>Codex result</section>', 'connected agent flow should render the Codex result markup');
  assert.ok(shellNodes.resultMeta.children.some((chip) => chip.textContent === 'Agent: codex'), 'connected agent flow should expose Codex provenance in support details');
  assert.ok(shellNodes.resultMeta.children.some((chip) => chip.textContent === 'Processor: codex_mcp'), 'connected agent flow should expose the MCP processor in support details');
  assert.ok(shellNodes.resultMeta.children.some((chip) => chip.textContent === 'Origin: mcp_agent'), 'connected agent flow should expose that Codex completed the request through the MCP agent');
  assert.ok(shellNodes.resultMeta.children.some((chip) => chip.textContent === 'Transport: mcp_stdio'), 'connected agent flow should expose the MCP stdio transport in support details');
  assert.strictEqual(liveCanvasRefreshCalls.length, 1, 'connected agent flow should refresh LiveCanvas after Codex returns an apply result');
  assert.ok(liveCanvasRefreshCalls[0].includes('lcfa_refresh='), 'connected agent LiveCanvas refresh should be cache-busted');
  assert.strictEqual(shellNodes.analyzeButton.children[shellNodes.analyzeButton.children.length - 1].textContent, 'Send', 'connected agent flow should restore the primary button label');

  console.log('PASS editor_chat_agent_queue_runtime_phase1');
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});

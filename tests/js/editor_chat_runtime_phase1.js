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
        const nextToken = String(token);

        if (typeof force === 'boolean') {
          if (force) {
            classes.add(nextToken);
          } else {
            classes.delete(nextToken);
          }

          this.className = Array.from(classes).join(' ');

          return force;
        }

        if (classes.has(nextToken)) {
          classes.delete(nextToken);
          this.className = Array.from(classes).join(' ');

          return false;
        }

        classes.add(nextToken);
        this.className = Array.from(classes).join(' ');

        return true;
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

    if (this.tagName === 'SELECT' && child.tagName === 'OPTION' && child.selected) {
      this.value = child.value;
    }

    return child;
  }

  removeChild(child) {
    this.children = this.children.filter((candidate) => candidate !== child);

    if (child.parentNode === this) {
      child.parentNode = null;
    }

    return child;
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);

    if (name === 'class') {
      this.className = String(value);
    }
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : '';
  }

  addEventListener(type, handler) {
    this.listeners[type] = handler;
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
  const span = new MockElement('span');
  span.textContent = label;
  button.appendChild(span);
  button._queryMap.span = span;

  return button;
}

function createLink(label) {
  const link = new MockElement('a');
  link.textContent = label;

  return link;
}

function createOption() {
  return new MockElement('option');
}

function createShell(config) {
  const shell = new MockElement('div');
  const drawer = new MockElement('aside');
  const openButton = createButton('Open');
  const closeButton = createButton('Close');
  const configNode = new MockElement('script');
  configNode.textContent = JSON.stringify(config);
  const threadSelect = new MockElement('select');
  threadSelect.value = config.threadId;
  const targetSelect = new MockElement('select');
  targetSelect.value = 'local';
  const promptInput = new MockElement('textarea');
  const analyzeButton = createButton('Analyze request');
  const createThreadButton = createButton('New thread');
  const duplicateThreadButton = createButton('Duplicate current');
  const renameThreadButton = createButton('Rename current');
  const clearThreadButton = createButton('Clear current');
  const deleteThreadButton = createButton('Delete current');
  const previewButton = createButton('Preview inline');
  previewButton.hidden = true;
  const applyButton = createButton('Apply inline');
  applyButton.hidden = true;
  const openDeckLink = createLink('Open Command Deck');
  const attachmentInput = new MockElement('input');
  const attachmentClearButton = createButton('Clear screenshot');
  attachmentClearButton.hidden = true;
  const attachmentPreview = new MockElement('div');
  attachmentPreview.hidden = true;
  const attachmentPreviewImage = new MockElement('img');
  const attachmentPreviewMeta = new MockElement('div');
  const resultBox = new MockElement('div');
  const resultSummary = new MockElement('div');
  const resultMeta = new MockElement('div');
  const statusNode = new MockElement('div');
  statusNode.textContent = config.labels.idleState;
  const threadLog = new MockElement('div');
  const threadEmpty = new MockElement('p');
  const reasonsWrap = new MockElement('div');
  const reasonsList = new MockElement('ul');
  const warningsWrap = new MockElement('div');
  const warningsList = new MockElement('ul');
  const workflowWrap = new MockElement('div');
  const workflowList = new MockElement('ol');
  const preflightWrap = new MockElement('div');
  const preflightNode = new MockElement('pre');
  const diffWrap = new MockElement('div');
  const diffNode = new MockElement('div');
  const existingWrap = new MockElement('div');
  const existingNode = new MockElement('pre');
  const proposedWrap = new MockElement('div');
  const proposedNode = new MockElement('pre');

  [
    drawer,
    openButton,
    closeButton,
    configNode,
    threadSelect,
    targetSelect,
    promptInput,
    analyzeButton,
    createThreadButton,
    duplicateThreadButton,
    renameThreadButton,
    clearThreadButton,
    deleteThreadButton,
    previewButton,
    applyButton,
    openDeckLink,
    attachmentInput,
    attachmentClearButton,
    attachmentPreview,
    attachmentPreviewImage,
    attachmentPreviewMeta,
    resultBox,
    resultSummary,
    resultMeta,
    statusNode,
    threadLog,
    threadEmpty,
    reasonsWrap,
    reasonsList,
    warningsWrap,
    warningsList,
    workflowWrap,
    workflowList,
    preflightWrap,
    preflightNode,
    diffWrap,
    diffNode,
    existingWrap,
    existingNode,
    proposedWrap,
    proposedNode,
  ].forEach((node) => shell.appendChild(node));

  shell._queryMap = {
    '.lcfa-editor-drawer': drawer,
    '[data-lcfa-editor-open]': openButton,
    '[data-lcfa-editor-close]': closeButton,
    '[data-lcfa-editor-config]': configNode,
    '[data-lcfa-editor-thread]': threadSelect,
    '[data-lcfa-editor-target]': targetSelect,
    '[data-lcfa-editor-prompt]': promptInput,
    '[data-lcfa-editor-analyze]': analyzeButton,
    '[data-lcfa-editor-thread-create]': createThreadButton,
    '[data-lcfa-editor-thread-duplicate]': duplicateThreadButton,
    '[data-lcfa-editor-thread-rename]': renameThreadButton,
    '[data-lcfa-editor-thread-clear]': clearThreadButton,
    '[data-lcfa-editor-thread-delete]': deleteThreadButton,
    '[data-lcfa-editor-preview]': previewButton,
    '[data-lcfa-editor-apply]': applyButton,
    '[data-lcfa-editor-open-deck]': openDeckLink,
    '[data-lcfa-editor-attachment]': attachmentInput,
    '[data-lcfa-editor-attachment-clear]': attachmentClearButton,
    '[data-lcfa-editor-attachment-preview]': attachmentPreview,
    '[data-lcfa-editor-attachment-preview-image]': attachmentPreviewImage,
    '[data-lcfa-editor-attachment-preview-meta]': attachmentPreviewMeta,
    '[data-lcfa-editor-result]': resultBox,
    '[data-lcfa-editor-result-summary]': resultSummary,
    '[data-lcfa-editor-result-meta]': resultMeta,
    '[data-lcfa-editor-status]': statusNode,
    '[data-lcfa-editor-thread-log]': threadLog,
    '[data-lcfa-editor-thread-empty]': threadEmpty,
    '[data-lcfa-editor-result-reasons-wrap]': reasonsWrap,
    '[data-lcfa-editor-result-reasons]': reasonsList,
    '[data-lcfa-editor-result-warnings-wrap]': warningsWrap,
    '[data-lcfa-editor-result-warnings]': warningsList,
    '[data-lcfa-editor-result-workflow-wrap]': workflowWrap,
    '[data-lcfa-editor-result-workflow]': workflowList,
    '[data-lcfa-editor-result-preflight-wrap]': preflightWrap,
    '[data-lcfa-editor-result-preflight]': preflightNode,
    '[data-lcfa-editor-result-diff-wrap]': diffWrap,
    '[data-lcfa-editor-result-diff]': diffNode,
    '[data-lcfa-editor-result-existing-wrap]': existingWrap,
    '[data-lcfa-editor-result-existing]': existingNode,
    '[data-lcfa-editor-result-proposed-wrap]': proposedWrap,
    '[data-lcfa-editor-result-proposed]': proposedNode,
  };

  return {
    shell,
    openButton,
    promptInput,
    analyzeButton,
    previewButton,
    applyButton,
    openDeckLink,
    attachmentInput,
    attachmentClearButton,
    attachmentPreview,
    attachmentPreviewImage,
    attachmentPreviewMeta,
    statusNode,
    diffNode,
    existingNode,
    proposedNode,
  };
}

function buildResponse(data, ok = true) {
  return {
    ok,
    text: async () => JSON.stringify(data),
  };
}

async function flush() {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
  await Promise.resolve();
}

(async () => {
  const analyzeBodies = [];
  const executionBodies = [];
  const executionPolls = [];
  const localStorageState = {};
  const documentListeners = {};

  const editorConfig = {
    postId: 42,
    targetId: 42,
    variant: '1',
    threadId: 'default',
    threadSummaries: [{ id: 'default', title: 'Default' }],
    threads: {
      default: {
        id: 'default',
        title: 'Default',
        state: 'idle',
        messages: [],
      },
    },
    defaultAction: 'page_upsert',
    restEndpoint: 'http://example.test/wp-json/lcfa/v1/chat/send',
    threadEndpoint: 'http://example.test/wp-json/lcfa/v1/chat/thread',
    commandEndpoint: 'http://example.test/wp-json/lcfa/v1/command',
    commandExecutionEndpoint: 'http://example.test/wp-json/lcfa/v1/command/execution',
    commandBaseUrl: 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=command',
    restNonce: 'nonce',
    labels: {
      idleState: 'Ready for a new request.',
      thinkingState: 'Analyzing request...',
      suggestedState: 'Suggestion ready. Review it or run it inline.',
      previewedState: 'Preview ready. Review the support details below.',
      appliedState: 'Inline action completed.',
      failedState: 'The current request failed. Review the support details below.',
      queuedState: 'Queued for inline execution.',
      runningState: 'Running inline execution...',
      previewSuggestion: 'Preview inline',
      applySuggestion: 'Apply inline',
      previewing: 'Preparing preview...',
      applying: 'Applying...',
      analyzing: 'Analyzing request...',
      analysisFailed: 'The request analysis failed.',
      applyFailed: 'The inline execution failed.',
    },
  };

  const shellNodes = createShell(editorConfig);

  const fetch = async (url, options = {}) => {
    const method = (options.method || 'GET').toUpperCase();

    if (url === editorConfig.restEndpoint && method === 'POST') {
      const body = JSON.parse(options.body || '{}');
      analyzeBodies.push(body);

      return buildResponse({
        thread: {
          id: 'default',
          title: 'Default',
          state: 'suggested',
          messages: [
            {
              role: 'user',
              label: 'You',
              time: '2026-04-17 10:00:00',
              content: body.user_prompt,
              attachments: body.attachments || [],
            },
            {
              role: 'suggestion_result',
              label: 'Suggestion ready',
              time: '2026-04-17 10:00:01',
              content: 'Suggested action: page_upsert.',
              meta: {
                action: 'page_upsert',
                execution_target: body.execution_target,
                attachment_count: (body.attachments || []).length,
              },
              actions: [
                {
                  kind: 'apply',
                  label: 'Preview inline',
                  payload: {
                    action: 'page_upsert',
                    execution_target: body.execution_target,
                    target_id: 42,
                    variant: '1',
                    dry_run: true,
                  },
                },
                {
                  kind: 'apply',
                  label: 'Apply inline',
                  payload: {
                    action: 'page_upsert',
                    execution_target: body.execution_target,
                    target_id: 42,
                    variant: '1',
                  },
                },
              ],
            },
          ],
        },
        suggestion: {
          summary: 'Suggested action ready.',
          confidence: 'high',
          suggested_payload: {
            action: 'page_upsert',
            execution_target: body.execution_target,
            target_id: 42,
            variant: '1',
          },
        },
      });
    }

    if (url === editorConfig.commandExecutionEndpoint && method === 'POST') {
      const body = JSON.parse(options.body || '{}');
      executionBodies.push(body);

      return buildResponse({
        execution: {
          id: body.dry_run ? 'exec-preview' : 'exec-apply',
          status: 'queued',
          mode: body.dry_run ? 'preview' : 'apply',
          action: body.action,
          execution_target: body.execution_target,
        },
      });
    }

    if (url.startsWith(editorConfig.commandExecutionEndpoint) && method === 'GET') {
      executionPolls.push(url);
      const isPreview = url.includes('exec-preview');

      return buildResponse({
        execution: {
          id: isPreview ? 'exec-preview' : 'exec-apply',
          status: 'completed',
          mode: isPreview ? 'preview' : 'apply',
          action: 'page_upsert',
          execution_target: 'local',
          result: {
            ok: true,
            summary: isPreview ? 'Preview generated.' : 'Page updated.',
            message: isPreview ? 'Preview generated.' : 'Page updated.',
            action: 'page_upsert',
            mode: isPreview ? 'preview' : 'apply',
            execution_target: 'local',
            diff_html: '<div class="diff">changed</div>',
            existing_html: '<section>old</section>',
            proposed_html: isPreview ? '<section>preview</section>' : '<section>applied</section>',
          },
          thread: {
            id: 'default',
            title: 'Default',
            state: isPreview ? 'previewed' : 'applied',
            messages: [
              {
                role: 'tool_result',
                label: isPreview ? 'Preview ready' : 'Applied',
                time: '2026-04-17 10:00:02',
                content: isPreview ? 'Preview generated.' : 'Page updated.',
                meta: {
                  ok: true,
                  mode: isPreview ? 'preview' : 'apply',
                  action: 'page_upsert',
                  execution_target: 'local',
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
    FileReader: class MockFileReader {
      readAsDataURL(file) {
        const result = file && file.dataURL ? file.dataURL : 'data:image/png;base64,AAAA';
        setTimeout(() => {
          this.result = result;
          if (typeof this.onload === 'function') {
            this.onload({ target: { result } });
          }
        }, 0);
      }
    },
    window: {
      fetch,
      location: { origin: 'http://example.test' },
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
        if (selector === '[data-lcfa-editor-shell]') {
          return shellNodes.shell;
        }

        return null;
      },
      addEventListener(type, handler) {
        documentListeners[type] = handler;
      },
      createElement(tagName) {
        return tagName === 'option' ? createOption() : new MockElement(tagName);
      },
    },
    setTimeout,
    clearTimeout,
  };

  context.window.document = context.document;

  vm.createContext(context);
  vm.runInContext(script, context);

  assert.strictEqual(shellNodes.shell.dataset.bound, '1', 'editor chat runtime should bind to the shell once');
  assert.strictEqual(localStorageState['lcfa-editor-thread:42'], 'default', 'editor chat runtime should persist the selected thread key for the current post');

  shellNodes.openButton.listeners.click({ target: shellNodes.openButton });
  assert.strictEqual(shellNodes.shell.classList.contains('is-open'), true, 'open button should toggle the drawer open');

  shellNodes.attachmentInput.files = [
    {
      name: 'pricing-reference.png',
      type: 'image/png',
      size: 128,
      dataURL: 'data:image/png;base64,AAAA',
    },
  ];
  shellNodes.attachmentInput.listeners.change({
    target: shellNodes.attachmentInput,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(shellNodes.attachmentPreview.hidden, false, 'attachment flow should reveal the screenshot preview');
  assert.strictEqual(shellNodes.attachmentPreviewImage.src, 'data:image/png;base64,AAAA', 'attachment flow should render the screenshot preview image');
  assert.ok(shellNodes.attachmentPreviewMeta.textContent.includes('pricing-reference.png'), 'attachment flow should render the screenshot metadata');

  shellNodes.promptInput.value = 'Refresh the pricing hero';
  shellNodes.analyzeButton.listeners.click({
    target: shellNodes.analyzeButton,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(analyzeBodies.length, 1, 'analyze flow should issue a chat/send request');
  assert.strictEqual(analyzeBodies[0].thread_id, 'default', 'analyze flow should preserve the selected thread id');
  assert.strictEqual(analyzeBodies[0].context_post_id, 42, 'analyze flow should send the current post as context');
  assert.strictEqual(analyzeBodies[0].attachments.length, 1, 'analyze flow should include the selected screenshot attachment');
  assert.strictEqual(shellNodes.previewButton.hidden, false, 'analyze flow should reveal the preview button');
  assert.strictEqual(shellNodes.applyButton.hidden, false, 'analyze flow should reveal the apply button');
  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'suggested', 'analyze flow should move the conversation to suggested state');
  assert.ok(shellNodes.openDeckLink.href.includes('suggest_action=page_upsert'), 'analyze flow should update the Command Deck deeplink with the suggested action');

  shellNodes.previewButton.listeners.click({
    target: shellNodes.previewButton,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(executionBodies.length, 1, 'preview flow should enqueue a command execution');
  assert.strictEqual(executionBodies[0].dry_run, true, 'preview flow should force dry_run');
  assert.strictEqual(executionBodies[0].context_post_id, 42, 'preview flow should restore the current post context');
  assert.strictEqual(executionBodies[0].post_id, 42, 'preview flow should keep the current post id');
  assert.strictEqual(executionBodies[0].target_id, 42, 'preview flow should preserve the current target id');
  assert.strictEqual(executionBodies[0].variant, '1', 'preview flow should preserve the current variant');
  assert.strictEqual(executionPolls.length, 1, 'preview flow should poll the queued execution until completion');
  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'previewed', 'preview flow should move the conversation to previewed state');
  assert.ok(shellNodes.diffNode.innerHTML.includes('changed'), 'preview flow should render diff support details');
  assert.strictEqual(shellNodes.existingNode.textContent, '<section>old</section>', 'preview flow should render the current markup support pane');
  assert.strictEqual(shellNodes.proposedNode.textContent, '<section>preview</section>', 'preview flow should render the proposed markup support pane');

  shellNodes.applyButton.listeners.click({
    target: shellNodes.applyButton,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(executionBodies.length, 2, 'apply flow should enqueue a second command execution');
  assert.strictEqual(Object.prototype.hasOwnProperty.call(executionBodies[1], 'dry_run'), false, 'apply flow should remove dry_run from the final payload');
  assert.strictEqual(executionBodies[1].thread_id, 'default', 'apply flow should preserve the selected thread id');
  assert.strictEqual(executionPolls.length, 2, 'apply flow should poll the queued execution until completion');
  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'applied', 'apply flow should move the conversation to applied state');
  assert.strictEqual(shellNodes.proposedNode.textContent, '<section>applied</section>', 'apply flow should refresh support details with the final applied markup');

  documentListeners.keydown({ key: 'Escape' });
  assert.strictEqual(shellNodes.shell.classList.contains('is-open'), false, 'Escape should close the drawer');

  console.log('PASS');
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});

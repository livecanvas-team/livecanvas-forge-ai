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
  icon.textContent = '';
  const span = new MockElement('span');
  span.textContent = label;
  button.appendChild(icon);
  button.appendChild(span);
  button._queryMap.span = icon;

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
  const analyzeButton = createButton('Send');
  analyzeButton.disabled = true;
  const createThreadButton = createButton('New thread');
  const duplicateThreadButton = createButton('Duplicate current');
  const renameThreadButton = createButton('Rename current');
  const clearThreadButton = createButton('Clear current');
  const deleteThreadButton = createButton('Delete current');
  const openDeckLink = createLink('Open Command Deck');
  const attachmentInput = new MockElement('input');
  const attachmentTriggerButton = createButton('Upload image');
  const attachmentClearButton = createButton('Remove image');
  attachmentClearButton.hidden = true;
  const attachmentPreviewCard = new MockElement('div');
  attachmentPreviewCard.hidden = true;
  const attachmentPreviewImage = new MockElement('img');
  const attachmentPreviewMeta = new MockElement('div');
  const sessionDetails = new MockElement('details');
  const supportDetails = new MockElement('details');
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
    openDeckLink,
    attachmentInput,
    attachmentTriggerButton,
    attachmentClearButton,
    attachmentPreviewCard,
    attachmentPreviewImage,
    attachmentPreviewMeta,
    sessionDetails,
    supportDetails,
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
    '[data-lcfa-editor-open-deck]': openDeckLink,
    '[data-lcfa-editor-attachment]': attachmentInput,
    '[data-lcfa-editor-attachment-trigger]': attachmentTriggerButton,
    '[data-lcfa-editor-attachment-clear]': attachmentClearButton,
    '[data-lcfa-editor-attachment-preview]': attachmentPreviewCard,
    '[data-lcfa-editor-attachment-preview-image]': attachmentPreviewImage,
    '[data-lcfa-editor-attachment-preview-meta]': attachmentPreviewMeta,
    '[data-lcfa-editor-session-details]': sessionDetails,
    '[data-lcfa-editor-support-details]': supportDetails,
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
    openDeckLink,
    attachmentInput,
    attachmentTriggerButton,
    attachmentClearButton,
    attachmentPreview: attachmentPreviewCard,
    attachmentPreviewImage,
    attachmentPreviewMeta,
    statusNode,
    threadLog,
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
  const liveCanvasRefreshCalls = [];
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
      thinkingState: 'Sending request...',
      suggestedState: 'Request prepared.',
      previewedState: 'Preview ready. Review the support details below.',
      appliedState: 'Change applied inline.',
      failedState: 'The current request failed. Review the support details below.',
      queuedState: 'Queued for inline execution.',
      runningState: 'Running inline execution...',
      previewSuggestion: 'Preview',
      applySuggestion: 'Apply',
      previewing: 'Preparing preview...',
      applying: 'Applying...',
      analyzing: 'Sending...',
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
                  label: 'Preview',
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
                  label: 'Apply',
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
      lc_editor_url_to_load: 'http://example.test/?page_id=42&lc_page_editing_mode=1',
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
  assert.strictEqual(shellNodes.attachmentPreview.hidden, true, 'editor chat runtime should keep the screenshot preview hidden until an image is attached');
  assert.strictEqual(shellNodes.analyzeButton.disabled, true, 'editor chat runtime should keep the primary action disabled until the request has content');
  assert.strictEqual(typeof shellNodes.attachmentTriggerButton.listeners.click, 'function', 'editor chat runtime should wire the upload button to the hidden image input');
  assert.strictEqual(shellNodes.attachmentClearButton.hidden, true, 'editor chat runtime should hide the remove-image control until an image exists');

  shellNodes.openButton.listeners.click({ target: shellNodes.openButton });
  assert.strictEqual(shellNodes.shell.classList.contains('is-open'), true, 'open button should toggle the drawer open');

  shellNodes.promptInput.value = 'Refresh the pricing hero';
  shellNodes.promptInput.listeners.input({
    target: shellNodes.promptInput,
    preventDefault() {},
  });
  assert.strictEqual(shellNodes.analyzeButton.disabled, false, 'editor chat runtime should enable the primary action as soon as the prompt has content');

  shellNodes.attachmentInput.files = [
    {
      name: 'pricing-reference.png',
      type: 'image/png',
      size: 128,
      dataURL: 'data:image/png;base64,AAAA',
    },
  ];
  shellNodes.attachmentTriggerButton.listeners.click({
    target: shellNodes.attachmentTriggerButton,
    preventDefault() {},
  });
  assert.strictEqual(shellNodes.attachmentInput.clicked, 1, 'attachment trigger should proxy clicks to the hidden image input');
  shellNodes.attachmentInput.listeners.change({
    target: shellNodes.attachmentInput,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(shellNodes.attachmentPreview.hidden, false, 'attachment flow should reveal the screenshot preview after an image is attached');
  assert.strictEqual(shellNodes.attachmentPreviewImage.src, 'data:image/png;base64,AAAA', 'attachment flow should render the selected screenshot preview image');
  assert.ok(shellNodes.attachmentPreviewMeta.textContent.includes('pricing-reference.png'), 'attachment flow should render the screenshot metadata');
  assert.strictEqual(shellNodes.attachmentClearButton.hidden, false, 'attachment flow should reveal the clear-screenshot control once an image is attached');

  shellNodes.attachmentPreviewImage.listeners.error({
    target: shellNodes.attachmentPreviewImage,
  });
  assert.strictEqual(shellNodes.attachmentPreview.hidden, true, 'broken screenshot previews should be hidden instead of leaving a broken image frame');
  assert.strictEqual(shellNodes.attachmentPreviewMeta.textContent, '', 'broken screenshot previews should clear the metadata copy');
  assert.strictEqual(shellNodes.attachmentClearButton.hidden, true, 'broken screenshot previews should also hide the remove-image control');
  assert.strictEqual(shellNodes.attachmentPreviewImage.src, '', 'broken screenshot previews should clear the image src');

  shellNodes.attachmentInput.files = [
    {
      name: 'pricing-reference.png',
      type: 'image/png',
      size: 128,
      dataURL: 'data:image/png;base64,BBBB',
    },
  ];
  shellNodes.attachmentInput.listeners.change({
    target: shellNodes.attachmentInput,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(shellNodes.attachmentPreview.hidden, false, 'attachment flow should recover after a second valid image upload');

  shellNodes.analyzeButton.listeners.click({
    target: shellNodes.analyzeButton,
    preventDefault() {},
  });
  await flush();

  assert.strictEqual(analyzeBodies.length, 1, 'analyze flow should issue a chat/send request');
  assert.strictEqual(analyzeBodies[0].thread_id, 'default', 'analyze flow should preserve the selected thread id');
  assert.strictEqual(analyzeBodies[0]._lcfa_origin, 'frontend_bridge', 'frontend chat requests should declare the browser bridge origin');
  assert.strictEqual(analyzeBodies[0]._lcfa_transport, 'browser_rest', 'frontend chat requests should declare browser REST transport');
  assert.strictEqual(analyzeBodies[0]._lcfa_agent, 'forge', 'frontend chat requests should declare Forge as the browser agent');
  assert.strictEqual(analyzeBodies[0]._lcfa_processed_by, 'forge_local_rules', 'frontend chat requests should declare the local Forge processor');
  assert.strictEqual(analyzeBodies[0].context_post_id, 42, 'analyze flow should send the current post as context');
  assert.strictEqual(analyzeBodies[0].attachments.length, 1, 'analyze flow should include the selected screenshot attachment');
  assert.strictEqual(executionBodies.length, 1, 'send flow should enqueue an inline execution immediately after a suggestion is prepared');
  assert.strictEqual(Object.prototype.hasOwnProperty.call(executionBodies[0], 'dry_run'), false, 'send flow should execute the inline apply payload directly');
  assert.strictEqual(executionBodies[0]._lcfa_origin, 'frontend_bridge', 'inline execution requests should preserve the browser bridge origin');
  assert.strictEqual(executionBodies[0]._lcfa_transport, 'browser_rest', 'inline execution requests should preserve browser REST transport');
  assert.strictEqual(executionBodies[0]._lcfa_agent, 'forge', 'inline execution requests should preserve Forge as the browser agent');
  assert.strictEqual(executionBodies[0]._lcfa_processed_by, 'forge_local_rules', 'inline execution requests should preserve the local Forge processor');
  assert.strictEqual(executionBodies[0].context_post_id, 42, 'send flow should restore the current post context');
  assert.strictEqual(executionBodies[0].post_id, 42, 'send flow should preserve the current post id');
  assert.strictEqual(executionBodies[0].target_id, 42, 'send flow should preserve the current target id');
  assert.strictEqual(executionBodies[0].variant, '1', 'send flow should preserve the current variant');
  assert.strictEqual(executionPolls.length, 1, 'send flow should poll the queued inline execution until completion');
  assert.strictEqual(shellNodes.analyzeButton.children[shellNodes.analyzeButton.children.length - 1].textContent, 'Send', 'editor chat runtime should restore the primary action label after inline execution completes');
  assert.strictEqual(shellNodes.statusNode.getAttribute('data-state'), 'applied', 'send flow should move the conversation directly to applied state');
  assert.ok(shellNodes.openDeckLink.href.includes('suggest_action=page_upsert'), 'analyze flow should update the Command Deck deeplink with the suggested action');
  assert.ok(shellNodes.diffNode.innerHTML.includes('changed'), 'send flow should render diff support details from the inline execution');
  assert.strictEqual(shellNodes.existingNode.textContent, '<section>old</section>', 'send flow should render the current markup support pane');
  assert.strictEqual(shellNodes.proposedNode.textContent, '<section>applied</section>', 'send flow should render the final applied markup support pane');
  assert.strictEqual(shellNodes.threadLog.children.length, 1, 'editor chat runtime should suppress suggestion-only messages in the frontend drawer thread');
  assert.strictEqual(shellNodes.threadLog.children[0].className, 'lcfa-editor-thread-message is-tool_result', 'editor chat runtime should keep only the execution result message in the frontend drawer thread');
  assert.strictEqual(liveCanvasRefreshCalls.length, 1, 'send flow should resync the LiveCanvas editor after a successful inline apply');
  assert.ok(liveCanvasRefreshCalls[0].includes('lcfa_refresh='), 'send flow should cache-bust the LiveCanvas editor refresh URL');

  documentListeners.keydown({ key: 'Escape' });
  assert.strictEqual(shellNodes.shell.classList.contains('is-open'), false, 'Escape should close the drawer');

  console.log('PASS');
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});

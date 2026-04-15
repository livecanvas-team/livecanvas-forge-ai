const fs = require('fs');
const vm = require('vm');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js',
  'utf8'
);

let clickHandler = null;
let execCommandCalled = null;
let appendedTextarea = null;

function MockElement() {}

const button = {
  textContent: 'Copy shortcut',
  getAttribute(name) {
    const map = {
      'data-lcfa-copy-text': 'echo test',
      'data-lcfa-copy-label': 'Copy shortcut',
      'data-lcfa-copied-label': 'Copied',
    };

    return map[name] || '';
  },
};

const target = new MockElement();
target.closest = function closest(selector) {
  if (selector === '[data-lcfa-copy-text]') {
    return button;
  }

  return null;
};

const context = {
  window: {
    setTimeout(callback) {
      callback();
      return 1;
    },
  },
  navigator: {},
  document: {
    readyState: 'complete',
    body: {
      appendChild(node) {
        appendedTextarea = node;
      },
      removeChild(node) {
        if (appendedTextarea === node) {
          appendedTextarea = null;
        }
      },
    },
    querySelectorAll() {
      return [];
    },
    addEventListener(type, handler) {
      if (type === 'click') {
        clickHandler = handler;
      }
    },
    createElement(tagName) {
      if (tagName === 'textarea') {
        return {
          value: '',
          style: {},
          setAttribute() {},
          focus() {},
          select() {},
          setSelectionRange() {},
        };
      }

      return {
        innerHTML: '',
        firstElementChild: null,
      };
    },
    execCommand(command) {
      execCommandCalled = command;
      return true;
    },
  },
  Element: MockElement,
  console,
};

context.window.document = context.document;
context.window.navigator = context.navigator;
context.window.Element = MockElement;

vm.createContext(context);
vm.runInContext(script, context);

if (typeof clickHandler !== 'function') {
  console.error('admin.js should register a delegated click handler for copy buttons');
  process.exit(1);
}

clickHandler({
  target,
  preventDefault() {},
});

if (execCommandCalled !== 'copy') {
  console.error('copy buttons should fall back to document.execCommand("copy") when navigator.clipboard is unavailable');
  process.exit(1);
}

if (button.textContent !== 'Copy shortcut') {
  console.error('copy fallback should restore the original button label after feedback');
  process.exit(1);
}

if (appendedTextarea !== null) {
  console.error('copy fallback should remove the temporary textarea after copying');
  process.exit(1);
}

console.log('PASS');

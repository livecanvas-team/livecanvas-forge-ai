(function () {
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
        });
    } else {
        highlightBlocks(document);
    }
})();

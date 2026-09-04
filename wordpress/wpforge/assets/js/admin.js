/**
 * WPForge admin — copy-to-clipboard buttons.
 */
(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function () {
                fallbackCopy(text);
            });
        }
        fallbackCopy(text);
        return Promise.resolve();
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (e) {
            // noop
        }
        document.body.removeChild(ta);
    }

    function flashButton(btn) {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('wpforge-copied');
        setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('wpforge-copied');
        }, 1600);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var buttons = document.querySelectorAll('.wpforge-copy');
        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.getAttribute('data-target'));
                if (!target) {
                    return;
                }
                copyText(target.textContent.trim()).then(function () {
                    flashButton(btn);
                });
            });
        });
    });
})();
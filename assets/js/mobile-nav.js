/**
 * DigiWash — Mobile Navigation & Shared Dashboard JS
 * assets/js/mobile-nav.js
 *
 * Provides:
 *  - dw_toast()          — accessible toast notifications
 *  - dw_skeleton()       — skeleton loader HTML generator
 *  - dwBottomNav.init()  — activates bottom nav tab switching
 *  - dw_confirm()        — accessible confirm modal (promise-based)
 */

/* ── Toast ──────────────────────────────────────────────────── */
(function () {
    // Inject container once
    let _container = null;
    function getContainer() {
        if (!_container) {
            _container = document.createElement('div');
            _container.id = 'dw-toast-container';
            document.body.appendChild(_container);
        }
        return _container;
    }

    const ICONS = {
        success: 'check_circle',
        error:   'error',
        info:    'info',
        warning: 'warning'
    };

    /**
     * Show a toast notification.
     * @param {string} message
     * @param {'success'|'error'|'info'|'warning'} type
     * @param {number} duration  milliseconds (default 3500)
     */
    window.dw_toast = function (message, type = 'info', duration = 3500) {
        const container = getContainer();
        const toast = document.createElement('div');
        toast.className = `dw-toast ${type}`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<i class="material-icons-outlined">${ICONS[type] || 'info'}</i><span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('out');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        }, duration);
    };

    // Backwards compat — map old showToast calls if it's not already defined inline
    if (typeof window.showToast === 'undefined') {
        window.showToast = (msg, type = 'info') => window.dw_toast(msg, type);
    }
})();

/* ── Skeleton HTML generator ────────────────────────────────── */
window.dw_skeleton = function (rows = 3) {
    let html = '';
    for (let i = 0; i < rows; i++) {
        html += `
        <div class="skel-card">
            <div class="skeleton skel-line h-20 w-60" style="margin-bottom:12px;"></div>
            <div class="skeleton skel-line w-80"></div>
            <div class="skeleton skel-line w-40"></div>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <div class="skeleton skel-btn"></div>
                <div class="skeleton skel-btn"></div>
            </div>
        </div>`;
    }
    return html;
};

/* ── Bottom nav tab manager ─────────────────────────────────── */
window.dwBottomNav = (function () {
    /**
     * Initialise bottom nav.
     * @param {Object} config
     * @param {string} config.navId          — id of the .dw-bottom-nav element
     * @param {Function} config.onSwitch     — callback(tabId, navEl)
     * @param {string} [config.defaultTab]   — tab id to activate initially
     */
    function init({ navId, onSwitch, defaultTab } = {}) {
        const nav = document.getElementById(navId || 'dwBottomNav');
        if (!nav) return;

        nav.querySelectorAll('.bn-item[data-tab]').forEach(btn => {
            btn.addEventListener('click', function () {
                const tabId = this.dataset.tab;
                setActive(nav, tabId);
                if (typeof onSwitch === 'function') onSwitch(tabId, this);
            });
        });

        if (defaultTab) {
            setActive(nav, defaultTab);
        }
    }

    function setActive(nav, tabId) {
        nav.querySelectorAll('.bn-item').forEach(b => b.classList.remove('active'));
        const target = nav.querySelector(`.bn-item[data-tab="${tabId}"]`);
        if (target) target.classList.add('active');
    }

    function setBadge(tabId, count) {
        const btn = document.querySelector(`.bn-item[data-tab="${tabId}"]`);
        if (!btn) return;
        let badge = btn.querySelector('.bn-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'bn-badge';
                btn.style.position = 'relative';
                btn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
        } else {
            if (badge) badge.remove();
        }
    }

    return { init, setActive: (id) => setActive(document.getElementById('dwBottomNav'), id), setBadge };
})();

/* ── Promise-based confirm dialog ───────────────────────────── */
window.dw_confirm = function (title, message, confirmLabel = 'Confirm') {
    return new Promise((resolve) => {
        // Reuse existing confirmModal if present (legacy dashboards)
        const existing = document.getElementById('confirmModal');
        if (existing) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmSub').textContent   = message;
            const yes = document.getElementById('btnConfirmYes');
            yes.textContent = confirmLabel;
            const newYes = yes.cloneNode(true); // remove old listeners
            yes.parentNode.replaceChild(newYes, yes);
            newYes.onclick = () => {
                existing.classList.remove('open');
                resolve(true);
            };
            document.getElementById('confirmModal')
                .querySelectorAll('[onclick*="closeModal"]')
                .forEach(b => { b.onclick = () => { existing.classList.remove('open'); resolve(false); }; });
            existing.classList.add('open');
            return;
        }

        // Fallback — native confirm
        resolve(window.confirm(`${title}\n\n${message}`));
    });
};

/* ── Lazy section loader helper ─────────────────────────────── */
/**
 * Load a tab section with skeleton → fetch → render.
 * @param {string}   containerId   — id of the container element
 * @param {Function} fetchFn       — async function that returns { success, html }
 * @param {number}   skelRows      — number of skeleton cards to show
 */
window.dw_loadSection = async function (containerId, fetchFn, skelRows = 3) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML = dw_skeleton(skelRows);
    try {
        const result = await fetchFn();
        el.innerHTML = result;
    } catch (e) {
        el.innerHTML = `<div class="dw-empty"><i class="material-icons-outlined">cloud_off</i><p>Connection error</p><small>Pull to refresh</small></div>`;
    }
};

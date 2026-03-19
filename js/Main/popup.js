/**
 * POPUP.JS - Globális szép popup rendszer
 * Az alert() és confirm() hívások lecserélésére
 */
(function() {
    'use strict';

    // ===== POPUP OVERLAY LÉTREHOZÁSA =====
    function ensureOverlay() {
        let overlay = document.getElementById('bmb-popup-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'bmb-popup-overlay';
            overlay.className = 'bmb-popup-overlay';
            overlay.innerHTML = `
                <div class="bmb-popup-box" id="bmb-popup-box">
                    <div class="bmb-popup-icon" id="bmb-popup-icon"></div>
                    <div class="bmb-popup-title" id="bmb-popup-title"></div>
                    <div class="bmb-popup-message" id="bmb-popup-message"></div>
                    <div class="bmb-popup-buttons" id="bmb-popup-buttons"></div>
                </div>
            `;
            document.body.appendChild(overlay);

            // Overlay kattintásra bezárás (csak alert típusnál)
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay && overlay.dataset.type === 'alert') {
                    closePopup();
                }
            });
        }
        return overlay;
    }

    // ===== POPUP BEZÁRÁSA =====
    function closePopup(callback) {
        const overlay = document.getElementById('bmb-popup-overlay');
        const box = document.getElementById('bmb-popup-box');
        if (overlay) {
            box.classList.add('bmb-popup-closing');
            setTimeout(function() {
                overlay.classList.remove('bmb-popup-visible');
                box.classList.remove('bmb-popup-closing');
                if (typeof callback === 'function') callback();
            }, 200);
        }
    }

    // ===== POPUP MEGJELENÍTÉSE =====
    function showPopup(options) {
        const overlay = ensureOverlay();
        const box = document.getElementById('bmb-popup-box');
        const iconEl = document.getElementById('bmb-popup-icon');
        const titleEl = document.getElementById('bmb-popup-title');
        const messageEl = document.getElementById('bmb-popup-message');
        const buttonsEl = document.getElementById('bmb-popup-buttons');

        // Típusok: warning, error, info, confirm, success
        const type = options.type || 'info';
        overlay.dataset.type = options.isConfirm ? 'confirm' : 'alert';

        // Ikon beállítás
        const icons = {
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            error: '<i class="fas fa-times-circle"></i>',
            info: '<i class="fas fa-info-circle"></i>',
            confirm: '<i class="fas fa-question-circle"></i>',
            success: '<i class="fas fa-check-circle"></i>'
        };
        iconEl.innerHTML = icons[type] || icons.info;
        iconEl.className = 'bmb-popup-icon bmb-popup-icon-' + type;

        // Cím
        titleEl.textContent = options.title || '';
        titleEl.style.display = options.title ? 'block' : 'none';

        // Üzenet
        messageEl.textContent = options.message || '';

        // Gombok
        buttonsEl.innerHTML = '';

        if (options.isConfirm) {
            // Confirm: Igen + Mégse gombok
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'bmb-popup-btn bmb-popup-btn-cancel';
            cancelBtn.textContent = options.cancelText || 'Mégse';
            cancelBtn.addEventListener('click', function() {
                closePopup(function() {
                    if (typeof options.onCancel === 'function') options.onCancel();
                });
            });

            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'bmb-popup-btn bmb-popup-btn-confirm';
            confirmBtn.textContent = options.confirmText || 'Igen';
            confirmBtn.addEventListener('click', function() {
                closePopup(function() {
                    if (typeof options.onConfirm === 'function') options.onConfirm();
                });
            });

            buttonsEl.appendChild(cancelBtn);
            buttonsEl.appendChild(confirmBtn);
        } else {
            // Alert: Egyetlen OK gomb
            const okBtn = document.createElement('button');
            okBtn.className = 'bmb-popup-btn bmb-popup-btn-ok bmb-popup-btn-' + type;
            okBtn.textContent = options.okText || 'Rendben';
            okBtn.addEventListener('click', function() {
                closePopup(function() {
                    if (typeof options.onClose === 'function') options.onClose();
                });
            });
            buttonsEl.appendChild(okBtn);
        }

        // Box típus class
        box.className = 'bmb-popup-box bmb-popup-box-' + type;

        // Megjelenítés
        overlay.classList.add('bmb-popup-visible');

        // ESC billentyűre bezárás
        function onEsc(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', onEsc);
                if (options.isConfirm) {
                    closePopup(function() {
                        if (typeof options.onCancel === 'function') options.onCancel();
                    });
                } else {
                    closePopup(function() {
                        if (typeof options.onClose === 'function') options.onClose();
                    });
                }
            }
        }
        document.addEventListener('keydown', onEsc);
    }

    // ===== PUBLIKUS API =====

    /**
     * Figyelmeztetés (sárga)
     * BmbPopup.warning('Üzenet', 'Opcionális cím')
     */
    window.BmbPopup = {
        warning: function(message, title) {
            showPopup({ type: 'warning', message: message, title: title || 'Figyelem' });
        },

        error: function(message, title) {
            showPopup({ type: 'error', message: message, title: title || 'Hiba' });
        },

        info: function(message, title) {
            showPopup({ type: 'info', message: message, title: title || 'Információ' });
        },

        success: function(message, title) {
            showPopup({ type: 'success', message: message, title: title || 'Siker' });
        },

        confirm: function(message, onConfirm, onCancel, title) {
            showPopup({
                type: 'confirm',
                message: message,
                title: title || 'Megerősítés',
                isConfirm: true,
                onConfirm: onConfirm,
                onCancel: onCancel
            });
        }
    };
})();

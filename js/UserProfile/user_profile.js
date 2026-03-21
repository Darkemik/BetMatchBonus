// UserProfile JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Aktív menüpont kijelölése
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.profile-nav-item');

    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });

    // Form validáció
    const passwordForms = document.querySelector('form[method="POST"]');
    if (passwordForms && window.location.pathname.includes('change_password')) {
        passwordForms.addEventListener('submit', validatePasswordForm);
    }

    // Befizetési/Kifizetési összeg validáció
    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('input', validateAmount);
    });

    // Figyelmeztetések elrejtése
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.classList.contains('alert-success')) {
                alert.style.display = 'none';
            }
        }, 5000);
    });

    // Másolható tranzakció ID-k
    const transactionCodes = document.querySelectorAll('code');
    transactionCodes.forEach(code => {
        code.style.cursor = 'pointer';
        code.title = 'Kattintson a másoláshoz';
        code.addEventListener('click', function() {
            copyToClipboard(this.textContent);
        });
    });
});

function validatePasswordForm(event) {
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    if (newPassword.value.length < 8) {
        event.preventDefault();
        alert('A jelszó legalább 8 karakter hosszú kell legyen!');
        return false;
    }

    if (newPassword.value !== confirmPassword.value) {
        event.preventDefault();
        alert('Az új jelszavak nem egyeznek!');
        return false;
    }

    if (currentPassword.value === '') {
        event.preventDefault();
        alert('Kérjük, adja meg a jelenlegi jelszót!');
        return false;
    }

    return true;
}

function validateAmount(event) {
    const value = parseFloat(event.target.value);
    if (value < 1) {
        event.target.value = '';
        alert('Az összeg legalább 1 kell legyen!');
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Másolta: ' + text, 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showNotification('Másolta: ' + text, 'success');
    } catch (err) {
        console.error('Másolás sikertelen:', err);
    }
    document.body.removeChild(textarea);
}

function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show notification`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.maxWidth = '400px';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Adatok frissítésének megerősítése
document.addEventListener('submit', function(event) {
    if (event.target.querySelector('input[name="update_profile"]')) {
        if (!confirm('Biztos vagy benne? A módosítások nem vonhatóak vissza.')) {
            event.preventDefault();
        }
    }
});

// Táblázatok szortírozása (ha DataTables nem elérhető)
function initSimpleSorting() {
    const tables = document.querySelectorAll('.transaction-table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('thead th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(table, index));
        });
    });
}

function sortTable(table, columnIndex) {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const isAscending = table.dataset.sortAscending === 'true';
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Szám alapú szortírozás
        const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        }
        
        // Szöveg alapú szortírozás
        return isAscending ? bValue.localeCompare(aValue) : aValue.localeCompare(bValue);
    });
    
    rows.forEach(row => table.querySelector('tbody').appendChild(row));
    table.dataset.sortAscending = !isAscending;
}

// Inicializálás
if (document.querySelector('.transaction-table')) {
    initSimpleSorting();
}

// Gyors Összeg Gombók
document.addEventListener('DOMContentLoaded', function() {
    const quickAmountButtons = document.querySelectorAll('.quick-amount-btn');
    const amountInput = document.getElementById('amount');

    quickAmountButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const amount = this.getAttribute('data-amount');
            amountInput.value = amount;
            amountInput.focus();
            
            // Visual feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'translateY(-3px)';
            }, 100);
        });
    });
});

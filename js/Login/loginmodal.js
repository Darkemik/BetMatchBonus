document.addEventListener('DOMContentLoaded', function () {
    var switchBtn = document.getElementById('switchToRegister');
    if (switchBtn) {
        switchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if (loginModal) loginModal.hide();
            var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
            registerModal.show();
        });
    }
});

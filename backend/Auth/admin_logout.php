<?php
session_start();

unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_role']);

header('Location: /BetMatchBonus/frontend/Admin/admin_login.php');
exit;
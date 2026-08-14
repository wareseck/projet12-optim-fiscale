<?php
require_once __DIR__ . '/includes/auth.php';
demarrerSession();

if (isset($_SESSION['id_utilisateur'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit;

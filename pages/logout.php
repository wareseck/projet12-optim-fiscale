<?php
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
deconnecterUtilisateur();
header('Location: login.php');
exit;

<?php
/**
 * Connexion à la base de données MySQL via PDO
 * Projet 12 - Optimisation fiscale
 */

// --- Paramètres de connexion (à adapter si besoin) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'optim_fiscale');
define('DB_USER', 'root');
define('DB_PASS', '');       // vide par défaut sous XAMPP
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // requêtes préparées natives (sécurité)
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données : ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}

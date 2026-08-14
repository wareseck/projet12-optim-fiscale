<?php
/**
 * Gestion de l'authentification, des sessions et des rôles
 * Projet 12 - Optimisation fiscale
 */

require_once __DIR__ . '/../config/db.php';

// Durée d'inactivité avant expiration automatique de la session (en secondes)
define('SESSION_TIMEOUT', 1800); // 30 minutes

/**
 * Démarre la session de façon sécurisée (à appeler en tout début de chaque page)
 */
function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
        ]);
    }

    // Expiration automatique par inactivité
    if (isset($_SESSION['derniere_activite']) &&
        (time() - $_SESSION['derniere_activite'] > SESSION_TIMEOUT)) {
        deconnecterUtilisateur();
        header('Location: /pages/login.php?expire=1');
        exit;
    }
    $_SESSION['derniere_activite'] = time();
}

/**
 * Tente de connecter un utilisateur avec email + mot de passe
 * Retourne true si succès, false sinon
 */
function connecterUtilisateur(string $email, string $motDePasse): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role, actif
         FROM utilisateurs WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur || !$utilisateur['actif']) {
        return false;
    }

    if (!password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
        return false;
    }

    // Régénère l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);

    $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
    $_SESSION['nom_complet']    = $utilisateur['prenom'] . ' ' . $utilisateur['nom'];
    $_SESSION['role']           = $utilisateur['role'];
    $_SESSION['derniere_activite'] = time();

    // Met à jour la date de dernière connexion
    $maj = $pdo->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id_utilisateur = :id');
    $maj->execute(['id' => $utilisateur['id_utilisateur']]);

    enregistrerAudit('CONNEXION', 'utilisateurs', $utilisateur['id_utilisateur'], 'Connexion réussie');

    return true;
}

/**
 * Déconnecte l'utilisateur et détruit la session
 */
function deconnecterUtilisateur(): void
{
    if (isset($_SESSION['id_utilisateur'])) {
        enregistrerAudit('DECONNEXION', 'utilisateurs', $_SESSION['id_utilisateur'], 'Déconnexion');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Vérifie que l'utilisateur est connecté, sinon redirige vers login
 */
function exigerConnexion(): void
{
    demarrerSession();
    if (!isset($_SESSION['id_utilisateur'])) {
        header('Location: /pages/login.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur a l'un des rôles autorisés
 * @param string[] $rolesAutorises
 */
function exigerRole(array $rolesAutorises): void
{
    exigerConnexion();
    if (!in_array($_SESSION['role'], $rolesAutorises, true)) {
        http_response_code(403);
        die('Accès refusé : vous n\'avez pas les droits nécessaires pour cette page.');
    }
}

/**
 * Génère un token CSRF pour un formulaire
 */
function genererTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie la validité d'un token CSRF soumis
 */
function verifierTokenCSRF(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && $token !== null &&
        hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enregistre une action dans le journal d'audit
 */
function enregistrerAudit(string $action, ?string $table = null, ?int $idEnregistrement = null, ?string $details = null): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (id_utilisateur, action, table_concernee, id_enregistrement, details, adresse_ip)
         VALUES (:id_utilisateur, :action, :table_concernee, :id_enregistrement, :details, :ip)'
    );
    $stmt->execute([
        'id_utilisateur'    => $_SESSION['id_utilisateur'] ?? null,
        'action'            => $action,
        'table_concernee'   => $table,
        'id_enregistrement' => $idEnregistrement,
        'details'           => $details,
        'ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

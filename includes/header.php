<?php
/**
 * En-tête commun : à inclure après avoir démarré la session
 * Variable optionnelle $titrePage
 */
$titrePage = $titrePage ?? 'Optimisation Fiscale';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titrePage) ?> - Plateforme d'Optimisation Fiscale</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/projet12/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<?php if (isset($_SESSION['id_utilisateur'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/projet12/pages/dashboard.php">
            <i class="bi bi-calculator"></i> Optim'Fiscale
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/projet12/pages/dashboard.php">Tableau de bord</a></li>
                <li class="nav-item"><a class="nav-link" href="/projet12/pages/dossiers_liste.php">Dossiers</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/projet12/pages/parametres.php">Paramètres fiscaux</a></li>
                <li class="nav-item"><a class="nav-link" href="/projet12/pages/utilisateurs_liste.php">Utilisateurs</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-light me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['nom_complet']) ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($_SESSION['role']) ?></span>
            </span>
            <a href="/projet12/pages/logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-fluid py-4">
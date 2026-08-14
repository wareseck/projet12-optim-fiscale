<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerConnexion();

$pdo = getPDO();

// --- Paramètres de recherche / filtre / pagination ---
$recherche   = trim($_GET['q'] ?? '');
$statutFiltre = $_GET['statut'] ?? '';
$page        = max(1, (int) ($_GET['page'] ?? 1));
$parPage     = 20;
$offset      = ($page - 1) * $parPage;

$conditions = [];
$params = [];

if ($_SESSION['role'] === 'client') {
    $conditions[] = 'id_client = :id_client';
    $params['id_client'] = $_SESSION['id_utilisateur'];
}

if ($recherche !== '') {
    $conditions[] = '(nom_dossier LIKE :recherche OR nom_entreprise LIKE :recherche OR nom_dirigeant LIKE :recherche)';
    $params['recherche'] = '%' . $recherche . '%';
}

if ($statutFiltre !== '') {
    $conditions[] = 'statut = :statut';
    $params['statut'] = $statutFiltre;
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Compte total pour la pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM dossiers $whereSql");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $parPage));

// Récupération de la page courante
$sql = "SELECT * FROM dossiers $whereSql ORDER BY date_creation DESC LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $cle => $valeur) {
    $stmt->bindValue($cle, $valeur);
}
$stmt->bindValue('limite', $parPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$dossiers = $stmt->fetchAll();

$titrePage = 'Dossiers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-folder2-open"></i> Dossiers</h3>
    <a href="dossier_ajouter.php" class="btn btn-dark"><i class="bi bi-plus-circle"></i> Nouveau dossier</a>
</div>

<div class="card p-3 mb-3">
    <form method="get" class="row g-2">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control" placeholder="Rechercher (dossier, entreprise, dirigeant)"
                   value="<?= htmlspecialchars($recherche) ?>">
        </div>
        <div class="col-md-3">
            <select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <option value="brouillon" <?= $statutFiltre === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                <option value="en_cours" <?= $statutFiltre === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="finalise" <?= $statutFiltre === 'finalise' ? 'selected' : '' ?>>Finalisé</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-outline-dark w-100"><i class="bi bi-search"></i> Filtrer</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom du dossier</th>
                    <th>Entreprise</th>
                    <th>Dirigeant</th>
                    <th>Exercice</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($dossiers)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucun résultat.</td></tr>
            <?php endif; ?>
            <?php foreach ($dossiers as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nom_dossier']) ?></td>
                    <td><?= htmlspecialchars($d['nom_entreprise'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['nom_dirigeant'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['exercice']) ?></td>
                    <td>
                        <span class="badge bg-<?= $d['statut'] === 'finalise' ? 'success' : ($d['statut'] === 'en_cours' ? 'warning text-dark' : 'secondary') ?>">
                            <?= htmlspecialchars($d['statut']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="dossier_voir.php?id=<?= $d['id_dossier'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-eye"></i></a>
                        <a href="dossier_modifier.php?id=<?= $d['id_dossier'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="dossier_supprimer.php?id=<?= $d['id_dossier'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Supprimer définitivement ce dossier ?');"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination justify-content-center mt-3">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($recherche) ?>&statut=<?= urlencode($statutFiltre) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerConnexion();

$pdo = getPDO();

// Filtre : si client, ne voit que ses dossiers ; sinon tout ou par conseiller
if ($_SESSION['role'] === 'client') {
    $stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id_client = :id ORDER BY date_creation DESC');
    $stmt->execute(['id' => $_SESSION['id_utilisateur']]);
} else {
    $stmt = $pdo->query('SELECT * FROM dossiers ORDER BY date_creation DESC');
}
$dossiers = $stmt->fetchAll();

// KPI globaux
$nbDossiers = count($dossiers);
$nbFinalises = count(array_filter($dossiers, fn($d) => $d['statut'] === 'finalise'));
$nbEnCours = count(array_filter($dossiers, fn($d) => $d['statut'] === 'en_cours'));

// Dernières simulations pour un graphique de comparaison (sur le dossier le plus récent avec résultats)
$dernierGraphique = null;
if ($nbDossiers > 0) {
    $stmt2 = $pdo->prepare(
        'SELECT s.libelle, r.cout_total_entreprise, r.remuneration_nette_dirigeant
         FROM resultats_scenarios r
         JOIN scenarios s ON s.id_scenario = r.id_scenario
         WHERE r.id_dossier = :id
         ORDER BY r.id_resultat DESC'
    );
    $stmt2->execute(['id' => $dossiers[0]['id_dossier']]);
    $dernierGraphique = $stmt2->fetchAll();
}

$titrePage = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-speedometer2"></i> Tableau de bord</h3>
    <a href="dossier_ajouter.php" class="btn btn-dark"><i class="bi bi-plus-circle"></i> Nouveau dossier</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-1">
            <div class="small">Dossiers au total</div>
            <h3><?= $nbDossiers ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-2">
            <div class="small">Dossiers finalisés</div>
            <h3><?= $nbFinalises ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-3">
            <div class="small">En cours d'étude</div>
            <h3><?= $nbEnCours ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card bg-kpi-4">
            <div class="small">Taux de finalisation</div>
            <h3><?= $nbDossiers > 0 ? round($nbFinalises / $nbDossiers * 100) : 0 ?>%</h3>
        </div>
    </div>
</div>

<?php if ($dernierGraphique): ?>
<div class="card p-3 mb-4">
    <h5>Comparaison des scénarios — dossier le plus récent : <?= htmlspecialchars($dossiers[0]['nom_dossier']) ?></h5>
    <canvas id="graphComparaison" height="90"></canvas>
</div>
<?php endif; ?>

<div class="card p-3">
    <h5 class="mb-3">Mes dossiers</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nom du dossier</th>
                    <th>Entreprise</th>
                    <th>Exercice</th>
                    <th>Statut</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($dossiers)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucun dossier pour le moment.</td></tr>
            <?php endif; ?>
            <?php foreach ($dossiers as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nom_dossier']) ?></td>
                    <td><?= htmlspecialchars($d['nom_entreprise'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['exercice']) ?></td>
                    <td>
                        <span class="badge bg-<?= $d['statut'] === 'finalise' ? 'success' : ($d['statut'] === 'en_cours' ? 'warning text-dark' : 'secondary') ?>">
                            <?= htmlspecialchars($d['statut']) ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($d['date_creation'])) ?></td>
                    <td>
                        <a href="dossier_voir.php?id=<?= $d['id_dossier'] ?>" class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($dernierGraphique): ?>
<script>
const ctx = document.getElementById('graphComparaison');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($dernierGraphique, 'libelle')) ?>,
        datasets: [
            {
                label: "Coût total pour l'entreprise (FCFA)",
                data: <?= json_encode(array_column($dernierGraphique, 'cout_total_entreprise')) ?>,
                backgroundColor: '#c0392b'
            },
            {
                label: "Rémunération nette du dirigeant (FCFA)",
                data: <?= json_encode(array_column($dernierGraphique, 'remuneration_nette_dirigeant')) ?>,
                backgroundColor: '#27ae60'
            }
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerRole(['admin']);

$pdo = getPDO();
$message = '';

// --- Mise à jour d'un paramètre existant ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
    if (isset($_POST['id_parametre'])) {
        $stmt = $pdo->prepare('UPDATE parametres_fiscaux SET valeur = :valeur WHERE id_parametre = :id');
        $stmt->execute(['valeur' => (float) $_POST['valeur'], 'id' => (int) $_POST['id_parametre']]);
        enregistrerAudit('MODIFICATION_PARAMETRE', 'parametres_fiscaux', (int) $_POST['id_parametre'], 'Taux mis à jour : ' . $_POST['valeur']);
        $message = 'Paramètre mis à jour avec succès.';
    }
}

$exercice = (int) ($_GET['exercice'] ?? date('Y'));
$stmt = $pdo->prepare('SELECT * FROM parametres_fiscaux WHERE exercice = :ex ORDER BY libelle');
$stmt->execute(['ex' => $exercice]);
$parametres = $stmt->fetchAll();

$stmtBareme = $pdo->prepare('SELECT * FROM bareme_ir WHERE exercice = :ex ORDER BY borne_inferieure');
$stmtBareme->execute(['ex' => $exercice]);
$bareme = $stmtBareme->fetchAll();

$titrePage = 'Paramètres fiscaux';
require_once __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4"><i class="bi bi-sliders"></i> Paramètres fiscaux et sociaux — Exercice <?= $exercice ?></h3>
<p class="text-muted">Ces taux alimentent tous les calculs de la plateforme. Modifie-les ici pour refléter la réglementation en vigueur — aucune modification de code n'est nécessaire.</p>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="card p-3 mb-4">
    <h5>Taux et montants</h5>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Paramètre</th><th>Valeur actuelle</th><th>Modifier</th></tr></thead>
            <tbody>
            <?php foreach ($parametres as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['libelle']) ?> <code class="text-muted"><?= htmlspecialchars($p['cle']) ?></code></td>
                    <td><?= $p['unite'] === 'taux' ? formaterPourcentage((float) $p['valeur']) : formaterMontant((float) $p['valeur']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">
                            <input type="hidden" name="id_parametre" value="<?= $p['id_parametre'] ?>">
                            <input type="number" step="0.0001" name="valeur" value="<?= $p['valeur'] ?>" class="form-control form-control-sm" style="max-width:150px;">
                            <button type="submit" class="btn btn-sm btn-outline-dark">Enregistrer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h5>Barème IR progressif</h5>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Borne inférieure</th><th>Borne supérieure</th><th>Taux</th></tr></thead>
            <tbody>
            <?php foreach ($bareme as $t): ?>
                <tr>
                    <td><?= formaterMontant((float) $t['borne_inferieure']) ?></td>
                    <td><?= $t['borne_superieure'] !== null ? formaterMontant((float) $t['borne_superieure']) : 'Illimité' ?></td>
                    <td><?= formaterPourcentage((float) $t['taux']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

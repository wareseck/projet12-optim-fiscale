<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerRole(['admin', 'conseiller']); // seuls admin/conseiller créent un dossier

$pdo = getPDO();
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreurs[] = 'Session expirée, merci de réessayer.';
    } else {
        $nomDossier    = trim($_POST['nom_dossier'] ?? '');
        $nomEntreprise = trim($_POST['nom_entreprise'] ?? '');
        $nomDirigeant  = trim($_POST['nom_dirigeant'] ?? '');
        $exercice      = (int) ($_POST['exercice'] ?? date('Y'));
        $ca            = (float) str_replace(' ', '', $_POST['chiffre_affaires'] ?? '0');
        $charges       = (float) str_replace(' ', '', $_POST['charges'] ?? '0');
        $netCible      = $_POST['net_cible'] !== '' ? (float) str_replace(' ', '', $_POST['net_cible']) : null;

        // --- Validation serveur ---
        if ($nomDossier === '') $erreurs[] = 'Le nom du dossier est obligatoire.';
        if ($ca <= 0) $erreurs[] = 'Le chiffre d\'affaires doit être positif.';
        if ($charges < 0) $erreurs[] = 'Les charges ne peuvent pas être négatives.';
        if ($charges >= $ca) $erreurs[] = 'Les charges doivent être inférieures au chiffre d\'affaires.';

        if (empty($erreurs)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO dossiers (id_utilisateur, id_client, nom_dossier, nom_entreprise, nom_dirigeant, exercice, statut)
                     VALUES (:id_utilisateur, :id_client, :nom_dossier, :nom_entreprise, :nom_dirigeant, :exercice, :statut)'
                );
                $stmt->execute([
                    'id_utilisateur' => $_SESSION['id_utilisateur'],
                    'id_client'      => null,
                    'nom_dossier'    => $nomDossier,
                    'nom_entreprise' => $nomEntreprise ?: null,
                    'nom_dirigeant'  => $nomDirigeant ?: null,
                    'exercice'       => $exercice,
                    'statut'         => 'en_cours',
                ]);
                $idDossier = (int) $pdo->lastInsertId();

                $stmt2 = $pdo->prepare(
                    'INSERT INTO hypotheses (id_dossier, chiffre_affaires, charges_hors_remuneration, remuneration_nette_cible)
                     VALUES (:id_dossier, :ca, :charges, :net_cible)'
                );
                $stmt2->execute([
                    'id_dossier' => $idDossier,
                    'ca'         => $ca,
                    'charges'    => $charges,
                    'net_cible'  => $netCible,
                ]);

                $pdo->commit();
                enregistrerAudit('CREATION_DOSSIER', 'dossiers', $idDossier, "Dossier « $nomDossier » créé");

                header('Location: dossier_voir.php?id=' . $idDossier);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
            }
        }
    }
}

$titrePage = 'Nouveau dossier';
require_once __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4"><i class="bi bi-plus-circle"></i> Nouveau dossier d'optimisation fiscale</h3>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card p-4">

            <?php foreach ($erreurs as $erreur): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">

                <div class="mb-3">
                    <label class="form-label">Nom du dossier *</label>
                    <input type="text" name="nom_dossier" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nom_dossier'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nom de l'entreprise</label>
                        <input type="text" name="nom_entreprise" class="form-control"
                               value="<?= htmlspecialchars($_POST['nom_entreprise'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nom du dirigeant</label>
                        <input type="text" name="nom_dirigeant" class="form-control"
                               value="<?= htmlspecialchars($_POST['nom_dirigeant'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Exercice fiscal *</label>
                    <input type="number" name="exercice" class="form-control" required
                           min="2020" max="2030" value="<?= htmlspecialchars($_POST['exercice'] ?? date('Y')) ?>">
                </div>

                <hr>
                <h6 class="text-muted">Hypothèses financières</h6>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Chiffre d'affaires annuel (FCFA) *</label>
                        <input type="number" step="0.01" name="chiffre_affaires" class="form-control" required min="1"
                               value="<?= htmlspecialchars($_POST['chiffre_affaires'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Charges hors rémunération dirigeant (FCFA) *</label>
                        <input type="number" step="0.01" name="charges" class="form-control" required min="0"
                               value="<?= htmlspecialchars($_POST['charges'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rémunération nette annuelle visée par le dirigeant (FCFA)</label>
                    <input type="number" step="0.01" name="net_cible" class="form-control"
                           value="<?= htmlspecialchars($_POST['net_cible'] ?? '') ?>"
                           placeholder="Optionnel — utilisé pour l'optimisation du mix salaire/dividende">
                </div>

                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-check-circle"></i> Créer le dossier et lancer les simulations
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

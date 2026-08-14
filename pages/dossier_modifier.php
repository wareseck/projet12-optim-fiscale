<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerRole(['admin', 'conseiller']);

$pdo = getPDO();
$idDossier = (int) ($_GET['id'] ?? $_POST['id_dossier'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id_dossier = :id');
$stmt->execute(['id' => $idDossier]);
$dossier = $stmt->fetch();
if (!$dossier) { die('Dossier introuvable.'); }

$stmtH = $pdo->prepare('SELECT * FROM hypotheses WHERE id_dossier = :id ORDER BY id_hypothese DESC LIMIT 1');
$stmtH->execute(['id' => $idDossier]);
$hypotheses = $stmtH->fetch();

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreurs[] = 'Session expirée, merci de réessayer.';
    } else {
        $nomDossier    = trim($_POST['nom_dossier'] ?? '');
        $nomEntreprise = trim($_POST['nom_entreprise'] ?? '');
        $nomDirigeant  = trim($_POST['nom_dirigeant'] ?? '');
        $exercice      = (int) ($_POST['exercice'] ?? date('Y'));
        $statut        = $_POST['statut'] ?? 'en_cours';
        $ca            = (float) str_replace(' ', '', $_POST['chiffre_affaires'] ?? '0');
        $charges       = (float) str_replace(' ', '', $_POST['charges'] ?? '0');
        $netCible      = $_POST['net_cible'] !== '' ? (float) str_replace(' ', '', $_POST['net_cible']) : null;

        if ($nomDossier === '') $erreurs[] = 'Le nom du dossier est obligatoire.';
        if ($ca <= 0) $erreurs[] = 'Le chiffre d\'affaires doit être positif.';
        if ($charges >= $ca) $erreurs[] = 'Les charges doivent être inférieures au chiffre d\'affaires.';
        if (!in_array($statut, ['brouillon', 'en_cours', 'finalise'], true)) $erreurs[] = 'Statut invalide.';

        if (empty($erreurs)) {
            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare(
                    'UPDATE dossiers SET nom_dossier = :nom_dossier, nom_entreprise = :nom_entreprise,
                     nom_dirigeant = :nom_dirigeant, exercice = :exercice, statut = :statut
                     WHERE id_dossier = :id'
                );
                $upd->execute([
                    'nom_dossier' => $nomDossier, 'nom_entreprise' => $nomEntreprise ?: null,
                    'nom_dirigeant' => $nomDirigeant ?: null, 'exercice' => $exercice,
                    'statut' => $statut, 'id' => $idDossier,
                ]);

                $updH = $pdo->prepare(
                    'UPDATE hypotheses SET chiffre_affaires = :ca, charges_hors_remuneration = :charges,
                     remuneration_nette_cible = :net_cible WHERE id_hypothese = :id'
                );
                $updH->execute([
                    'ca' => $ca, 'charges' => $charges, 'net_cible' => $netCible,
                    'id' => $hypotheses['id_hypothese'],
                ]);

                $pdo->commit();
                enregistrerAudit('MODIFICATION_DOSSIER', 'dossiers', $idDossier, "Dossier « $nomDossier » modifié");

                // Envoi d'un email automatique si le dossier vient d'être finalisé
                if ($statut === 'finalise' && $dossier['statut'] !== 'finalise' && $dossier['id_client']) {
                    require_once __DIR__ . '/../includes/email.php';
                    $stmtClient = $pdo->prepare('SELECT email, prenom, nom FROM utilisateurs WHERE id_utilisateur = :id');
                    $stmtClient->execute(['id' => $dossier['id_client']]);
                    if ($client = $stmtClient->fetch()) {
                        envoyerEmailFinalisationDossier($client['email'], $client['prenom'], $nomDossier);
                    }
                }

                header('Location: dossier_voir.php?id=' . $idDossier);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

$titrePage = 'Modifier le dossier';
require_once __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4"><i class="bi bi-pencil"></i> Modifier le dossier</h3>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card p-4">
            <?php foreach ($erreurs as $erreur): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">
                <input type="hidden" name="id_dossier" value="<?= $idDossier ?>">

                <div class="mb-3">
                    <label class="form-label">Nom du dossier *</label>
                    <input type="text" name="nom_dossier" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nom_dossier'] ?? $dossier['nom_dossier']) ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Entreprise</label>
                        <input type="text" name="nom_entreprise" class="form-control"
                               value="<?= htmlspecialchars($_POST['nom_entreprise'] ?? $dossier['nom_entreprise']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dirigeant</label>
                        <input type="text" name="nom_dirigeant" class="form-control"
                               value="<?= htmlspecialchars($_POST['nom_dirigeant'] ?? $dossier['nom_dirigeant']) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Exercice *</label>
                        <input type="number" name="exercice" class="form-control" required
                               value="<?= htmlspecialchars($_POST['exercice'] ?? $dossier['exercice']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Statut *</label>
                        <select name="statut" class="form-select">
                            <?php foreach (['brouillon', 'en_cours', 'finalise'] as $s): ?>
                                <option value="<?= $s ?>" <?= $dossier['statut'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr>
                <h6 class="text-muted">Hypothèses financières</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Chiffre d'affaires (FCFA) *</label>
                        <input type="number" step="0.01" name="chiffre_affaires" class="form-control" required
                               value="<?= htmlspecialchars($_POST['chiffre_affaires'] ?? $hypotheses['chiffre_affaires']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Charges hors rémunération (FCFA) *</label>
                        <input type="number" step="0.01" name="charges" class="form-control" required
                               value="<?= htmlspecialchars($_POST['charges'] ?? $hypotheses['charges_hors_remuneration']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rémunération nette cible (FCFA)</label>
                    <input type="number" step="0.01" name="net_cible" class="form-control"
                           value="<?= htmlspecialchars($_POST['net_cible'] ?? $hypotheses['remuneration_nette_cible']) ?>">
                </div>

                <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-circle"></i> Enregistrer les modifications</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

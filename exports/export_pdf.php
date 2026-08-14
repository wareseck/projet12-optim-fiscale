<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerConnexion();

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die(
        "La librairie Dompdf n'est pas installée.<br>" .
        "Ouvre un terminal dans le dossier <code>php/</code> et exécute :<br>" .
        "<code>composer require dompdf/dompdf</code><br>" .
        "(voir le README à la racine du projet pour le détail)"
    );
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = getPDO();
$idDossier = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id_dossier = :id');
$stmt->execute(['id' => $idDossier]);
$dossier = $stmt->fetch();
if (!$dossier) { die('Dossier introuvable.'); }

if ($_SESSION['role'] === 'client' && (int) $dossier['id_client'] !== (int) $_SESSION['id_utilisateur']) {
    http_response_code(403);
    die('Accès refusé.');
}

$stmtH = $pdo->prepare('SELECT * FROM hypotheses WHERE id_dossier = :id ORDER BY id_hypothese DESC LIMIT 1');
$stmtH->execute(['id' => $idDossier]);
$hypotheses = $stmtH->fetch();

$stmtR = $pdo->prepare(
    'SELECT s.libelle, r.* FROM resultats_scenarios r
     JOIN scenarios s ON s.id_scenario = r.id_scenario
     WHERE r.id_dossier = :id ORDER BY r.remuneration_nette_dirigeant DESC'
);
$stmtR->execute(['id' => $idDossier]);
$resultats = $stmtR->fetchAll();

enregistrerAudit('EXPORT_PDF', 'dossiers', $idDossier, 'Export PDF du rapport comparatif');

// --- Construction du HTML du rapport ---
ob_start();
?>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; color: #2c3e50; }
    h2 { font-size: 14px; color: #2c3e50; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: right; }
    th { background-color: #2c3e50; color: white; }
    td:first-child, th:first-child { text-align: left; }
    .meilleur { background-color: #d4efdf; font-weight: bold; }
    .footer { margin-top: 30px; font-size: 9px; color: #888; }
</style>
</head>
<body>
    <h1>Rapport d'optimisation fiscale</h1>
    <p>
        <strong>Dossier :</strong> <?= htmlspecialchars($dossier['nom_dossier']) ?><br>
        <strong>Entreprise :</strong> <?= htmlspecialchars($dossier['nom_entreprise'] ?? '—') ?><br>
        <strong>Dirigeant :</strong> <?= htmlspecialchars($dossier['nom_dirigeant'] ?? '—') ?><br>
        <strong>Exercice :</strong> <?= htmlspecialchars($dossier['exercice']) ?><br>
        <strong>Date d'édition :</strong> <?= date('d/m/Y') ?>
    </p>

    <h2>Hypothèses financières</h2>
    <table>
        <tr><th>Chiffre d'affaires</th><td><?= formaterMontant((float) $hypotheses['chiffre_affaires']) ?></td></tr>
        <tr><th>Charges hors rémunération</th><td><?= formaterMontant((float) $hypotheses['charges_hors_remuneration']) ?></td></tr>
        <tr><th>Résultat avant rémunération</th><td><?= formaterMontant((float) $hypotheses['resultat_avant_remuneration']) ?></td></tr>
        <tr><th>Rémunération nette cible</th><td><?= $hypotheses['remuneration_nette_cible'] ? formaterMontant((float) $hypotheses['remuneration_nette_cible']) : 'Non définie' ?></td></tr>
    </table>

    <h2>Comparatif des scénarios</h2>
    <table>
        <tr>
            <th>Scénario</th><th>IS dû</th><th>IR dirigeant</th><th>Cotisations</th>
            <th>Coût total entreprise</th><th>Net dirigeant</th><th>Taux global</th>
        </tr>
        <?php foreach ($resultats as $i => $r): ?>
        <tr class="<?= $i === 0 ? 'meilleur' : '' ?>">
            <td><?= htmlspecialchars($r['libelle']) ?><?= $i === 0 ? ' (optimal)' : '' ?></td>
            <td><?= formaterMontant((float) $r['is_du']) ?></td>
            <td><?= formaterMontant((float) $r['ir_dirigeant']) ?></td>
            <td><?= formaterMontant((float) $r['cotisations_ipres'] + (float) $r['cotisations_css']) ?></td>
            <td><?= formaterMontant((float) $r['cout_total_entreprise']) ?></td>
            <td><?= formaterMontant((float) $r['remuneration_nette_dirigeant']) ?></td>
            <td><?= formaterPourcentage((float) $r['taux_prelevement_global']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p class="footer">
        Rapport généré automatiquement par la plateforme Optim'Fiscale — Projet Master CCA, ESP Dakar.
        Les taux utilisés sont paramétrables et doivent être vérifiés avant toute décision réelle.
    </p>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('rapport_' . $idDossier . '.pdf', ['Attachment' => false]);

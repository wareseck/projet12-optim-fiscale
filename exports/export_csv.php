<?php
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
exigerConnexion();

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

$stmtR = $pdo->prepare(
    'SELECT s.libelle, r.remuneration_brute, r.dividendes_bruts, r.is_du, r.ir_dirigeant,
            r.cotisations_ipres, r.cotisations_css, r.irvm_dividendes,
            r.cout_total_entreprise, r.remuneration_nette_dirigeant, r.taux_prelevement_global
     FROM resultats_scenarios r
     JOIN scenarios s ON s.id_scenario = r.id_scenario
     WHERE r.id_dossier = :id'
);
$stmtR->execute(['id' => $idDossier]);
$resultats = $stmtR->fetchAll();

enregistrerAudit('EXPORT_CSV', 'dossiers', $idDossier, 'Export CSV du comparatif des scénarios');

$nomFichier = 'comparatif_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $dossier['nom_dossier']) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$sortie = fopen('php://output', 'w');
// BOM UTF-8 pour un affichage correct des accents dans Excel
fwrite($sortie, "\xEF\xBB\xBF");

fputcsv($sortie, [
    'Scenario', 'Remuneration brute', 'Dividendes bruts', 'IS du', 'IR dirigeant',
    'Cotisations IPRES', 'Cotisations CSS/employeur', 'IRVM dividendes',
    'Cout total entreprise', 'Remuneration nette dirigeant', 'Taux prelevement global',
], ';');

foreach ($resultats as $r) {
    fputcsv($sortie, [
        $r['libelle'],
        $r['remuneration_brute'],
        $r['dividendes_bruts'],
        $r['is_du'],
        $r['ir_dirigeant'],
        $r['cotisations_ipres'],
        $r['cotisations_css'],
        $r['irvm_dividendes'],
        $r['cout_total_entreprise'],
        $r['remuneration_nette_dirigeant'],
        round($r['taux_prelevement_global'] * 100, 2) . '%',
    ], ';');
}

fclose($sortie);
exit;

<?php
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
exigerRole(['admin']);

$pdo = getPDO();
$idDossier = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT nom_dossier FROM dossiers WHERE id_dossier = :id');
$stmt->execute(['id' => $idDossier]);
$dossier = $stmt->fetch();

if ($dossier) {
    // Suppression en cascade gérée par les clés étrangères (ON DELETE CASCADE)
    $pdo->prepare('DELETE FROM dossiers WHERE id_dossier = :id')->execute(['id' => $idDossier]);
    enregistrerAudit('SUPPRESSION_DOSSIER', 'dossiers', $idDossier, "Dossier « {$dossier['nom_dossier']} » supprimé");
}

header('Location: dossiers_liste.php');
exit;

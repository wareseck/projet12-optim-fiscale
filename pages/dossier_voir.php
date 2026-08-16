<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
demarrerSession();
exigerConnexion();

$pdo = getPDO();
$idDossier = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id_dossier = :id');
$stmt->execute(['id' => $idDossier]);
$dossier = $stmt->fetch();

if (!$dossier) {
    http_response_code(404);
    die('Dossier introuvable.');
}

// Contrôle d'accès : un client ne peut voir que ses propres dossiers
if ($_SESSION['role'] === 'client' && (int) $dossier['id_client'] !== (int) $_SESSION['id_utilisateur']) {
    http_response_code(403);
    die('Accès refusé.');
}

$stmtH = $pdo->prepare('SELECT * FROM hypotheses WHERE id_dossier = :id ORDER BY id_hypothese DESC LIMIT 1');
$stmtH->execute(['id' => $idDossier]);
$hypotheses = $stmtH->fetch();

if (!$hypotheses) {
    die('Aucune hypothèse financière renseignée pour ce dossier.');
}

$parametres = getParametresFiscaux((int) $dossier['exercice']);
$bareme     = getBaremeIR((int) $dossier['exercice']);

// --- (Re)calcul de tous les scénarios à l'affichage (et persistance en base) ---
$stmtScenarios = $pdo->query('SELECT * FROM scenarios ORDER BY id_scenario');
$scenarios = $stmtScenarios->fetchAll();

$resultats = [];
$pdo->prepare('DELETE FROM resultats_scenarios WHERE id_dossier = :id')->execute(['id' => $idDossier]);

$insertResultat = $pdo->prepare(
    'INSERT INTO resultats_scenarios
     (id_dossier, id_scenario, remuneration_brute, dividendes_bruts, is_du, ir_dirigeant,
      cotisations_ipres, cotisations_css, irvm_dividendes, cout_total_entreprise,
      remuneration_nette_dirigeant, taux_prelevement_global)
     VALUES (:id_dossier, :id_scenario, :rb, :db, :is_du, :ir, :ipres, :css, :irvm, :cout, :net, :taux)'
);

foreach ($scenarios as $scenario) {
    $res = calculerScenario($scenario['code'], $hypotheses, $parametres, $bareme, 0.6, 1.0);

    $insertResultat->execute([
        'id_dossier'  => $idDossier,
        'id_scenario' => $scenario['id_scenario'],
        'rb'    => $res['remuneration_brute'],
        'db'    => $res['dividendes_bruts'],
        'is_du' => $res['is_du'],
        'ir'    => $res['ir_dirigeant'],
        'ipres' => $res['cotisations_ipres'],
        'css'   => $res['cotisations_css'],
        'irvm'  => $res['irvm_dividendes'],
        'cout'  => $res['cout_total_entreprise'],
        'net'   => $res['remuneration_nette_dirigeant'],
        'taux'  => $res['taux_prelevement_global'],
    ]);

    $resultats[] = array_merge(['code' => $scenario['code'], 'libelle' => $scenario['libelle']], $res);
}

enregistrerAudit('CALCUL_SCENARIOS', 'dossiers', $idDossier, 'Recalcul des 6 scénarios');

// Meilleur scénario = celui qui maximise le net du dirigeant
usort($resultats, fn($a, $b) => $b['remuneration_nette_dirigeant'] <=> $a['remuneration_nette_dirigeant']);
$meilleurScenario = $resultats[0]['code'] ?? null;

// --- Optimisation du mix salaire/dividende si une cible est définie ---
$optimisations = [];
$pdo->prepare('DELETE FROM simulations_optimisation WHERE id_dossier = :id')->execute(['id' => $idDossier]);
$insertOptim = $pdo->prepare(
    'INSERT INTO simulations_optimisation (id_dossier, id_scenario, part_salaire_optimale, part_dividende_optimale, cout_total_minimal)
     VALUES (:id_dossier, :id_scenario, :part_salaire, :part_dividende, :cout)'
);

foreach ($scenarios as $scenario) {
    if (in_array($scenario['code'], ['SARL_MINO', 'SARL_MAJO', 'SA', 'HOLDING'], true)) {
        $opt = optimiserMixSalaireDividende($scenario['code'], $hypotheses, $parametres, $bareme,
            $hypotheses['remuneration_nette_cible'] !== null ? (float) $hypotheses['remuneration_nette_cible'] : null);

        $insertOptim->execute([
            'id_dossier'    => $idDossier,
            'id_scenario'   => $scenario['id_scenario'],
            'part_salaire'  => $opt['part_salaire_optimale'],
            'part_dividende'=> $opt['part_dividende_optimale'],
            'cout'          => $opt['resultat']['cout_total_entreprise'],
        ]);

        $optimisations[] = array_merge(['libelle' => $scenario['libelle']], $opt);
    }
}

// ============================================================
// Analyse de sensibilité : impact d'une variation du CA et du taux d'IS
// (calcul indicatif à la volée, non persisté en base)
// ============================================================
$variationsCA = [-0.20, 0, 0.20];
$sensibiliteCA = [];
foreach ($variationsCA as $var) {
    $hypVariante = $hypotheses;
    $hypVariante['chiffre_affaires'] = (float) $hypotheses['chiffre_affaires'] * (1 + $var);
    $hypVariante['resultat_avant_remuneration'] = (float) $hypotheses['resultat_avant_remuneration'] * (1 + $var);

    $meilleurNet = null;
    $meilleurLibelle = null;
    foreach ($scenarios as $scenario) {
        $res = calculerScenario($scenario['code'], $hypVariante, $parametres, $bareme, 0.6, 1.0);
        if ($meilleurNet === null || $res['remuneration_nette_dirigeant'] > $meilleurNet) {
            $meilleurNet = $res['remuneration_nette_dirigeant'];
            $meilleurLibelle = $scenario['libelle'];
        }
    }
    $sensibiliteCA[] = [
        'variation'         => $var,
        'ca'                => $hypVariante['chiffre_affaires'],
        'meilleur_scenario' => $meilleurLibelle,
        'net_optimal'       => $meilleurNet,
    ];
}

$variationsTauxIS = [-0.02, 0, 0.02];
$sensibiliteIS = [];
foreach ($variationsTauxIS as $var) {
    $paramVariant = $parametres;
    $paramVariant['taux_IS'] = ($parametres['taux_IS'] ?? 0.30) + $var;

    $meilleurNetIS = null;
    $meilleurLibelleIS = null;
    foreach (['SARL_MINO', 'SARL_MAJO', 'SA', 'HOLDING'] as $codeSociete) {
        $scenarioSociete = null;
        foreach ($scenarios as $s) {
            if ($s['code'] === $codeSociete) {
                $scenarioSociete = $s;
                break;
            }
        }
        if (!$scenarioSociete) {
            continue;
        }
        $res = calculerScenario($codeSociete, $hypotheses, $paramVariant, $bareme, 0.6, 1.0);
        if ($meilleurNetIS === null || $res['remuneration_nette_dirigeant'] > $meilleurNetIS) {
            $meilleurNetIS = $res['remuneration_nette_dirigeant'];
            $meilleurLibelleIS = $scenarioSociete['libelle'];
        }
    }
    $sensibiliteIS[] = [
        'variation'         => $var,
        'taux_is'           => $paramVariant['taux_IS'],
        'meilleur_scenario' => $meilleurLibelleIS,
        'net_optimal'       => $meilleurNetIS,
    ];
}

$titrePage = $dossier['nom_dossier'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h3><?= htmlspecialchars($dossier['nom_dossier']) ?></h3>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($dossier['nom_entreprise'] ?? '—') ?> —
            Dirigeant : <?= htmlspecialchars($dossier['nom_dirigeant'] ?? '—') ?> —
            Exercice <?= htmlspecialchars($dossier['exercice']) ?>
        </p>
    </div>
    <div class="btn-group">
        <a href="dossier_modifier.php?id=<?= $idDossier ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Modifier</a>
        <a href="../exports/export_pdf.php?id=<?= $idDossier ?>" class="btn btn-outline-danger" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
        <a href="../exports/export_csv.php?id=<?= $idDossier ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export Excel/CSV</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card bg-kpi-1">
            <div class="small">Chiffre d'affaires</div>
            <h3><?= formaterMontant((float) $hypotheses['chiffre_affaires']) ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card bg-kpi-2">
            <div class="small">Résultat avant rémunération</div>
            <h3><?= formaterMontant((float) $hypotheses['resultat_avant_remuneration']) ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card bg-kpi-3">
            <div class="small">Rémunération nette cible</div>
            <h3><?= $hypotheses['remuneration_nette_cible'] ? formaterMontant((float) $hypotheses['remuneration_nette_cible']) : 'Non définie' ?></h3>
        </div>
    </div>
</div>

<div class="card p-3 mb-4">
    <h5>Comparatif des 6 scénarios</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Scénario</th>
                    <th class="text-end">IS dû</th>
                    <th class="text-end">IR dirigeant</th>
                    <th class="text-end">Cotisations</th>
                    <th class="text-end">Coût total entreprise</th>
                    <th class="text-end">Net dirigeant</th>
                    <th class="text-end">Taux prélèvement global</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resultats as $r): ?>
                <tr class="<?= $r['code'] === $meilleurScenario ? 'table-success' : '' ?>">
                    <td>
                        <?= htmlspecialchars($r['libelle']) ?>
                        <?php if ($r['code'] === $meilleurScenario): ?>
                            <span class="badge bg-success scenario-badge"><i class="bi bi-star-fill"></i> Optimal</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= formaterMontant($r['is_du']) ?></td>
                    <td class="text-end"><?= formaterMontant($r['ir_dirigeant']) ?></td>
                    <td class="text-end"><?= formaterMontant($r['cotisations_ipres'] + $r['cotisations_css']) ?></td>
                    <td class="text-end"><strong><?= formaterMontant($r['cout_total_entreprise']) ?></strong></td>
                    <td class="text-end"><strong><?= formaterMontant($r['remuneration_nette_dirigeant']) ?></strong></td>
                    <td class="text-end"><?= formaterPourcentage($r['taux_prelevement_global']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3 mb-4">
    <h5>Graphique comparatif (répartition empilée des prélèvements)</h5>
    <canvas id="graphScenarios" height="100"></canvas>
</div>

<div class="card p-3 mb-4">
    <h5>Analyse de sensibilité</h5>
    <p class="text-muted small">
        Impact indicatif d'une variation du chiffre d'affaires et du taux d'IS sur le meilleur scénario
        (calcul à la volée, non enregistré en base).
    </p>

    <h6 class="mt-3">Variation du chiffre d'affaires</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Variation</th>
                    <th class="text-end">CA simulé</th>
                    <th>Meilleur scénario</th>
                    <th class="text-end">Net dirigeant optimal</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sensibiliteCA as $s): ?>
                <tr>
                    <td><?= $s['variation'] > 0 ? '+' : '' ?><?= formaterPourcentage($s['variation']) ?></td>
                    <td class="text-end"><?= formaterMontant($s['ca']) ?></td>
                    <td><?= htmlspecialchars($s['meilleur_scenario'] ?? '—') ?></td>
                    <td class="text-end"><?= formaterMontant($s['net_optimal'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h6 class="mt-3">Variation du taux d'IS (scénarios sociétés uniquement)</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Variation</th>
                    <th class="text-end">Taux d'IS simulé</th>
                    <th>Meilleur scénario société</th>
                    <th class="text-end">Net dirigeant optimal</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sensibiliteIS as $s): ?>
                <tr>
                    <td><?= $s['variation'] > 0 ? '+' : '' ?><?= formaterPourcentage($s['variation']) ?></td>
                    <td class="text-end"><?= formaterPourcentage($s['taux_is']) ?></td>
                    <td><?= htmlspecialchars($s['meilleur_scenario'] ?? '—') ?></td>
                    <td class="text-end"><?= formaterMontant($s['net_optimal'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($optimisations)): ?>
<div class="card p-3 mb-4">
    <h5>Optimisation du mix salaire / dividende (scénarios sociétés)</h5>
    <p class="text-muted small">
        Recherche du meilleur équilibre entre rémunération et dividende pour chaque type de société,
        <?= $hypotheses['remuneration_nette_cible'] ? 'afin d\'atteindre le plus précisément possible la cible de net fixée.' : 'afin de maximiser le net perçu par le dirigeant.' ?>
    </p>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Scénario</th>
                    <th class="text-end">Part salaire optimale</th>
                    <th class="text-end">Part dividende optimale</th>
                    <th class="text-end">Coût total résultant</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($optimisations as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['libelle']) ?></td>
                    <td class="text-end"><?= formaterPourcentage($o['part_salaire_optimale']) ?></td>
                    <td class="text-end"><?= formaterPourcentage($o['part_dividende_optimale']) ?></td>
                    <td class="text-end"><?= formaterMontant($o['resultat']['cout_total_entreprise']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
 window.addEventListener('load', function() {
new Chart(document.getElementById('graphScenarios'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($resultats, 'libelle')) ?>,
        datasets: [
            {
                label: "IS dû",
                data: <?= json_encode(array_column($resultats, 'is_du')) ?>,
                backgroundColor: '#c0392b'
            },
            {
                label: "IR dirigeant",
                data: <?= json_encode(array_column($resultats, 'ir_dirigeant')) ?>,
                backgroundColor: '#e67e22'
            },
            {
                label: "Cotisations (IPRES + CSS)",
                data: <?= json_encode(array_map(fn($r) => $r['cotisations_ipres'] + $r['cotisations_css'], $resultats)) ?>,
                backgroundColor: '#8e44ad'
            },
            {
                label: "IRVM dividendes",
                data: <?= json_encode(array_column($resultats, 'irvm_dividendes')) ?>,
                backgroundColor: '#f1c40f'
            },
            {
                label: "Net dirigeant",
                data: <?= json_encode(array_column($resultats, 'remuneration_nette_dirigeant')) ?>,
                backgroundColor: '#27ae60'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        },
        plugins: {
            title: { display: true, text: "Répartition empilée : prélèvements + net dirigeant par scénario" }
        }
    }
});
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
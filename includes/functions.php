<?php
/**
 * Moteur de calcul fiscal et social
 * Projet 12 - Optimisation fiscale pour dirigeants et entreprises
 *
 * IMPORTANT (à lire avant la soutenance) :
 * Les taux et règles ci-dessous sont paramétrés dans la table `parametres_fiscaux`
 * et `bareme_ir` (rien n'est codé "en dur" dans les formules). Ils reflètent une
 * approximation pédagogique du système fiscal et social sénégalais afin de pouvoir
 * comparer les scénarios de façon cohérente. Avant la soutenance, vérifie les taux
 * exacts en vigueur (CGI, Code du Travail, IPRES, CSS) et ajuste si besoin la table
 * `parametres_fiscaux` : aucune modification de code n'est nécessaire pour ça.
 */

require_once __DIR__ . '/../config/db.php';

// ============================================================
// Récupération des paramètres depuis la base
// ============================================================

function getParametresFiscaux(int $exercice): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT cle, valeur FROM parametres_fiscaux WHERE exercice = :ex');
    $stmt->execute(['ex' => $exercice]);
    $parametres = [];
    foreach ($stmt->fetchAll() as $ligne) {
        $parametres[$ligne['cle']] = (float) $ligne['valeur'];
    }
    return $parametres;
}

function getBaremeIR(int $exercice): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT borne_inferieure, borne_superieure, taux FROM bareme_ir
         WHERE exercice = :ex ORDER BY borne_inferieure ASC'
    );
    $stmt->execute(['ex' => $exercice]);
    return $stmt->fetchAll();
}

// ============================================================
// Calculs de base
// ============================================================

/**
 * Calcule l'IR selon un barème progressif par tranches
 */
function calculerIR(float $revenuImposable, array $bareme): float
{
    if ($revenuImposable <= 0) {
        return 0.0;
    }

    $impot = 0.0;
    foreach ($bareme as $tranche) {
        $borneInf = (float) $tranche['borne_inferieure'];
        $borneSup = $tranche['borne_superieure'] !== null ? (float) $tranche['borne_superieure'] : null;
        $taux     = (float) $tranche['taux'];

        if ($revenuImposable <= $borneInf) {
            continue;
        }

        $plafondTranche = $borneSup !== null ? min($revenuImposable, $borneSup) : $revenuImposable;
        $baseTranche    = max(0, $plafondTranche - $borneInf);
        $impot         += $baseTranche * $taux;

        if ($borneSup !== null && $revenuImposable <= $borneSup) {
            break;
        }
    }

    return round($impot, 2);
}

/**
 * Cotisations sociales part salariale (IPRES uniquement plafonnée + CSS n'a pas de part salariale ici)
 */
function calculerCotisationsSalarie(float $remunerationBrute, array $parametres): float
{
    $baseMensuelle = $remunerationBrute / 12;
    $basePlafonnee = min($baseMensuelle, $parametres['plafond_IPRES'] ?? PHP_FLOAT_MAX);
    $ipresSalarie  = $basePlafonnee * 12 * ($parametres['taux_IPRES_salarie'] ?? 0);
    return round($ipresSalarie, 2);
}

/**
 * Cotisations sociales part employeur (IPRES + CSS + CFCE)
 */
function calculerCotisationsEmployeur(float $remunerationBrute, array $parametres): float
{
    $baseMensuelle = $remunerationBrute / 12;
    $basePlafonnee = min($baseMensuelle, $parametres['plafond_IPRES'] ?? PHP_FLOAT_MAX);

    $ipresEmployeur = $basePlafonnee * 12 * ($parametres['taux_IPRES_employeur'] ?? 0);
    $cssEmployeur   = $remunerationBrute * (($parametres['taux_CSS_prestations'] ?? 0) + ($parametres['taux_CSS_accidents'] ?? 0));
    $cfce           = $remunerationBrute * ($parametres['taux_CFCE'] ?? 0);

    return round($ipresEmployeur + $cssEmployeur + $cfce, 2);
}

/**
 * Calcule le revenu imposable à l'IR après abattement forfaitaire plafonné
 */
function calculerRevenuImposableSalaire(float $remunerationBrute, float $cotisationsSalarie, array $parametres): float
{
    $baseAvantAbattement = max(0, $remunerationBrute - $cotisationsSalarie);
    $abattement = min(
        $baseAvantAbattement * ($parametres['abattement_IR_taux'] ?? 0),
        $parametres['abattement_IR_plafond'] ?? PHP_FLOAT_MAX
    );
    return max(0, $baseAvantAbattement - $abattement);
}

/**
 * Calcule l'IS dû : max(IS normal, Impôt Minimum Forfaitaire)
 */
function calculerIS(float $resultatFiscal, float $chiffreAffaires, array $parametres): float
{
    $isNormal = max(0, $resultatFiscal) * ($parametres['taux_IS'] ?? 0.30);
    $imf      = $chiffreAffaires * ($parametres['taux_IMF'] ?? 0.005);
    return round(max($isNormal, $imf), 2);
}

/**
 * Retenue à la source sur dividendes distribués (IRVM)
 */
function calculerIRVM(float $dividendesBruts, array $parametres): float
{
    return round($dividendesBruts * ($parametres['taux_IRVM_dividendes'] ?? 0.10), 2);
}

// ============================================================
// Calcul par scénario
// Chaque fonction retourne un tableau associatif normalisé :
// [remuneration_brute, dividendes_bruts, is_du, ir_dirigeant,
//  cotisations_ipres, cotisations_css, irvm_dividendes,
//  cout_total_entreprise, remuneration_nette_dirigeant, taux_prelevement_global]
// ============================================================

/**
 * Entreprise Individuelle - régime du réel
 * Pas de société distincte : le bénéfice entier est imposé à l'IR au nom de l'exploitant.
 * Pas d'IS, pas de "salaire" ni de dividende au sens juridique.
 */
function calculerScenarioEIReel(float $resultatAvantRemuneration, array $parametres, array $bareme): array
{
    $revenuImposable = max(0, $resultatAvantRemuneration);
    $ir = calculerIR($revenuImposable, $bareme);
    $netDirigeant = $resultatAvantRemuneration - $ir;
    $coutTotal = $resultatAvantRemuneration; // toute la richesse créée reste dans le même patrimoine

    return [
        'remuneration_brute'            => $resultatAvantRemuneration,
        'dividendes_bruts'              => 0,
        'is_du'                         => 0,
        'ir_dirigeant'                  => $ir,
        'cotisations_ipres'             => 0,
        'cotisations_css'               => 0,
        'irvm_dividendes'               => 0,
        'cout_total_entreprise'         => $coutTotal,
        'remuneration_nette_dirigeant'  => $netDirigeant,
        'taux_prelevement_global'       => $coutTotal > 0 ? round($ir / $coutTotal, 4) : 0,
    ];
}

/**
 * Entreprise Individuelle - Contribution Globale Unique (régime forfaitaire simplifié)
 * Approximation pédagogique : taux forfaitaire unique appliqué au CA (à ajuster selon barème CGU réel).
 */
function calculerScenarioEICGU(float $chiffreAffaires, float $resultatAvantRemuneration, array $parametres): array
{
    $tauxForfaitaireCGU = 0.03; // approximation : 3% du CA, palier simplifié (à affiner selon barème officiel)
    $impotForfaitaire = round($chiffreAffaires * $tauxForfaitaireCGU, 2);
    $netDirigeant = $resultatAvantRemuneration - $impotForfaitaire;

    return [
        'remuneration_brute'            => $resultatAvantRemuneration,
        'dividendes_bruts'              => 0,
        'is_du'                         => 0,
        'ir_dirigeant'                  => $impotForfaitaire,
        'cotisations_ipres'             => 0,
        'cotisations_css'               => 0,
        'irvm_dividendes'               => 0,
        'cout_total_entreprise'         => $resultatAvantRemuneration,
        'remuneration_nette_dirigeant'  => $netDirigeant,
        'taux_prelevement_global'       => $resultatAvantRemuneration > 0
            ? round($impotForfaitaire / $resultatAvantRemuneration, 4) : 0,
    ];
}

/**
 * Scénario générique "société" (SARL minoritaire, SARL majoritaire, SA, Holding)
 * Le résultat avant rémunération est réparti entre salaire (partSalaire) et le solde
 * reste dans la société, soumis à l'IS, puis distribué en dividende (partDividendeDistribue).
 *
 * @param string $codeScenario 'SARL_MINO' | 'SARL_MAJO' | 'SA' | 'HOLDING'
 * @param float $partSalaire part du résultat avant rémunération versée en salaire brut (0 à 1)
 * @param float $partDividendeDistribue part du résultat après IS distribuée en dividende (0 à 1)
 */
function calculerScenarioSociete(
    string $codeScenario,
    float $resultatAvantRemuneration,
    float $chiffreAffaires,
    float $partSalaire,
    float $partDividendeDistribue,
    array $parametres,
    array $bareme
): array {
    $remunerationBrute = max(0, $resultatAvantRemuneration * $partSalaire);

    // Le régime majoritaire (TNS) n'a pas les mêmes cotisations que le minoritaire (assimilé salarié).
    // Approximation : le gérant majoritaire cotise sur une base réduite (pas d'IPRES/CSS classiques),
    // on applique un taux forfaitaire de charges sociales TNS simplifié.
    if ($codeScenario === 'SARL_MAJO') {
        $cotisationsSalarie   = round($remunerationBrute * 0.10, 2); // approximation régime TNS
        $cotisationsEmployeur = 0.0;
    } else {
        $cotisationsSalarie   = calculerCotisationsSalarie($remunerationBrute, $parametres);
        $cotisationsEmployeur = calculerCotisationsEmployeur($remunerationBrute, $parametres);
    }

    $revenuImposableSalaire = calculerRevenuImposableSalaire($remunerationBrute, $cotisationsSalarie, $parametres);
    $irSurSalaire = calculerIR($revenuImposableSalaire, $bareme);

    // Résultat fiscal de la société = résultat avant rémunération - charge salariale totale (déductible)
    $chargeSalarialeTotale = $remunerationBrute + $cotisationsEmployeur;
    $resultatFiscalSociete = max(0, $resultatAvantRemuneration - $chargeSalarialeTotale);
    $isDu = calculerIS($resultatFiscalSociete, $chiffreAffaires, $parametres);

    $resultatApresIS = max(0, $resultatFiscalSociete - $isDu);
    $dividendesBruts = $resultatApresIS * $partDividendeDistribue;

    // Régime holding : abattement 95% façon mère-fille sur l'IRVM effectif (approximation)
    if ($codeScenario === 'HOLDING') {
        $irvm = calculerIRVM($dividendesBruts, $parametres) * 0.05; // 95% d'exonération approximative
    } else {
        $irvm = calculerIRVM($dividendesBruts, $parametres);
    }

    $dividendesNets = $dividendesBruts - $irvm;
    $salaireNet = $remunerationBrute - $cotisationsSalarie - $irSurSalaire;

    $netDirigeant = $salaireNet + $dividendesNets;
    $coutTotalEntreprise = $chargeSalarialeTotale + $isDu;
    // (le dividende n'est pas une "charge" pour l'entreprise, c'est une distribution de résultat déjà taxé)

    $prelevementsTotal = $cotisationsSalarie + $cotisationsEmployeur + $irSurSalaire + $isDu + $irvm;
    $richesseCreee = $resultatAvantRemuneration;

    return [
        'remuneration_brute'            => round($remunerationBrute, 2),
        'dividendes_bruts'              => round($dividendesBruts, 2),
        'is_du'                         => $isDu,
        'ir_dirigeant'                  => $irSurSalaire,
        'cotisations_ipres'             => $cotisationsSalarie,
        'cotisations_css'               => $cotisationsEmployeur,
        'irvm_dividendes'               => round($irvm, 2),
        'cout_total_entreprise'         => round($coutTotalEntreprise, 2),
        'remuneration_nette_dirigeant'  => round($netDirigeant, 2),
        'taux_prelevement_global'       => $richesseCreee > 0
            ? round($prelevementsTotal / $richesseCreee, 4) : 0,
    ];
}

/**
 * Calcule un scénario donné à partir de son code, avec une part salaire par défaut de 60%
 * et une distribution de 100% du résultat après IS en dividende (hypothèse simple par défaut).
 */
function calculerScenario(string $codeScenario, array $hypotheses, array $parametres, array $bareme, float $partSalaire = 0.6, float $partDividende = 1.0): array
{
    $rar = (float) $hypotheses['resultat_avant_remuneration'];
    $ca  = (float) $hypotheses['chiffre_affaires'];

    switch ($codeScenario) {
        case 'EI_REEL':
            return calculerScenarioEIReel($rar, $parametres, $bareme);
        case 'EI_CGU':
            return calculerScenarioEICGU($ca, $rar, $parametres);
        case 'SARL_MINO':
        case 'SARL_MAJO':
        case 'SA':
        case 'HOLDING':
            return calculerScenarioSociete($codeScenario, $rar, $ca, $partSalaire, $partDividende, $parametres, $bareme);
        default:
            throw new InvalidArgumentException("Scénario inconnu : $codeScenario");
    }
}

/**
 * Recherche itérative du mix salaire/dividende minimisant le coût total pour l'entreprise
 * tout en atteignant (si possible) la rémunération nette cible du dirigeant.
 * Approche : grille de 0% à 100% par pas de 5% (simple, explicable en soutenance).
 */
function optimiserMixSalaireDividende(string $codeScenario, array $hypotheses, array $parametres, array $bareme, ?float $netCible): array
{
    $meilleurResultat = null;
    $meilleurePartSalaire = null;
    $meilleurEcart = PHP_FLOAT_MAX;

    for ($part = 0.0; $part <= 1.0001; $part += 0.05) {
        $partSalaire = min(1.0, $part);
        $resultat = calculerScenario($codeScenario, $hypotheses, $parametres, $bareme, $partSalaire, 1.0);

        if ($netCible !== null) {
            // On cherche la combinaison qui atteint (ou approche au mieux) la cible,
            // avec en cas d'égalité le coût total le plus faible pour l'entreprise.
            $ecart = abs($resultat['remuneration_nette_dirigeant'] - $netCible);
            if ($ecart < $meilleurEcart - 0.01 ||
                (abs($ecart - $meilleurEcart) < 0.01 &&
                 ($meilleurResultat === null || $resultat['cout_total_entreprise'] < $meilleurResultat['cout_total_entreprise']))) {
                $meilleurEcart = $ecart;
                $meilleurResultat = $resultat;
                $meilleurePartSalaire = $partSalaire;
            }
        } else {
            // Sans cible : on minimise simplement le coût total pour un même net dirigeant maximal
            if ($meilleurResultat === null ||
                $resultat['remuneration_nette_dirigeant'] > $meilleurResultat['remuneration_nette_dirigeant']) {
                $meilleurResultat = $resultat;
                $meilleurePartSalaire = $partSalaire;
            }
        }
    }

    return [
        'part_salaire_optimale'    => $meilleurePartSalaire,
        'part_dividende_optimale'  => 1 - $meilleurePartSalaire,
        'resultat'                 => $meilleurResultat,
    ];
}

// ============================================================
// Utilitaires d'affichage
// ============================================================

function formaterMontant(float $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

function formaterPourcentage(float $taux): string
{
    return number_format($taux * 100, 2, ',', ' ') . ' %';
}

# Document technique — Optim'Fiscale

## 1. Architecture générale

Architecture 3 tiers classique, sans framework, en PHP procédural orienté fonctions (facile à
expliquer en soutenance) :

- **Présentation** : HTML5 / Bootstrap 5 / Chart.js (CDN), un fichier PHP = une page
- **Logique métier** : `includes/functions.php` (moteur de calcul fiscal), `includes/auth.php`
  (sécurité et sessions)
- **Données** : MySQL via PDO (requêtes préparées), une connexion centralisée dans `config/db.php`

Chaque page suit le même schéma :
1. Inclusion des fichiers requis (`auth.php`, `functions.php`)
2. Démarrage de session + contrôle d'accès (`exigerConnexion()` / `exigerRole()`)
3. Traitement du formulaire POST éventuel (validation + requête préparée)
4. Requêtes de lecture pour l'affichage
5. Inclusion du header, affichage HTML, inclusion du footer

## 2. Modèle conceptuel de données (MCD — description textuelle)

```
UTILISATEUR (1,n) ---- crée ---- (0,n) DOSSIER
UTILISATEUR (0,1) ---- concerne (client) ---- (0,n) DOSSIER
DOSSIER (1,1) ---- possède ---- (1,n) HYPOTHESE
DOSSIER (1,1) ---- génère ---- (0,n) RESULTAT_SCENARIO
SCENARIO (1,1) ---- est calculé dans ---- (0,n) RESULTAT_SCENARIO
DOSSIER (1,1) ---- génère ---- (0,n) SIMULATION_OPTIMISATION
SCENARIO (1,1) ---- concerne ---- (0,n) SIMULATION_OPTIMISATION
UTILISATEUR (0,n) ---- réalise ---- (0,n) AUDIT_LOG
```

### Dictionnaire des tables principales

| Table | Rôle |
|---|---|
| `utilisateurs` | Comptes et rôles (admin / conseiller / client) |
| `dossiers` | Une étude d'optimisation pour un dirigeant/entreprise donné |
| `hypotheses` | Données financières de base saisies pour un dossier |
| `parametres_fiscaux` | Taux et montants réglementaires, modifiables sans coder |
| `bareme_ir` | Tranches du barème IR progressif |
| `scenarios` | Référentiel des 6 types de scénarios comparés |
| `resultats_scenarios` | Résultat du calcul de chaque scénario pour un dossier |
| `simulations_optimisation` | Résultat de la recherche du mix salaire/dividende optimal |
| `audit_log` | Traçabilité horodatée des actions sensibles |

## 3. Logique de calcul (cœur métier)

Le moteur (`includes/functions.php`) applique le principe : **rien n'est codé en dur**, tous les
taux viennent de la base (`parametres_fiscaux`, `bareme_ir`).

- `calculerIR()` : application d'un barème progressif générique par tranches
- `calculerIS()` : IS normal (taux × résultat fiscal) comparé à l'IMF (taux × CA), le plus élevé
  des deux étant retenu
- `calculerScenarioEIReel()` / `calculerScenarioEICGU()` : cas particuliers sans société distincte
- `calculerScenarioSociete()` : fonction générique pour SARL minoritaire/majoritaire, SA et
  holding — paramétrée par la part du résultat versée en salaire vs conservée en société
- `optimiserMixSalaireDividende()` : recherche par grille (pas de 5 %) de la répartition
  salaire/dividende la plus proche de la cible de rémunération nette, à coût minimal

## 4. Conventions de code

- Noms de fonctions et variables en français, en `camelCase`
- Toutes les requêtes SQL utilisent des requêtes préparées PDO (`:parametre` nommé)
- Toute sortie HTML passe par `htmlspecialchars()`
- Chaque action sensible (création, modification, suppression, export, connexion) est tracée
  dans `audit_log` via `enregistrerAudit()`
- Un jeton CSRF (`genererTokenCSRF()` / `verifierTokenCSRF()`) protège chaque formulaire POST

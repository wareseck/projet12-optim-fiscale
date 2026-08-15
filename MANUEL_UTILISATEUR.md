# Manuel utilisateur - Optim'Fiscale

**Plateforme web d'optimisation fiscale pour dirigeants et entreprises**

Master CCA (Comptabilité, Contrôle, Audit) — École Supérieure Polytechnique de Dakar
Réalisé par : Ware Seck
Année universitaire 2025-2026

<div style="page-break-after: always;"></div>

## Sommaire

1. Présentation de la plateforme
2. Prérequis et environnement technique
3. Connexion et rôles utilisateurs
4. Tableau de bord
5. Créer un nouveau dossier
6. Consulter le comparatif des scénarios
7. Modifier / supprimer un dossier
8. Exporter en PDF et Excel/CSV
9. Administration : paramètres fiscaux
10. Administration : gestion des utilisateurs
11. Sécurité et bonnes pratiques
12. Glossaire des termes fiscaux
13. Foire aux questions (FAQ)

<div style="page-break-after: always;"></div>

## 1. Présentation de la plateforme

Optim'Fiscale est une plateforme web destinée aux experts-comptables et conseillers fiscaux exerçant au Sénégal. Elle répond à une problématique récurrente dans la pratique professionnelle : comment déterminer, pour un dirigeant d'entreprise donné, la structure juridique et le mode de rémunération qui minimisent la charge fiscale et sociale globale ?

Le choix entre Entreprise Individuelle, SARL, SA ou Holding, ainsi que l'arbitrage entre salaire et dividendes, ont un impact considérable sur le montant net perçu par le dirigeant et sur le coût total supporté par l'entreprise. Ces calculs, réalisés manuellement, sont longs, sujets à erreur, et difficiles à mettre à jour lorsque la législation fiscale évolue.

La plateforme automatise la comparaison de **6 scénarios juridiques et fiscaux** :

1. Entreprise Individuelle au régime réel
2. Entreprise Individuelle à la Contribution Globale Unique (CGU)
3. SARL à l'IS avec gérant minoritaire
4. SARL à l'IS avec gérant majoritaire
5. SA avec rémunération du PDG et dividendes
6. Holding avec remontée des bénéfices par dividendes

Pour chaque scénario, l'application calcule automatiquement l'Impôt sur les Sociétés (IS), l'Impôt sur le Revenu du dirigeant (IR), les cotisations sociales (IPRES, CSS), l'impôt sur les revenus des valeurs mobilières (IRVM) le cas échéant, le coût total supporté par l'entreprise, la rémunération nette perçue par le dirigeant, et le taux de prélèvement global. Un module d'optimisation recherche automatiquement, pour les scénarios en société, le meilleur équilibre entre salaire et dividendes.

<div style="page-break-after: always;"></div>

## 2. Prérequis et environnement technique

### 2.1 Prérequis pour l'installation

- Un serveur local XAMPP (Apache, MySQL, PHP 8.2 ou supérieur)
- Un navigateur web récent (Chrome, Edge, Firefox)
- Une connexion réseau locale (aucun accès internet requis pour l'usage courant)

### 2.2 Stack technique utilisée

| Composant | Technologie |
|---|---|
| Serveur web | Apache 2.4 |
| Langage back-end | PHP 8.2 (orienté procédural avec fonctions modulaires) |
| Base de données | MySQL / MariaDB 10.4 |
| Front-end | HTML5, CSS3, Bootstrap 5 |
| Graphiques | Chart.js |
| Génération PDF | Dompdf |
| Sécurité | PDO (requêtes préparées), password_hash, jetons CSRF |

### 2.3 Structure de la base de données

La base de données `optim_fiscale` comprend 9 tables liées par des clés étrangères :

- `utilisateurs` : comptes et rôles
- `dossiers` : dossiers clients
- `hypotheses` : hypothèses financières saisies (CA, charges)
- `parametres_fiscaux` : taux en vigueur (IS, cotisations, plafonds)
- `bareme_ir` : barème progressif de l'IR par tranches
- `scenarios` : définition des 6 scénarios
- `resultats_scenarios` : résultats calculés pour chaque dossier et scénario
- `simulations_optimisation` : historique des optimisations salaire/dividende
- `audit_log` : journal des actions sensibles

<div style="page-break-after: always;"></div>

## 3. Connexion et rôles utilisateurs

![Page de connexion](captures/01_connexion.png)

L'accès à la plateforme nécessite une authentification par adresse email et mot de passe. Les mots de passe sont stockés sous forme hachée (fonction `password_hash` de PHP), jamais en clair, conformément aux bonnes pratiques de sécurité.

Trois rôles distincts sont gérés par l'application, chacun avec des droits d'accès différenciés :

- **Administrateur** : accès complet à la plateforme. Peut gérer les comptes utilisateurs, modifier les paramètres fiscaux (taux d'IS, barème IR, cotisations), consulter tous les dossiers, et accéder au journal d'audit.
- **Conseiller** : peut créer, consulter, modifier et supprimer les dossiers de ses clients. N'a pas accès aux pages d'administration (paramètres, gestion des utilisateurs).
- **Client** : accès en lecture seule à ses propres dossiers. Ne peut ni créer ni modifier de dossier.

### Étapes de connexion

1. Ouvrir l'adresse `localhost/projet12/` dans le navigateur
2. Renseigner l'adresse email associée au compte
3. Renseigner le mot de passe
4. Cliquer sur "Se connecter"
5. En cas d'erreur d'identifiants, un message explicite s'affiche sans révéler si c'est l'email ou le mot de passe qui est incorrect (bonne pratique de sécurité)
6. La session reste active 30 minutes en cas d'inactivité, après quoi une reconnexion est demandée

<div style="page-break-after: always;"></div>

## 4. Tableau de bord

![Tableau de bord](captures/02_dashboard.png)

Le tableau de bord constitue la page d'accueil après connexion. Il offre une vue synthétique de l'activité :

- **Indicateurs clés (KPI)** : nombre total de dossiers créés, répartition par statut (en cours, finalisé)
- **Graphique comparatif** : représentation visuelle du dernier dossier consulté, permettant de visualiser rapidement l'écart entre les scénarios
- **Accès rapide** : liens directs vers la création d'un nouveau dossier et la liste complète des dossiers

Le contenu affiché varie selon le rôle : un conseiller ne voit que les statistiques relatives à ses propres dossiers, tandis que l'administrateur dispose d'une vue globale.

<div style="page-break-after: always;"></div>

## 5. Créer un nouveau dossier

![Formulaire de création d'un dossier](captures/03_creation_dossier.png)

La création d'un dossier est la première étape pour obtenir une comparaison de scénarios fiscaux. Le formulaire recueille l'ensemble des hypothèses nécessaires au calcul.

### Étapes détaillées

1. Depuis le tableau de bord ou le menu "Dossiers", cliquer sur "Nouveau dossier"
2. Renseigner le **nom du dossier** (libre, sert d'identifiant dans la liste)
3. Renseigner le **nom de l'entreprise** et le **nom du dirigeant**
4. Sélectionner ou saisir le **secteur d'activité**
5. Saisir le **chiffre d'affaires annuel** prévisionnel ou constaté (en FCFA)
6. Saisir les **charges hors rémunération du dirigeant** (charges d'exploitation)
7. Indiquer, si souhaité, une **rémunération nette cible** pour le dirigeant : cette donnée est utilisée par le module d'optimisation pour calibrer le mix salaire/dividende
8. Cliquer sur "Enregistrer" pour valider

Dès la validation, l'application déclenche automatiquement le calcul des 6 scénarios et enregistre les résultats en base de données. Aucune action supplémentaire n'est requise pour lancer les calculs.

<div style="page-break-after: always;"></div>

## 6. Consulter le comparatif des scénarios

![Comparatif des 6 scénarios](captures/04_comparatif_scenarios.png)

C'est la fonctionnalité centrale de la plateforme. En ouvrant un dossier, l'utilisateur accède à un tableau comparatif détaillé des 6 scénarios fiscaux.

### Colonnes du tableau comparatif

| Colonne | Signification |
|---|---|
| Scénario | Nom de la structure juridique et du régime fiscal |
| IS dû | Impôt sur les Sociétés calculé pour ce scénario |
| IR dirigeant | Impôt sur le Revenu payé par le dirigeant sur sa rémunération |
| Cotisations | Cotisations sociales (IPRES, CSS) sur le salaire versé |
| Coût total entreprise | Somme totale déboursée par l'entreprise (salaire chargé + IS) |
| Net dirigeant | Montant net effectivement perçu par le dirigeant |
| Taux de prélèvement global | Rapport entre le total des prélèvements et le chiffre d'affaires |

Le scénario le plus avantageux (taux de prélèvement global le plus faible) est mis en évidence visuellement (surbrillance verte, mention "Optimal").

### Logique de calcul par scénario

- **Entreprise Individuelle (régime réel)** : absence d'IS, le bénéfice est directement soumis à l'IR sur le revenu du dirigeant selon le barème progressif
- **Entreprise Individuelle (CGU)** : régime forfaitaire simplifié, taux réduit appliqué au chiffre d'affaires, adapté aux petites structures
- **SARL / SA / Holding** : la société paie d'abord l'IS (30% ou l'Impôt Minimum Forfaitaire si plus favorable au Trésor), puis le dirigeant est imposé une seconde fois sur ce qu'il perçoit, sous forme de salaire (IR + cotisations sociales) ou de dividendes (IRVM, sans cotisations sociales)

### Module d'optimisation salaire/dividende

Pour les scénarios en société, l'application teste automatiquement différentes répartitions entre salaire et dividendes, par pas de 5%, afin d'identifier la combinaison qui maximise le net perçu par le dirigeant pour un coût donné, ou qui atteint la rémunération nette cible saisie lors de la création du dossier au moindre coût.

<div style="page-break-after: always;"></div>

## 7. Modifier / supprimer un dossier

![Modification d'un dossier](captures/05_modifier_dossier.png)

### Modifier un dossier

1. Depuis la liste des dossiers, cliquer sur l'icône "Modifier" du dossier concerné
2. Le formulaire se pré-remplit avec les données existantes
3. Modifier les champs souhaités (chiffre d'affaires, rémunération cible, statut...)
4. Cliquer sur "Enregistrer les modifications"
5. Les 6 scénarios sont automatiquement recalculés avec les nouvelles hypothèses

Si le statut du dossier passe à "Finalisé" lors de la modification, un email automatique est envoyé pour notifier la finalisation du dossier.

### Supprimer un dossier

1. Depuis la liste des dossiers, cliquer sur l'icône "Supprimer"
2. Une confirmation est demandée pour éviter toute suppression accidentelle
3. La suppression est définitive et entraîne également la suppression des résultats de scénarios associés

<div style="page-break-after: always;"></div>

## 8. Exporter en PDF et Excel/CSV

![Export PDF et Excel/CSV](captures/06_export.png)

Depuis la page de consultation d'un dossier, deux formats d'export sont disponibles :

### Export PDF

Génère un rapport professionnel présentant le comparatif complet des 6 scénarios, formaté pour être transmis directement au client ou archivé. Le document inclut l'ensemble des indicateurs (IS, IR, cotisations, coût total, net dirigeant, taux de prélèvement).

### Export Excel/CSV

Génère un fichier tabulaire (encodage UTF-8 avec BOM pour une compatibilité optimale avec Microsoft Excel) reprenant les mêmes données, exploitable pour des analyses complémentaires ou une intégration dans d'autres outils.

### Étapes

1. Ouvrir le dossier concerné
2. Cliquer sur "Export PDF" ou "Export Excel/CSV" selon le format souhaité
3. Le fichier se télécharge automatiquement dans le dossier de téléchargements du navigateur

<div style="page-break-after: always;"></div>

## 9. Administration : paramètres fiscaux

![Paramètres fiscaux](captures/07_parametres.png)

Cette page, réservée au rôle Administrateur, permet de maintenir à jour les taux et paramètres fiscaux utilisés dans tous les calculs, **sans nécessiter de modification du code source**.

### Paramètres modifiables

- Taux d'Impôt sur les Sociétés (IS)
- Barème progressif de l'Impôt sur le Revenu (IR) par tranches
- Taux de cotisations IPRES (retraite)
- Taux de cotisations CSS (sécurité sociale)
- Plafonds de cotisations
- Taux forfaitaire de la Contribution Globale Unique (CGU)
- Taux de l'IRVM sur les dividendes

Toute modification est immédiatement prise en compte pour les nouveaux calculs et les recalculs de dossiers existants, garantissant que l'application reste conforme à la législation fiscale en vigueur même en cas d'évolution réglementaire.

<div style="page-break-after: always;"></div>

## 10. Administration : gestion des utilisateurs

![Gestion des utilisateurs](captures/08_utilisateurs.png)

Cette page, également réservée à l'Administrateur, permet la gestion complète des comptes d'accès à la plateforme.

### Fonctionnalités disponibles

- Consulter la liste de tous les utilisateurs enregistrés
- Créer un nouveau compte utilisateur (conseiller ou client)
- Modifier le rôle d'un utilisateur existant
- Désactiver un compte sans le supprimer (conservation de l'historique)
- Consulter la date de dernière connexion

<div style="page-break-after: always;"></div>

## 11. Sécurité et bonnes pratiques

La plateforme intègre plusieurs mécanismes de sécurité conformes aux standards professionnels :

- **Mots de passe hachés** : utilisation de `password_hash()` / `password_verify()`, aucun mot de passe n'est stocké en clair
- **Requêtes préparées PDO** : protection systématique contre les injections SQL
- **Échappement des sorties** (`htmlspecialchars`) : protection contre les attaques XSS (Cross-Site Scripting)
- **Jetons CSRF** : chaque formulaire intègre un jeton unique vérifié à la soumission, empêchant les attaques par falsification de requête intersite
- **Expiration de session** : déconnexion automatique après 30 minutes d'inactivité
- **Contrôle d'accès par rôle** : chaque page vérifie les droits de l'utilisateur avant d'afficher son contenu
- **Journal d'audit** : toutes les actions sensibles (connexion, création, modification, suppression) sont horodatées et tracées dans la table `audit_log`

### Recommandations pour l'administrateur

- Changer les mots de passe par défaut dès la mise en production
- Vérifier régulièrement le journal d'audit
- Mettre à jour les paramètres fiscaux dès qu'un changement de législation est publié

<div style="page-break-after: always;"></div>

## 12. Glossaire des termes fiscaux

| Terme | Définition |
|---|---|
| **IS** | Impôt sur les Sociétés, payé par les sociétés (SARL, SA, Holding) sur leur bénéfice |
| **IR** | Impôt sur le Revenu, payé par une personne physique (le dirigeant) sur ses revenus |
| **IMF** | Impôt Minimum Forfaitaire, montant plancher d'IS dû même en l'absence de bénéfice |
| **CGU** | Contribution Globale Unique, régime fiscal forfaitaire simplifié pour petites entreprises |
| **IPRES** | Institution de Prévoyance Retraite du Sénégal, cotisations retraite |
| **CSS** | Caisse de Sécurité Sociale, cotisations sociales |
| **IRVM** | Impôt sur les Revenus des Valeurs Mobilières, appliqué notamment aux dividendes |
| **EI** | Entreprise Individuelle |
| **SARL** | Société à Responsabilité Limitée |
| **SA** | Société Anonyme |
| **PDG** | Président Directeur Général |
| **CA** | Chiffre d'Affaires |

<div style="page-break-after: always;"></div>

## 13. Foire aux questions (FAQ)

**Que faire si je ne me souviens plus de mon mot de passe ?**
Contacter l'administrateur de la plateforme, qui peut réinitialiser le mot de passe depuis la page de gestion des utilisateurs.

**Puis-je modifier un scénario après création du dossier ?**
Les scénarios sont recalculés automatiquement à chaque consultation du dossier ou modification des hypothèses. Il n'est pas possible de modifier manuellement un résultat de scénario, celui-ci étant toujours dérivé des hypothèses saisies et des paramètres fiscaux en vigueur.

**Les taux fiscaux affichés sont-ils toujours à jour ?**
Les taux sont ceux configurés dans la page "Paramètres fiscaux" par l'administrateur. Il est recommandé de vérifier leur conformité avec la législation en vigueur avant toute utilisation en contexte réel.

**Que se passe-t-il si je supprime un dossier par erreur ?**
La suppression est définitive. Il est recommandé d'exporter un dossier important en PDF avant toute suppression.

**Puis-je utiliser la plateforme sur mobile ?**
Oui, l'interface est responsive grâce à Bootstrap 5 et s'adapte aux écrans de smartphone et tablette.
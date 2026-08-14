# Optim'Fiscale — Plateforme d'optimisation fiscale pour dirigeants et entreprises

Projet réalisé dans le cadre du Master CCA (Comptabilité, Contrôle, Audit) — École Supérieure Polytechnique de Dakar.
**Sujet n°12** : Plateforme web d'optimisation fiscale pour dirigeants et entreprises (comparaison de scénarios).

## 1. Présentation

La plateforme compare 6 scénarios juridiques et fiscaux pour un dirigeant d'entreprise :

1. Entreprise Individuelle — régime du réel
2. Entreprise Individuelle — Contribution Globale Unique (CGU)
3. SARL — gérant minoritaire
4. SARL — gérant majoritaire
5. SA (Société Anonyme)
6. Structure avec holding

Pour chaque scénario, elle calcule automatiquement : IS dû, IR du dirigeant, cotisations sociales,
coût total pour l'entreprise, rémunération nette perçue et taux de prélèvement global — puis
recherche le mix salaire/dividende qui minimise le coût tout en atteignant la rémunération cible.

## 2. Prérequis

- XAMPP (Apache + MySQL + PHP 8+) — [https://www.apachefriends.org](https://www.apachefriends.org)
- Composer (pour l'export PDF) — [https://getcomposer.org](https://getcomposer.org)
- Un navigateur récent

## 3. Installation

### Étape 1 — Copier le projet
Copie le dossier `php/` dans `C:\xampp\htdocs\projet12` (Windows) ou `/Applications/XAMPP/htdocs/projet12` (Mac).

### Étape 2 — Créer la base de données
1. Démarre Apache et MySQL depuis le panneau de contrôle XAMPP
2. Ouvre phpMyAdmin (`http://localhost/phpmyadmin`)
3. Onglet "SQL", colle et exécute le contenu de `sql/01_creation_base.sql`
4. Fais de même avec `sql/02_donnees_demo.sql`

### Étape 3 — Générer les mots de passe de démonstration
Les mots de passe dans `02_donnees_demo.sql` sont des placeholders. Pour les activer :
1. Va sur `http://localhost/projet12/pages/generer_hash.php`
2. Copie les 3 hash générés
3. Dans phpMyAdmin, mets à jour la colonne `mot_de_passe` de la table `utilisateurs` pour
   chacun des 3 comptes de démonstration avec le hash correspondant
4. **Supprime le fichier `generer_hash.php`** (sécurité)

Comptes de démonstration :
| Email | Mot de passe | Rôle |
|---|---|---|
| admin@optimfiscale.sn | Admin123! | Administrateur |
| conseiller@optimfiscale.sn | Conseil123! | Conseiller |
| client@optimfiscale.sn | Client123! | Client |

### Étape 4 — Installer Dompdf (export PDF)
Dans un terminal, place-toi dans le dossier `php/` puis exécute :
```
composer require dompdf/dompdf
```

### Étape 5 — Lancer l'application
Ouvre `http://localhost/projet12/` dans ton navigateur.

## 4. Structure du projet

```
projet12/
├── sql/
│   ├── 01_creation_base.sql       (structure de la base)
│   └── 02_donnees_demo.sql        (données de démonstration)
├── php/
│   ├── config/db.php              (connexion PDO)
│   ├── includes/
│   │   ├── auth.php               (authentification, sessions, rôles, CSRF)
│   │   ├── functions.php          (moteur de calcul fiscal — cœur métier)
│   │   ├── email.php              (envoi d'email automatique)
│   │   ├── header.php / footer.php
│   ├── pages/
│   │   ├── login.php / logout.php
│   │   ├── dashboard.php
│   │   ├── dossiers_liste.php     (recherche, filtres, pagination)
│   │   ├── dossier_ajouter.php / dossier_modifier.php / dossier_supprimer.php
│   │   ├── dossier_voir.php       (calcul des 6 scénarios + optimisation)
│   │   ├── parametres.php         (admin — taux fiscaux modifiables sans coder)
│   │   ├── utilisateurs_liste.php (admin — gestion des comptes)
│   │   └── generer_hash.php       (outil ponctuel, à supprimer après usage)
│   ├── exports/
│   │   ├── export_pdf.php
│   │   └── export_csv.php
│   └── assets/css/style.css
```

## 5. Sécurité mise en œuvre

- Mots de passe hachés (`password_hash` / `password_verify`)
- Requêtes préparées PDO partout (protection injection SQL)
- Échappement systématique (`htmlspecialchars`) contre le XSS
- Protection CSRF sur tous les formulaires
- Sessions avec expiration automatique après 30 minutes d'inactivité
- Contrôle d'accès par rôle (admin / conseiller / client)
- Journal d'audit horodaté de toutes les actions sensibles (table `audit_log`)

## 6. Points à vérifier / adapter avant la soutenance

- **Les taux fiscaux et sociaux** (IS, IPRES, CSS, barème IR, CGU, IRVM) sont des valeurs
  paramétrées dans la base (`parametres_fiscaux`, `bareme_ir`) à titre pédagogique. Vérifie
  les taux exactement en vigueur et ajuste-les depuis la page **Paramètres fiscaux** (admin),
  sans toucher au code.
- Le régime du gérant majoritaire (TNS) utilise une approximation simplifiée des cotisations —
  à affiner si tu veux aller plus loin.
- L'envoi d'email utilise la fonction native `mail()` de PHP : sous XAMPP local, il faut
  configurer `sendmail` dans `php.ini` pour un envoi réel (sinon la fonction s'exécute sans erreur
  mais l'email ne part pas réellement — un `try/catch` capture ce cas proprement).

## 7. Ce que tu dois savoir expliquer en soutenance

- Le modèle de données et les relations entre `dossiers`, `hypotheses`, `resultats_scenarios`
- La logique de calcul de chaque scénario dans `includes/functions.php`
- Comment fonctionne la recherche du mix salaire/dividende optimal (`optimiserMixSalaireDividende`)
- Les mesures de sécurité mises en place et pourquoi (CSRF, requêtes préparées, hachage...)

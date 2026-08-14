-- ============================================================
-- Projet 12 : Données de démonstration
-- À exécuter après 01_creation_base.sql
-- ============================================================

USE optim_fiscale;

-- ============================================================
-- Utilisateurs de test
-- Mot de passe en clair pour référence : "Admin123!" / "Conseil123!" / "Client123!"
-- Les hash ci-dessous doivent être régénérés avec password_hash() en PHP
-- (placeholders à remplacer par le script generer_hash.php fourni)
-- ============================================================
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES
('DIOP', 'Amadou', 'admin@optimfiscale.sn', '$2y$10$PLACEHOLDER_ADMIN_HASH', 'admin'),
('FALL', 'Aïssatou', 'conseiller@optimfiscale.sn', '$2y$10$PLACEHOLDER_CONSEILLER_HASH', 'conseiller'),
('NDIAYE', 'Moussa', 'client@optimfiscale.sn', '$2y$10$PLACEHOLDER_CLIENT_HASH', 'client');

-- ============================================================
-- Paramètres fiscaux 2026 (Sénégal)
-- ============================================================
INSERT INTO parametres_fiscaux (cle, libelle, valeur, unite, exercice) VALUES
('taux_IS', 'Taux normal de l''Impôt sur les Sociétés', 0.30, 'taux', 2026),
('taux_IMF', 'Impôt Minimum Forfaitaire (% du CA)', 0.005, 'taux', 2026),
('taux_IPRES_salarie', 'Cotisation IPRES part salariale', 0.056, 'taux', 2026),
('taux_IPRES_employeur', 'Cotisation IPRES part employeur', 0.084, 'taux', 2026),
('plafond_IPRES', 'Plafond mensuel de cotisation IPRES', 432000.00, 'montant', 2026),
('taux_CSS_prestations', 'CSS - prestations familiales (employeur)', 0.07, 'taux', 2026),
('taux_CSS_accidents', 'CSS - accidents du travail (employeur, taux moyen)', 0.03, 'taux', 2026),
('taux_CFCE', 'Contribution forfaitaire à la charge de l''employeur', 0.03, 'taux', 2026),
('abattement_IR_taux', 'Abattement forfaitaire sur salaire imposable', 0.30, 'taux', 2026),
('abattement_IR_plafond', 'Plafond annuel de l''abattement IR', 900000.00, 'montant', 2026),
('taux_IRVM_dividendes', 'Retenue à la source sur dividendes distribués (IRVM)', 0.10, 'taux', 2026);

-- ============================================================
-- Barème IR progressif 2026 (à ajuster selon le CGI en vigueur)
-- ============================================================
INSERT INTO bareme_ir (exercice, borne_inferieure, borne_superieure, taux) VALUES
(2026, 0, 630000, 0.00),
(2026, 630001, 1500000, 0.20),
(2026, 1500001, 4000000, 0.30),
(2026, 4000001, 8000000, 0.35),
(2026, 8000001, 13500000, 0.37),
(2026, 13500001, NULL, 0.40);

-- ============================================================
-- Référentiel des scénarios comparés
-- ============================================================
INSERT INTO scenarios (code, libelle, description) VALUES
('EI_REEL', 'Entreprise Individuelle - régime du réel', 'Le bénéfice est imposé directement à l''IR au nom de l''exploitant'),
('EI_CGU', 'Entreprise Individuelle - Contribution Globale Unique', 'Régime forfaitaire simplifié selon le chiffre d''affaires'),
('SARL_MINO', 'SARL - gérant minoritaire', 'Gérant assimilé salarié, rémunération soumise à IPRES/CSS'),
('SARL_MAJO', 'SARL - gérant majoritaire', 'Gérant TNS, cotisations spécifiques, rémunération non déductible d''un plafond'),
('SA', 'Société Anonyme', 'PDG avec rémunération + dividendes possibles'),
('HOLDING', 'Structure avec holding', 'Remontée des bénéfices par dividendes intermédiés via holding');

-- ============================================================
-- Dossier de démonstration
-- ============================================================
INSERT INTO dossiers (id_utilisateur, id_client, nom_dossier, nom_entreprise, nom_dirigeant, exercice, statut) VALUES
(2, 3, 'Étude Ndiaye SARL 2026', 'NDIAYE CONSULTING SARL', 'Moussa NDIAYE', 2026, 'en_cours');

INSERT INTO hypotheses (id_dossier, chiffre_affaires, charges_hors_remuneration, remuneration_nette_cible) VALUES
(1, 50000000.00, 20000000.00, 15000000.00);

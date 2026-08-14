<?php
/**
 * Envoi d'email automatique
 * Utilise la fonction mail() native de PHP (fonctionne via le sendmail de XAMPP
 * une fois configuré dans php.ini — voir le README pour la configuration locale).
 *
 * Pour un environnement de production, il est recommandé de migrer vers PHPMailer
 * (composer require phpmailer/phpmailer) afin d'utiliser un serveur SMTP fiable.
 */

function envoyerEmailFinalisationDossier(string $emailDestinataire, string $nomDestinataire, string $nomDossier): bool
{
    $sujet = 'Votre étude d\'optimisation fiscale est finalisée';

    $corps = "Bonjour $nomDestinataire,\n\n"
        . "Le dossier « $nomDossier » vient d'être finalisé sur la plateforme Optim'Fiscale.\n"
        . "Connectez-vous à votre espace pour consulter le comparatif des scénarios et le rapport détaillé.\n\n"
        . "Cordialement,\nL'équipe Optim'Fiscale";

    $entetes = "From: notifications@optimfiscale.sn\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    // mail() retourne true si le message a été accepté pour livraison (pas de garantie de réception)
    return @mail($emailDestinataire, $sujet, $corps, $entetes);
}

<?php
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();

// Si déjà connecté, redirige directement
if (isset($_SESSION['id_utilisateur'])) {
    header('Location: dashboard.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, merci de réessayer.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if ($email === '' || $motDePasse === '') {
            $erreur = 'Merci de renseigner votre email et votre mot de passe.';
        } elseif (connecterUtilisateur($email, $motDePasse)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $erreur = 'Email ou mot de passe incorrect.';
        }
    }
}

$titrePage = 'Connexion';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-5 col-lg-4">
        <div class="card p-4">
            <div class="text-center mb-3">
                <i class="bi bi-calculator" style="font-size: 2.5rem; color:#2c3e50;"></i>
                <h4 class="mt-2">Optim'Fiscale</h4>
                <p class="text-muted small">Plateforme d'optimisation fiscale pour dirigeants</p>
            </div>

            <?php if (isset($_GET['expire'])): ?>
                <div class="alert alert-warning py-2 small">Votre session a expiré, veuillez vous reconnecter.</div>
            <?php endif; ?>

            <?php if ($erreur): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">
                <div class="mb-3">
                    <label class="form-label">Adresse email</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="invalid-feedback">Merci de saisir un email valide.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="mot_de_passe" class="form-control" required minlength="4">
                </div>
                <button type="submit" class="btn btn-dark w-100">Se connecter</button>
            </form>

            <hr>
            <div class="small text-muted">
                <strong>Comptes de démonstration :</strong><br>
                admin@optimfiscale.sn / conseiller@optimfiscale.sn / client@optimfiscale.sn<br>
                (mots de passe définis via generer_hash.php — voir README)
            </div>
        </div>
    </div>
</div>

<script>
// Validation côté client (Bootstrap)
(() => {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

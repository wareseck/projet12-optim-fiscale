<?php
require_once __DIR__ . '/../includes/auth.php';
demarrerSession();
exigerRole(['admin']);

$pdo = getPDO();
$erreurs = [];
$message = '';

// --- Création d'un utilisateur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreurs[] = 'Session expirée, merci de réessayer.';
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'client';

        if ($nom === '' || $prenom === '') $erreurs[] = 'Nom et prénom obligatoires.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs[] = 'Email invalide.';
        if (strlen($motDePasse) < 8) $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if (!in_array($role, ['admin', 'conseiller', 'client'], true)) $erreurs[] = 'Rôle invalide.';

        if (empty($erreurs)) {
            $verif = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = :email');
            $verif->execute(['email' => $email]);
            if ($verif->fetchColumn() > 0) {
                $erreurs[] = 'Cet email est déjà utilisé.';
            } else {
                $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (:nom, :prenom, :email, :hash, :role)'
                );
                $stmt->execute(['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'hash' => $hash, 'role' => $role]);
                enregistrerAudit('CREATION_UTILISATEUR', 'utilisateurs', (int) $pdo->lastInsertId(), "Utilisateur $email créé ($role)");
                $message = 'Utilisateur créé avec succès.';
            }
        }
    }
}

// --- Activation / désactivation ---
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $pdo->prepare('UPDATE utilisateurs SET actif = NOT actif WHERE id_utilisateur = :id')->execute(['id' => $id]);
    enregistrerAudit('TOGGLE_UTILISATEUR', 'utilisateurs', $id, 'Statut actif/inactif basculé');
    header('Location: utilisateurs_liste.php');
    exit;
}

$utilisateurs = $pdo->query('SELECT * FROM utilisateurs ORDER BY date_creation DESC')->fetchAll();

$titrePage = 'Utilisateurs';
require_once __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4"><i class="bi bi-people"></i> Gestion des utilisateurs</h3>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php foreach ($erreurs as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card p-4 mb-4">
            <h5>Nouvel utilisateur</h5>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">
                <input type="hidden" name="action" value="creer">
                <div class="row">
                    <div class="col-6 mb-2"><input type="text" name="nom" class="form-control" placeholder="Nom" required></div>
                    <div class="col-6 mb-2"><input type="text" name="prenom" class="form-control" placeholder="Prénom" required></div>
                </div>
                <div class="mb-2"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="mb-2"><input type="password" name="mot_de_passe" class="form-control" placeholder="Mot de passe (8 car. min)" required minlength="8"></div>
                <div class="mb-3">
                    <select name="role" class="form-select">
                        <option value="client">Client</option>
                        <option value="conseiller">Conseiller</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-dark w-100">Créer l'utilisateur</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card p-3">
            <h5>Liste des utilisateurs</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($u['role']) ?></span></td>
                            <td><span class="badge bg-<?= $u['actif'] ? 'success' : 'danger' ?>"><?= $u['actif'] ? 'Actif' : 'Inactif' ?></span></td>
                            <td><a href="?toggle=<?= $u['id_utilisateur'] ?>" class="btn btn-sm btn-outline-secondary">Basculer</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ============================================================
//  PAGE D'AUTHENTIFICATION — auth/auth.php
// ============================================================
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$erreur    = '';
$success   = '';
$modeInit  = ($_GET['mode'] ?? '') === 'inscription' ? 'inscription' : 'connexion';

// ── Traitement CONNEXION ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'connexion') {
        $email = trim($_POST['email'] ?? '');
        $mdp   = $_POST['mot_de_passe'] ?? '';

        if (!$email || !$mdp) {
            $erreur = 'Veuillez remplir tous les champs.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($mdp, $user['mot_de_passe'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_nom']  = $user['nom'];
                $_SESSION['user_role'] = $user['role'];

                // Log
                $db->prepare('INSERT INTO historique (utilisateur_id, action, detail, ip) VALUES (?,?,?,?)')
                   ->execute([$user['id'], 'CONNEXION', 'Connexion réussie', $_SERVER['REMOTE_ADDR']]);

                $redirect = $_GET['redirect'] ?? '../pages/dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $erreur = 'Email ou mot de passe incorrect.';
                $modeInit = 'connexion';
            }
        }
    }

    if ($_POST['action'] === 'inscription') {
        $nom    = trim($_POST['nom']    ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email']  ?? '');
        $mdp    = $_POST['mot_de_passe'] ?? '';
        $mdp2   = $_POST['mot_de_passe_confirm'] ?? '';
        $role   = $_POST['role'] ?? 'agent';
        $modeInit = 'inscription';

        if (!$nom || !$prenom || !$email || !$mdp) {
            $erreur = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Adresse email invalide.';
        } elseif (strlen($mdp) < 8) {
            $erreur = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($mdp !== $mdp2) {
            $erreur = 'Les mots de passe ne correspondent pas.';
        } elseif (!in_array($role, ['admin','agent','medecin'])) {
            $erreur = 'Rôle invalide.';
        } else {
            $db = getDB();
            $exist = $db->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $exist->execute([$email]);
            if ($exist->fetch()) {
                $erreur = 'Un compte existe déjà avec cet email.';
            } else {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $ins  = $db->prepare(
                    'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?,?,?,?,?)'
                );
                $ins->execute([$nom, $prenom, $email, $hash, $role]);
                $success  = 'Compte créé avec succès ! Vous pouvez vous connecter.';
                $modeInit = 'connexion';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="auth-page">

    <!-- ── Côté gauche (visuel) ────────────────────────────── -->
    <div class="auth-gauche">
        <div style="text-align:center;color:#fff;max-width:380px;">
            <div style="font-size:64px;margin-bottom:20px;">🩸</div>
            <h2 style="font-size:1.8rem;font-weight:800;margin-bottom:14px;line-height:1.2;">
                Chaque don compte.<br>Chaque poche aussi.
            </h2>
            <p style="opacity:.75;font-size:.9rem;line-height:1.7;margin-bottom:32px;">
                SangGestion vous aide à gérer les stocks de sang,
                les donneurs et les hôpitaux partenaires de façon
                sécurisée et efficace.
            </p>
            <!-- Groupes sanguins décoratifs -->
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                <span style="background:rgba(255,255,255,.15);border-radius:6px;padding:5px 12px;
                             font-weight:700;font-size:.85rem;backdrop-filter:blur(4px);">
                    <?= $g ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Côté droit (formulaire) ─────────────────────────── -->
    <div class="auth-droite">
        <div class="auth-formulaire">

            <!-- Logo -->
            <div class="auth-logo-bloc">
                <div class="auth-logo-ic">🩸</div>
                <h1 class="auth-titre"><?= APP_NAME ?></h1>
                <p class="auth-sous-titre">Système de gestion de banque de sang</p>
            </div>

            <!-- Onglets -->
            <div class="auth-onglets">
                <button class="auth-onglet <?= $modeInit==='connexion'?'actif':'' ?>"
                        data-onglet="connexion">Connexion</button>
                <button class="auth-onglet <?= $modeInit==='inscription'?'actif':'' ?>"
                        data-onglet="inscription">Créer un compte</button>
            </div>

            <!-- Messages -->
            <?php if ($erreur): ?>
            <div class="alerte danger" style="margin-bottom:16px;">
                <span class="alerte-icone">⚠</span>
                <div>
                    <div class="alerte-titre">Erreur</div>
                    <div class="alerte-texte"><?= htmlspecialchars($erreur) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alerte success" style="margin-bottom:16px;">
                <span class="alerte-icone">✅</span>
                <div>
                    <div class="alerte-titre">Succès</div>
                    <div class="alerte-texte"><?= htmlspecialchars($success) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ FORMULAIRE CONNEXION ═══ -->
            <div data-panneau="connexion"
                 style="display:<?= $modeInit==='connexion'?'block':'none' ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="connexion">

                    <div class="champ" style="margin-bottom:14px;">
                        <label>Email <span class="requis">*</span></label>
                        <input type="email" name="email" placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autocomplete="email">
                    </div>

                    <div class="champ" style="margin-bottom:6px;">
                        <label>Mot de passe <span class="requis">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="mot_de_passe" id="motDePasse"
                                   placeholder="••••••••" required autocomplete="current-password"
                                   style="width:100%;padding-right:44px;">
                            <button type="button" id="toggleMdp"
                                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                           background:none;border:none;cursor:pointer;font-size:16px;">👁</button>
                        </div>
                    </div>

                    <div style="text-align:right;margin-bottom:20px;">
                        <a href="#" style="font-size:.78rem;color:var(--rouge);">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn btn-primaire" style="width:100%;justify-content:center;padding:11px;">
                        🔑 Se connecter
                    </button>
                </form>

                <!-- Accès rapide démo -->
                <div style="margin-top:20px;padding:14px;background:var(--fond);border-radius:8px;border:1px dashed var(--bordure);">
                    <div style="font-size:.74rem;color:var(--texte-fin);text-align:center;margin-bottom:10px;font-weight:600;">
                        ACCÈS DÉMONSTRATION
                    </div>
                    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                        <button onclick="remplirConnexion('admin@sanggestion.com','Admin1234!')"
                                class="btn btn-secondaire btn-sm">Admin</button>
                        <button onclick="remplirConnexion('agent@sanggestion.com','Agent1234!')"
                                class="btn btn-secondaire btn-sm">Agent</button>
                        <button onclick="remplirConnexion('medecin@sanggestion.com','Medecin1234!')"
                                class="btn btn-secondaire btn-sm">Médecin</button>
                    </div>
                </div>
            </div>

            <!-- ═══ FORMULAIRE INSCRIPTION ═══ -->
            <div data-panneau="inscription"
                 style="display:<?= $modeInit==='inscription'?'block':'none' ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="inscription">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div class="champ">
                            <label>Prénom <span class="requis">*</span></label>
                            <input type="text" name="prenom" placeholder="Jean"
                                   value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                        </div>
                        <div class="champ">
                            <label>Nom <span class="requis">*</span></label>
                            <input type="text" name="nom" placeholder="Dupont"
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="champ" style="margin-bottom:14px;">
                        <label>Email <span class="requis">*</span></label>
                        <input type="email" name="email" placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="champ" style="margin-bottom:14px;">
                        <label>Rôle <span class="requis">*</span></label>
                        <select name="role">
                            <option value="agent"   <?= ($_POST['role']??'agent')==='agent'?'selected':'' ?>>Agent de collecte</option>
                            <option value="medecin" <?= ($_POST['role']??'')==='medecin'?'selected':'' ?>>Médecin</option>
                            <option value="admin"   <?= ($_POST['role']??'')==='admin'?'selected':'' ?>>Administrateur</option>
                        </select>
                    </div>

                    <div class="champ" style="margin-bottom:14px;">
                        <label>Mot de passe <span class="requis">*</span></label>
                        <div style="position:relative;">
                            <input type="password" name="mot_de_passe" id="motDePasseReg"
                                   placeholder="Min. 8 caractères" required style="width:100%;padding-right:44px;">
                            <button type="button"
                                    onclick="toggleMdpInline('motDePasseReg',this)"
                                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                           background:none;border:none;cursor:pointer;font-size:16px;">👁</button>
                        </div>
                    </div>

                    <div class="champ" style="margin-bottom:20px;">
                        <label>Confirmer le mot de passe <span class="requis">*</span></label>
                        <input type="password" name="mot_de_passe_confirm"
                               placeholder="Répétez le mot de passe" required>
                    </div>

                    <button type="submit" class="btn btn-primaire" style="width:100%;justify-content:center;padding:11px;">
                        ✅ Créer mon compte
                    </button>
                </form>
            </div>

            <p style="text-align:center;margin-top:20px;font-size:.78rem;color:var(--texte-fin);">
                <a href="../index.php" style="color:var(--texte-fin);">← Retour à l'accueil</a>
            </p>
        </div>
    </div>
</div>

<script src="../js/script.js"></script>
<script>
function remplirConnexion(email, mdp) {
    document.querySelector('[name="email"]').value = email;
    document.getElementById('motDePasse').value = mdp;
}
function toggleMdpInline(inputId, btn) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
}
// Init onglets auth
document.querySelectorAll('.auth-onglet[data-onglet]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.auth-onglet').forEach(b => b.classList.remove('actif'));
        btn.classList.add('actif');
        document.querySelectorAll('[data-panneau]').forEach(p => p.style.display = 'none');
        const p = document.querySelector(`[data-panneau="${btn.dataset.onglet}"]`);
        if (p) p.style.display = 'block';
    });
});
</script>
</body>
</html>

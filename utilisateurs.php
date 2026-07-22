<?php
// ============================================================
//  GESTION DES UTILISATEURS — pages/utilisateurs.php
//  Accès réservé à l'administrateur
// ============================================================
$root       = '../';
$pageTitre  = 'Gestion des utilisateurs';
$pageModule = 'utilisateurs';

require_once __DIR__ . '/../includes/auth_check.php';
exigerRole('admin');

$db = getDB();

// Désactivation (pas de suppression réelle pour préserver l'historique)
if (isset($_GET['desactiver']) && is_numeric($_GET['desactiver'])) {
    $id = (int)$_GET['desactiver'];
    if ($id !== (int)$_SESSION['user_id']) {
        $db->prepare('UPDATE utilisateurs SET actif=0 WHERE id=?')->execute([$id]);
        logAction('DESACTIVATION_UTILISATEUR','utilisateurs',$id);
        header('Location: utilisateurs.php?msg='.urlencode('Utilisateur désactivé.').'&type=success'); exit;
    }
}
if (isset($_GET['activer']) && is_numeric($_GET['activer'])) {
    $id = (int)$_GET['activer'];
    $db->prepare('UPDATE utilisateurs SET actif=1 WHERE id=?')->execute([$id]);
    logAction('ACTIVATION_UTILISATEUR','utilisateurs',$id);
    header('Location: utilisateurs.php?msg='.urlencode('Utilisateur réactivé.').'&type=success'); exit;
}

$erreurForm = '';
$editU      = null;

if (isset($_GET['modifier']) && is_numeric($_GET['modifier'])) {
    $s = $db->prepare('SELECT id,nom,prenom,email,role,actif FROM utilisateurs WHERE id=?');
    $s->execute([(int)$_GET['modifier']]);
    $editU = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom    = trim($_POST['nom']    ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email']  ?? '');
    $role   = $_POST['role']        ?? 'agent';
    $mdp    = $_POST['mot_de_passe'] ?? '';
    $id_e   = (int)($_POST['id_edit'] ?? 0);

    $roles_ok = ['admin','agent','medecin'];

    if (!$nom || !$prenom || !$email) {
        $erreurForm = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurForm = 'Email invalide.';
    } elseif (!in_array($role, $roles_ok)) {
        $erreurForm = 'Rôle invalide.';
    } elseif (!$id_e && strlen($mdp) < 8) {
        $erreurForm = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        $exist = $db->prepare('SELECT id FROM utilisateurs WHERE email=? AND id != ?');
        $exist->execute([$email, $id_e]);
        if ($exist->fetch()) {
            $erreurForm = 'Cet email est déjà utilisé par un autre compte.';
        } else {
            if ($id_e > 0) {
                if ($mdp) {
                    $hash = password_hash($mdp, PASSWORD_DEFAULT);
                    $db->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=?,mot_de_passe=? WHERE id=?')
                       ->execute([$nom,$prenom,$email,$role,$hash,$id_e]);
                } else {
                    $db->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,role=? WHERE id=?')
                       ->execute([$nom,$prenom,$email,$role,$id_e]);
                }
                logAction('MODIFICATION_UTILISATEUR','utilisateurs',$id_e,"$prenom $nom");
                header('Location: utilisateurs.php?msg='.urlencode('Utilisateur modifié.').'&type=success');
            } else {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO utilisateurs (nom,prenom,email,mot_de_passe,role) VALUES (?,?,?,?,?)')
                   ->execute([$nom,$prenom,$email,$hash,$role]);
                $nid = $db->lastInsertId();
                logAction('AJOUT_UTILISATEUR','utilisateurs',$nid,"$prenom $nom ($role)");
                header('Location: utilisateurs.php?msg='.urlencode('Utilisateur créé.').'&type=success');
            }
            exit;
        }
    }
}

$search = trim($_GET['q'] ?? '');
$where  = ['1=1']; $params = [];
if ($search) { $where[] = '(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)'; $s="%$search%"; $params=[$s,$s,$s]; }
$ws = 'WHERE '.implode(' AND ', $where);

$stmt = $db->prepare("SELECT * FROM utilisateurs $ws ORDER BY cree_le DESC");
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">⚙️ Utilisateurs du système</div>
        <div class="section-sous-titre"><?= count($utilisateurs) ?> compte(s)</div>
    </div>
    <button class="btn btn-primaire" onclick="reinitUser()">＋ Créer un utilisateur</button>
</div>

<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, email…">
            </div>
        </form>
    </div>
</div>

<div class="carte-tableau">
    <div class="table-wrapper">
        <table id="tableauPrincipal">
            <thead>
                <tr><th>#</th><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Créé le</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td style="color:var(--texte-fin);font-size:.78rem;">#<?= $u['id'] ?></td>
                    <td><strong><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></strong>
                        <?php if ($u['id']==$_SESSION['user_id']): ?>
                        <span style="font-size:.7rem;color:var(--texte-fin);">(vous)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge-role <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['actif']): ?>
                        <span class="badge-statut disponible">Actif</span>
                        <?php else: ?>
                        <span class="badge-statut perime">Désactivé</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($u['cree_le'])) ?></td>
                    <td>
                        <div class="actions-tableau">
                            <button class="btn btn-secondaire btn-sm btn-icone-seul" data-tooltip="Modifier"
                                    onclick="ouvrirModifUser(<?= htmlspecialchars(json_encode($u)) ?>)">✏️</button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <?php if ($u['actif']): ?>
                                <a href="utilisateurs.php?desactiver=<?= $u['id'] ?>" class="btn btn-danger btn-sm btn-icone-seul" data-tooltip="Désactiver">🚫</a>
                                <?php else: ?>
                                <a href="utilisateurs.php?activer=<?= $u['id'] ?>" class="btn btn-secondaire btn-sm btn-icone-seul" data-tooltip="Réactiver">✅</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal utilisateur -->
<div class="modal-overlay" id="modalUser" style="display:<?= $erreurForm||$editU?'flex':'none' ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titre" id="titreModalUser">⚙️ Créer un utilisateur</div>
            <button class="modal-fermer" onclick="fermerModal('modalUser')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-corps">
                <input type="hidden" name="id_edit" id="idEditUser" value="<?= $editU?$editU['id']:0 ?>">
                <?php if ($erreurForm): ?>
                <div class="alerte danger" style="margin-bottom:14px;"><span>⚠</span><div><?= htmlspecialchars($erreurForm) ?></div></div>
                <?php endif; ?>
                <div class="form-grille col-2">
                    <div class="champ">
                        <label>Prénom <span class="requis">*</span></label>
                        <input type="text" name="prenom" id="uPrenom" value="<?= htmlspecialchars($editU['prenom']??'') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Nom <span class="requis">*</span></label>
                        <input type="text" name="nom" id="uNom" value="<?= htmlspecialchars($editU['nom']??'') ?>" required>
                    </div>
                    <div class="champ pleine-largeur">
                        <label>Email <span class="requis">*</span></label>
                        <input type="email" name="email" id="uEmail" value="<?= htmlspecialchars($editU['email']??'') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Rôle <span class="requis">*</span></label>
                        <select name="role" id="uRole">
                            <option value="agent">Agent</option>
                            <option value="medecin">Médecin</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Mot de passe <span id="mdpRequis" class="requis">*</span></label>
                        <input type="password" name="mot_de_passe" id="uMdp" placeholder="Min. 8 caractères">
                        <span class="aide" id="mdpAide">Laisser vide pour conserver le mot de passe actuel</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondaire" onclick="fermerModal('modalUser')">Annuler</button>
                <button type="submit" class="btn btn-primaire">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function reinitUser() {
    document.getElementById('titreModalUser').textContent = '⚙️ Créer un utilisateur';
    document.getElementById('idEditUser').value = 0;
    document.getElementById('uPrenom').value = '';
    document.getElementById('uNom').value = '';
    document.getElementById('uEmail').value = '';
    document.getElementById('uRole').value = 'agent';
    document.getElementById('uMdp').value = '';
    document.getElementById('uMdp').required = true;
    document.getElementById('mdpAide').style.display = 'none';
    ouvrirModal('modalUser');
}
function ouvrirModifUser(u) {
    document.getElementById('titreModalUser').textContent = '✏️ Modifier l\'utilisateur';
    document.getElementById('idEditUser').value = u.id;
    document.getElementById('uPrenom').value = u.prenom;
    document.getElementById('uNom').value = u.nom;
    document.getElementById('uEmail').value = u.email;
    document.getElementById('uRole').value = u.role;
    document.getElementById('uMdp').value = '';
    document.getElementById('uMdp').required = false;
    document.getElementById('mdpAide').style.display = 'inline';
    ouvrirModal('modalUser');
}
</script>

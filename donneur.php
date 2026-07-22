<?php
// ============================================================
//  GESTION DES DONNEURS — pages/donneur.php
// ============================================================
$root       = '../';
$pageTitre  = 'Gestion des donneurs';
$pageModule = 'donneurs';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// ── Gestion des actions GET (suppression) ────────────────────
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    exigerRole('admin', 'agent');
    $id = (int)$_GET['supprimer'];
    $d  = $db->prepare('SELECT nom, prenom FROM donneurs WHERE id = ?');
    $d->execute([$id]);
    $dn = $d->fetch();
    if ($dn) {
        $db->prepare('UPDATE donneurs SET actif = 0 WHERE id = ?')->execute([$id]);
        logAction('SUPPRESSION_DONNEUR', 'donneurs', $id, $dn['prenom'].' '.$dn['nom']);
        header('Location: donneur.php?msg=' . urlencode('Donneur supprimé.') . '&type=success');
        exit;
    }
}

// ── Traitement formulaire ajout/modification ─────────────────
$erreurForm = '';
$editDonneur = null;

if (isset($_GET['modifier']) && is_numeric($_GET['modifier'])) {
    $stmt = $db->prepare('SELECT * FROM donneurs WHERE id = ? AND actif = 1');
    $stmt->execute([(int)$_GET['modifier']]);
    $editDonneur = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole('admin', 'agent');

    $nom       = trim($_POST['nom']       ?? '');
    $prenom    = trim($_POST['prenom']    ?? '');
    $sexe      = $_POST['sexe']           ?? '';
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse   = trim($_POST['adresse']   ?? '');
    $groupe    = $_POST['groupe_sanguin'] ?? '';
    $date_ins  = $_POST['date_inscription'] ?? date('Y-m-d');
    $id_edit   = (int)($_POST['id_edit']  ?? 0);

    $groupes_valides = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

    if (!$nom || !$prenom || !$sexe || !$groupe) {
        $erreurForm = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!in_array($groupe, $groupes_valides)) {
        $erreurForm = 'Groupe sanguin invalide.';
    } elseif (!in_array($sexe, ['M','F'])) {
        $erreurForm = 'Sexe invalide.';
    } else {
        if ($id_edit > 0) {
            $db->prepare(
                'UPDATE donneurs SET nom=?,prenom=?,sexe=?,telephone=?,adresse=?,groupe_sanguin=?,date_inscription=? WHERE id=?'
            )->execute([$nom,$prenom,$sexe,$telephone,$adresse,$groupe,$date_ins,$id_edit]);
            logAction('MODIFICATION_DONNEUR','donneurs',$id_edit,"$prenom $nom");
            header('Location: donneur.php?msg=' . urlencode('Donneur modifié avec succès.') . '&type=success');
        } else {
            $ins = $db->prepare(
                'INSERT INTO donneurs (nom,prenom,sexe,telephone,adresse,groupe_sanguin,date_inscription) VALUES (?,?,?,?,?,?,?)'
            );
            $ins->execute([$nom,$prenom,$sexe,$telephone,$adresse,$groupe,$date_ins]);
            $newId = $db->lastInsertId();
            logAction('AJOUT_DONNEUR','donneurs',$newId,"$prenom $nom");
            header('Location: donneur.php?msg=' . urlencode('Donneur ajouté avec succès.') . '&type=success');
        }
        exit;
    }
}

// ── Récupération liste ────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filtre_groupe = $_GET['groupe'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where  = ['d.actif = 1'];
$params = [];

if ($search) {
    $where[]  = '(d.nom LIKE ? OR d.prenom LIKE ? OR d.telephone LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s,$s,$s]);
}
if ($filtre_groupe) {
    $where[]  = 'd.groupe_sanguin = ?';
    $params[] = $filtre_groupe;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM donneurs d $whereStr");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt = $db->prepare(
    "SELECT d.*,
            (SELECT MAX(date_don) FROM dons WHERE donneur_id = d.id) as dernier_don,
            (SELECT COUNT(*)      FROM dons WHERE donneur_id = d.id) as nb_dons
     FROM donneurs d $whereStr
     ORDER BY d.cree_le DESC LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$donneurs = $stmt->fetchAll();

// ── Historique dons d'un donneur (modal) ──────────────────────
$histDonneur = null;
if (isset($_GET['historique']) && is_numeric($_GET['historique'])) {
    $hid = (int)$_GET['historique'];
    $hd  = $db->prepare('SELECT * FROM donneurs WHERE id = ?');
    $hd->execute([$hid]);
    $histDonneur = $hd->fetch();
    if ($histDonneur) {
        $hDons = $db->prepare('SELECT * FROM dons WHERE donneur_id = ? ORDER BY date_don DESC');
        $hDons->execute([$hid]);
        $histDons = $hDons->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ════════════ EN-TÊTE SECTION ════════════ -->
<div class="section-header">
    <div>
        <div class="section-titre">👤 Donneurs</div>
        <div class="section-sous-titre"><?= number_format($total) ?> donneur(s) enregistré(s)</div>
    </div>
    <button class="btn btn-primaire" data-ouvrir-modal="modalDonneur"
            onclick="reinitFormDonneur()">
        ＋ Ajouter un donneur
    </button>
</div>

<!-- ════════════ FILTRES ════════════ -->
<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Nom, prénom, téléphone…"
                       id="rechercheTableau">
            </div>
            <div class="champ" style="margin-bottom:0">
                <select name="groupe" style="padding:8px 13px;border:1px solid var(--bordure);
                    border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;background:var(--fond);">
                    <option value="">Tous les groupes</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                    <option value="<?= $g ?>" <?= $filtre_groupe===$g?'selected':'' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondaire">Filtrer</button>
            <?php if ($search || $filtre_groupe): ?>
            <a href="donneur.php" class="btn btn-secondaire">✕ Effacer</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ════════════ TABLEAU ════════════ -->
<div class="carte-tableau">
    <div class="table-wrapper">
        <table id="tableauPrincipal">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Donneur</th>
                    <th>Sexe</th>
                    <th>Groupe</th>
                    <th>Téléphone</th>
                    <th>Inscrit le</th>
                    <th>Dernier don</th>
                    <th>Nb dons</th>
                    <th>Statut don</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($donneurs)): ?>
                <tr>
                    <td colspan="10">
                        <div class="etat-vide">
                            <div class="etat-vide-icone">👤</div>
                            <div class="etat-vide-titre">Aucun donneur trouvé</div>
                            <div class="etat-vide-texte">Ajoutez un donneur ou modifiez vos filtres.</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($donneurs as $d):
                    $gc = str_replace(['+','-'], ['pos','neg'], $d['groupe_sanguin']);
                    // Vérification délai 90 jours
                    $peutDonner = true;
                    $joursDepuis = null;
                    if ($d['dernier_don']) {
                        $diff = (new DateTime())->diff(new DateTime($d['dernier_don']));
                        $joursDepuis = $diff->days;
                        $peutDonner  = $joursDepuis >= DELAI_MIN_DON;
                    }
                ?>
                <tr>
                    <td style="color:var(--texte-fin);font-size:.78rem;">#<?= $d['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong>
                        <?php if ($d['adresse']): ?>
                        <div style="font-size:.74rem;color:var(--texte-fin);">
                            <?= htmlspecialchars(mb_strimwidth($d['adresse'], 0, 40, '…')) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?= $d['sexe'] === 'M' ? '♂ Homme' : '♀ Femme' ?></td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $d['groupe_sanguin'] ?></span></td>
                    <td><?= htmlspecialchars($d['telephone'] ?: '—') ?></td>
                    <td><?= date('d/m/Y', strtotime($d['date_inscription'])) ?></td>
                    <td>
                        <?= $d['dernier_don']
                            ? date('d/m/Y', strtotime($d['dernier_don']))
                            : '<span style="color:var(--texte-fin)">Aucun</span>' ?>
                    </td>
                    <td style="text-align:center;font-weight:700;"><?= $d['nb_dons'] ?></td>
                    <td>
                        <?php if ($peutDonner): ?>
                            <span class="badge-statut disponible">Peut donner</span>
                        <?php else: ?>
                            <span class="badge-statut critique"
                                  data-tooltip="<?= DELAI_MIN_DON - $joursDepuis ?> jours restants">
                                En attente
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions-tableau">
                            <a href="donneur.php?historique=<?= $d['id'] ?>"
                               class="btn btn-secondaire btn-sm btn-icone-seul"
                               data-tooltip="Historique dons">📋</a>

                            <button class="btn btn-secondaire btn-sm btn-icone-seul"
                                    data-tooltip="Modifier"
                                    onclick="ouvrirModification(<?= htmlspecialchars(json_encode($d)) ?>)">
                                ✏️
                            </button>

                            <button class="btn btn-danger btn-sm btn-icone-seul"
                                    data-tooltip="Supprimer"
                                    onclick="confirmerSuppression('donneur.php?supprimer=<?= $d['id'] ?>',
                                             'Supprimer <?= htmlspecialchars(addslashes($d['prenom'].' '.$d['nom'])) ?> ?')">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span>Affichage <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> sur <?= $total ?></span>
        <div class="pagination-pages">
            <?php if ($page > 1): ?>
            <a href="?q=<?= urlencode($search) ?>&groupe=<?= urlencode($filtre_groupe) ?>&page=<?= $page-1 ?>"
               class="page-btn">‹</a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?q=<?= urlencode($search) ?>&groupe=<?= urlencode($filtre_groupe) ?>&page=<?= $i ?>"
               class="page-btn <?= $i===$page?'actif':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?q=<?= urlencode($search) ?>&groupe=<?= urlencode($filtre_groupe) ?>&page=<?= $page+1 ?>"
               class="page-btn">›</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════ MODAL AJOUT / MODIFICATION ════════════ -->
<div class="modal-overlay" id="modalDonneur" style="display:<?= ($erreurForm || $editDonneur) ? 'flex' : 'none' ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titre">
                <span id="modalDonneurTitre">👤 Ajouter un donneur</span>
            </div>
            <button class="modal-fermer" onclick="fermerModal('modalDonneur')">✕</button>
        </div>
        <form method="POST" id="formDonneur">
            <div class="modal-corps">
                <input type="hidden" name="id_edit" id="idEdit"
                       value="<?= $editDonneur ? $editDonneur['id'] : 0 ?>">

                <?php if ($erreurForm): ?>
                <div class="alerte danger" style="margin-bottom:14px;">
                    <span>⚠</span><div><?= htmlspecialchars($erreurForm) ?></div>
                </div>
                <?php endif; ?>

                <div class="form-grille col-2">
                    <div class="champ">
                        <label>Prénom <span class="requis">*</span></label>
                        <input type="text" name="prenom" id="champPrenom"
                               value="<?= htmlspecialchars($editDonneur['prenom'] ?? $_POST['prenom'] ?? '') ?>"
                               placeholder="Jean" required>
                    </div>
                    <div class="champ">
                        <label>Nom <span class="requis">*</span></label>
                        <input type="text" name="nom" id="champNom"
                               value="<?= htmlspecialchars($editDonneur['nom'] ?? $_POST['nom'] ?? '') ?>"
                               placeholder="Dupont" required>
                    </div>
                    <div class="champ">
                        <label>Sexe <span class="requis">*</span></label>
                        <select name="sexe" id="champSexe" required>
                            <option value="">— Choisir —</option>
                            <option value="M" <?= ($editDonneur['sexe']??$_POST['sexe']??'')==='M'?'selected':'' ?>>Homme</option>
                            <option value="F" <?= ($editDonneur['sexe']??$_POST['sexe']??'')==='F'?'selected':'' ?>>Femme</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Groupe sanguin <span class="requis">*</span></label>
                        <select name="groupe_sanguin" id="champGroupe" required>
                            <option value="">— Choisir —</option>
                            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                            <option value="<?= $g ?>"
                                <?= ($editDonneur['groupe_sanguin']??$_POST['groupe_sanguin']??'')===$g?'selected':'' ?>>
                                <?= $g ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" id="champTel"
                               value="<?= htmlspecialchars($editDonneur['telephone'] ?? $_POST['telephone'] ?? '') ?>"
                               placeholder="+243 …">
                    </div>
                    <div class="champ">
                        <label>Date d'inscription</label>
                        <input type="date" name="date_inscription" id="champDateIns"
                               value="<?= $editDonneur['date_inscription'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="champ pleine-largeur">
                        <label>Adresse</label>
                        <input type="text" name="adresse" id="champAdresse"
                               value="<?= htmlspecialchars($editDonneur['adresse'] ?? $_POST['adresse'] ?? '') ?>"
                               placeholder="Quartier, Commune, Ville">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondaire" onclick="fermerModal('modalDonneur')">Annuler</button>
                <button type="submit" class="btn btn-primaire">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════ MODAL HISTORIQUE DONS ════════════ -->
<?php if ($histDonneur): ?>
<div class="modal-overlay" id="modalHistorique" style="display:flex">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <div class="modal-titre">
                📋 Dons de <?= htmlspecialchars($histDonneur['prenom'].' '.$histDonneur['nom']) ?>
                <span class="badge-sang <?= str_replace(['+','-'], ['pos','neg'], $histDonneur['groupe_sanguin']) ?>">
                    <?= $histDonneur['groupe_sanguin'] ?>
                </span>
            </div>
            <a href="donneur.php" class="modal-fermer">✕</a>
        </div>
        <div class="modal-corps">
            <?php if (empty($histDons)): ?>
            <div class="etat-vide">
                <div class="etat-vide-icone">🩸</div>
                <div class="etat-vide-titre">Aucun don enregistré</div>
            </div>
            <?php else: ?>

            <!-- Bouton enregistrer un nouveau don -->
            <div style="margin-bottom:16px;display:flex;justify-content:flex-end;">
                <button class="btn btn-primaire btn-sm"
                        onclick="window.location='../actions/save_don.php?donneur_id=<?= $histDonneur['id'] ?>'">
                    ＋ Enregistrer un don
                </button>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>#</th><th>Date</th><th>Quantité</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($histDons as $don): ?>
                        <tr>
                            <td>#<?= $don['id'] ?></td>
                            <td><strong><?= date('d/m/Y', strtotime($don['date_don'])) ?></strong></td>
                            <td><?= $don['quantite_ml'] ?> ml</td>
                            <td><?= htmlspecialchars($don['notes'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <a href="donneur.php" class="btn btn-secondaire">Fermer</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal confirmation suppression générique -->
<div class="modal-overlay" id="modalConfirm" style="display:none">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-titre">🗑️ Confirmer la suppression</div>
            <button class="modal-fermer" onclick="fermerModal('modalConfirm')">✕</button>
        </div>
        <div class="modal-corps">
            <p id="confirmMessage" style="color:var(--texte-doux);font-size:.9rem;"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondaire" onclick="fermerModal('modalConfirm')">Annuler</button>
            <button id="confirmOui" class="btn btn-danger">Supprimer</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function reinitFormDonneur() {
    document.getElementById('modalDonneurTitre').textContent = '👤 Ajouter un donneur';
    document.getElementById('idEdit').value = 0;
    ['Prenom','Nom','Tel','Adresse'].forEach(f => {
        const el = document.getElementById('champ'+f);
        if (el) el.value = '';
    });
    document.getElementById('champSexe').value = '';
    document.getElementById('champGroupe').value = '';
    document.getElementById('champDateIns').value = new Date().toISOString().split('T')[0];
}

function ouvrirModification(d) {
    document.getElementById('modalDonneurTitre').textContent = '✏️ Modifier le donneur';
    document.getElementById('idEdit').value        = d.id;
    document.getElementById('champPrenom').value   = d.prenom;
    document.getElementById('champNom').value      = d.nom;
    document.getElementById('champSexe').value     = d.sexe;
    document.getElementById('champGroupe').value   = d.groupe_sanguin;
    document.getElementById('champTel').value      = d.telephone || '';
    document.getElementById('champAdresse').value  = d.adresse || '';
    document.getElementById('champDateIns').value  = d.date_inscription;
    ouvrirModal('modalDonneur');
}
</script>

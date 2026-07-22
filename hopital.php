<?php
// ============================================================
//  GESTION DES HÔPITAUX — pages/hopital.php
// ============================================================
$root       = '../';
$pageTitre  = 'Hôpitaux partenaires';
$pageModule = 'hopitaux';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// Suppression
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    exigerRole('admin');
    $id = (int)$_GET['supprimer'];
    $db->prepare('UPDATE hopitaux SET actif=0 WHERE id=?')->execute([$id]);
    logAction('SUPPRESSION_HOPITAL','hopitaux',$id);
    header('Location: hopital.php?msg='.urlencode('Hôpital supprimé.').'&type=success'); exit;
}

$erreurForm = '';
$editH      = null;

if (isset($_GET['modifier']) && is_numeric($_GET['modifier'])) {
    $s = $db->prepare('SELECT * FROM hopitaux WHERE id=?'); $s->execute([(int)$_GET['modifier']]);
    $editH = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole('admin','agent');
    $nom  = trim($_POST['nom']       ?? '');
    $adr  = trim($_POST['adresse']   ?? '');
    $tel  = trim($_POST['telephone'] ?? '');
    $mail = trim($_POST['email']     ?? '');
    $id_e = (int)($_POST['id_edit']  ?? 0);

    if (!$nom) { $erreurForm = 'Le nom est obligatoire.'; }
    elseif ($mail && !filter_var($mail, FILTER_VALIDATE_EMAIL)) { $erreurForm = 'Email invalide.'; }
    else {
        if ($id_e > 0) {
            $db->prepare('UPDATE hopitaux SET nom=?,adresse=?,telephone=?,email=? WHERE id=?')
               ->execute([$nom,$adr,$tel,$mail,$id_e]);
            logAction('MODIFICATION_HOPITAL','hopitaux',$id_e,$nom);
            header('Location: hopital.php?msg='.urlencode('Hôpital modifié.').'&type=success');
        } else {
            $db->prepare('INSERT INTO hopitaux (nom,adresse,telephone,email) VALUES (?,?,?,?)')
               ->execute([$nom,$adr,$tel,$mail]);
            $nid = $db->lastInsertId();
            logAction('AJOUT_HOPITAL','hopitaux',$nid,$nom);
            header('Location: hopital.php?msg='.urlencode('Hôpital ajouté.').'&type=success');
        }
        exit;
    }
}

$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 12; $offset = ($page-1)*$limit;
$where  = ['actif=1']; $params = [];
if ($search) { $where[] = '(nom LIKE ? OR adresse LIKE ? OR telephone LIKE ?)'; $s="%$search%"; $params=[$s,$s,$s]; }
$ws    = 'WHERE '.implode(' AND ', $where);
$total = $db->prepare("SELECT COUNT(*) FROM hopitaux $ws"); $total->execute($params); $total = $total->fetchColumn();
$totalPages = ceil($total/$limit);
$stmt = $db->prepare("SELECT h.*, (SELECT COUNT(*) FROM ventes WHERE hopital_id=h.id) as nb_ventes FROM hopitaux h $ws ORDER BY h.nom ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$hopitaux = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">🏥 Hôpitaux partenaires</div>
        <div class="section-sous-titre"><?= $total ?> hôpital(ux) enregistré(s)</div>
    </div>
    <button class="btn btn-primaire" data-ouvrir-modal="modalHopital" onclick="reinitHop()">＋ Ajouter un hôpital</button>
</div>

<!-- Filtre -->
<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, adresse…">
            </div>
            <button type="submit" class="btn btn-secondaire">Filtrer</button>
            <?php if ($search): ?><a href="hopital.php" class="btn btn-secondaire">✕</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Grille cartes hôpitaux -->
<?php if (empty($hopitaux)): ?>
<div class="carte-tableau">
    <div class="etat-vide">
        <div class="etat-vide-icone">🏥</div>
        <div class="etat-vide-titre">Aucun hôpital trouvé</div>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px;">
    <?php foreach ($hopitaux as $h): ?>
    <div class="carte-tableau" style="padding:20px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;">
            <div style="width:44px;height:44px;background:var(--bleu-clair);border-radius:10px;
                        display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🏥</div>
            <div class="actions-tableau">
                <button class="btn btn-secondaire btn-sm btn-icone-seul" data-tooltip="Modifier"
                        onclick="ouvrirModifHop(<?= htmlspecialchars(json_encode($h)) ?>)">✏️</button>
                <?php if ($userActuel['role']==='admin'): ?>
                <button class="btn btn-danger btn-sm btn-icone-seul" data-tooltip="Supprimer"
                        onclick="confirmerSuppression('hopital.php?supprimer=<?= $h['id'] ?>','Supprimer <?= htmlspecialchars(addslashes($h['nom'])) ?> ?')">🗑️</button>
                <?php endif; ?>
            </div>
        </div>
        <h3 style="font-size:.95rem;font-weight:700;color:var(--texte);margin-bottom:8px;line-height:1.3;">
            <?= htmlspecialchars($h['nom']) ?>
        </h3>
        <?php if ($h['adresse']): ?>
        <div style="display:flex;gap:6px;align-items:flex-start;font-size:.8rem;color:var(--texte-fin);margin-bottom:5px;">
            <span>📍</span><span><?= htmlspecialchars($h['adresse']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($h['telephone']): ?>
        <div style="display:flex;gap:6px;align-items:center;font-size:.8rem;color:var(--texte-fin);margin-bottom:5px;">
            <span>📞</span><span><?= htmlspecialchars($h['telephone']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($h['email']): ?>
        <div style="display:flex;gap:6px;align-items:center;font-size:.8rem;color:var(--texte-fin);margin-bottom:5px;">
            <span>✉️</span><a href="mailto:<?= htmlspecialchars($h['email']) ?>" style="color:var(--bleu)"><?= htmlspecialchars($h['email']) ?></a>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--bordure);
                    font-size:.78rem;color:var(--texte-fin);">
            📦 <strong style="color:var(--texte)"><?= $h['nb_ventes'] ?></strong> distribution(s)
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="background:var(--blanc);border-radius:var(--rayon);border:1px solid var(--bordure);">
    <span>Page <?= $page ?> / <?= $totalPages ?></span>
    <div class="pagination-pages">
        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
        <a href="?q=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'actif':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Modal hôpital -->
<div class="modal-overlay" id="modalHopital" style="display:<?= $erreurForm||$editH?'flex':'none' ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titre" id="titreModalHop">🏥 Ajouter un hôpital</div>
            <button class="modal-fermer" onclick="fermerModal('modalHopital')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-corps">
                <input type="hidden" name="id_edit" id="idEditHop" value="<?= $editH?$editH['id']:0 ?>">
                <?php if ($erreurForm): ?>
                <div class="alerte danger" style="margin-bottom:14px;"><span>⚠</span><div><?= htmlspecialchars($erreurForm) ?></div></div>
                <?php endif; ?>
                <div class="form-grille col-1" style="gap:14px;">
                    <div class="champ">
                        <label>Nom de l'hôpital <span class="requis">*</span></label>
                        <input type="text" name="nom" id="hNom" value="<?= htmlspecialchars($editH['nom']??'') ?>" placeholder="Hôpital Général de …" required>
                    </div>
                    <div class="champ">
                        <label>Adresse</label>
                        <input type="text" name="adresse" id="hAdr" value="<?= htmlspecialchars($editH['adresse']??'') ?>" placeholder="Avenue, Commune, Ville">
                    </div>
                    <div class="form-grille col-2" style="gap:14px;margin:0;">
                        <div class="champ">
                            <label>Téléphone</label>
                            <input type="tel" name="telephone" id="hTel" value="<?= htmlspecialchars($editH['telephone']??'') ?>">
                        </div>
                        <div class="champ">
                            <label>Email</label>
                            <input type="email" name="email" id="hMail" value="<?= htmlspecialchars($editH['email']??'') ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondaire" onclick="fermerModal('modalHopital')">Annuler</button>
                <button type="submit" class="btn btn-primaire">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalConfirm" style="display:none">
    <div class="modal" style="max-width:420px">
        <div class="modal-header"><div class="modal-titre">🗑️ Confirmation</div><button class="modal-fermer" onclick="fermerModal('modalConfirm')">✕</button></div>
        <div class="modal-corps"><p id="confirmMessage" style="color:var(--texte-doux);font-size:.9rem;"></p></div>
        <div class="modal-footer">
            <button class="btn btn-secondaire" onclick="fermerModal('modalConfirm')">Annuler</button>
            <button id="confirmOui" class="btn btn-danger">Confirmer</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function reinitHop() {
    document.getElementById('titreModalHop').textContent = '🏥 Ajouter un hôpital';
    document.getElementById('idEditHop').value = 0;
    ['hNom','hAdr','hTel','hMail'].forEach(id => document.getElementById(id).value = '');
}
function ouvrirModifHop(h) {
    document.getElementById('titreModalHop').textContent = '✏️ Modifier l\'hôpital';
    document.getElementById('idEditHop').value = h.id;
    document.getElementById('hNom').value  = h.nom;
    document.getElementById('hAdr').value  = h.adresse || '';
    document.getElementById('hTel').value  = h.telephone || '';
    document.getElementById('hMail').value = h.email || '';
    ouvrirModal('modalHopital');
}
</script>

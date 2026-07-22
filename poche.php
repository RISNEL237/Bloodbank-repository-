<?php
// ============================================================
//  GESTION DES POCHES — pages/poche.php
// ============================================================
$root       = '../';
$pageTitre  = 'Poches de sang';
$pageModule = 'poches';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// ── Suppression ──────────────────────────────────────────────
if (isset($_GET['supprimer']) && is_numeric($_GET['supprimer'])) {
    exigerRole('admin');
    $id = (int)$_GET['supprimer'];
    $db->prepare("UPDATE poches SET etat='perime' WHERE id=?")->execute([$id]);
    logAction('SUPPRESSION_POCHE','poches',$id);
    header('Location: poche.php?msg='.urlencode('Poche retirée du stock.').'&type=success');
    exit;
}

// ── Ajout / Modification ─────────────────────────────────────
$erreurForm  = '';
$editPoche   = null;

if (isset($_GET['modifier']) && is_numeric($_GET['modifier'])) {
    $s = $db->prepare('SELECT * FROM poches WHERE id=?');
    $s->execute([(int)$_GET['modifier']]);
    $editPoche = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole('admin','agent');

    $groupe    = $_POST['groupe_sanguin']  ?? '';
    $qte       = (int)($_POST['quantite_ml'] ?? 0);
    $collecte  = $_POST['date_collecte']   ?? '';
    $expir     = $_POST['date_expiration'] ?? '';
    $etat      = $_POST['etat']            ?? 'disponible';
    $notes     = trim($_POST['notes']      ?? '');
    $don_id    = (int)($_POST['don_id']    ?? 0);
    $id_edit   = (int)($_POST['id_edit']   ?? 0);

    $groupes_ok = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    $etats_ok   = ['disponible','distribue','expire','perime'];

    if (!$groupe || !$qte || !$collecte || !$expir) {
        $erreurForm = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!in_array($groupe, $groupes_ok)) {
        $erreurForm = 'Groupe sanguin invalide.';
    } elseif ($expir <= $collecte) {
        $erreurForm = 'La date d\'expiration doit être postérieure à la date de collecte.';
    } else {
        if ($id_edit > 0) {
            $db->prepare(
                'UPDATE poches SET groupe_sanguin=?,quantite_ml=?,date_collecte=?,date_expiration=?,etat=?,notes=? WHERE id=?'
            )->execute([$groupe,$qte,$collecte,$expir,$etat,$notes,$id_edit]);
            logAction('MODIFICATION_POCHE','poches',$id_edit,"Groupe $groupe");
            header('Location: poche.php?msg='.urlencode('Poche modifiée.').'&type=success');
        } else {
            $db->prepare(
                'INSERT INTO poches (don_id,groupe_sanguin,quantite_ml,date_collecte,date_expiration,etat,notes) VALUES (?,?,?,?,?,?,?)'
            )->execute([$don_id ?: null,$groupe,$qte,$collecte,$expir,$etat,$notes]);
            $nid = $db->lastInsertId();
            logAction('AJOUT_POCHE','poches',$nid,"Groupe $groupe $qte ml");
            header('Location: poche.php?msg='.urlencode('Poche ajoutée au stock.').'&type=success');
        }
        exit;
    }
}

// ── Filtres & liste ───────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$fGroupe = $_GET['groupe'] ?? '';
$fEtat   = $_GET['etat']   ?? '';
$page    = max(1,(int)($_GET['page'] ?? 1));
$limit   = 15; $offset = ($page-1)*$limit;

$where  = ['1=1']; $params = [];
if ($search)  { $where[] = "(p.groupe_sanguin LIKE ? OR p.notes LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
if ($fGroupe) { $where[] = "p.groupe_sanguin = ?"; $params[] = $fGroupe; }
if ($fEtat)   { $where[] = "p.etat = ?";           $params[] = $fEtat;  }
$ws = 'WHERE '.implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM poches p $ws");
$total->execute($params); $total = $total->fetchColumn();
$totalPages = ceil($total/$limit);

$stmt = $db->prepare("SELECT p.*, d.donneur_id,
    CONCAT(don.prenom,' ',don.nom) as donneur_nom
    FROM poches p
    LEFT JOIN dons d ON d.id = p.don_id
    LEFT JOIN donneurs don ON don.id = d.donneur_id
    $ws ORDER BY p.date_collecte DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$poches = $stmt->fetchAll();

// Mettre à jour les poches expirées automatiquement
$db->exec("UPDATE poches SET etat='expire' WHERE etat='disponible' AND date_expiration < CURDATE()");

// Résumé stock
$resume = $db->query(
    "SELECT groupe_sanguin, SUM(quantite_ml) as total_ml, COUNT(*) as nb
     FROM poches WHERE etat='disponible'
     GROUP BY groupe_sanguin ORDER BY groupe_sanguin"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ════════════ EN-TÊTE ════════════ -->
<div class="section-header">
    <div>
        <div class="section-titre">🩸 Poches de sang</div>
        <div class="section-sous-titre"><?= number_format($total) ?> poche(s)</div>
    </div>
    <button class="btn btn-primaire" data-ouvrir-modal="modalPoche"
            onclick="reinitFormPoche()">
        ＋ Ajouter une poche
    </button>
</div>

<!-- ════════════ RÉSUMÉ STOCK ════════════ -->
<?php if (!empty($resume)): ?>
<div class="carte-tableau" style="margin-bottom:18px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">Résumé du stock disponible</span>
    </div>
    <div style="padding:14px 20px;">
        <div class="stock-grid">
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g):
                $r   = array_filter($resume, fn($x) => $x['groupe_sanguin']===$g);
                $r   = reset($r);
                $nb  = $r ? $r['nb'] : 0;
                $ml  = $r ? $r['total_ml'] : 0;
                $gc  = str_replace(['+','-'], ['pos','neg'], $g);
                $niv = $nb < SEUIL_STOCK_CRITIQUE ? 'critique' : ($nb < 10 ? 'bas' : 'bon');
                $max = max(array_column($resume, 'nb') ?: [1]);
                $pct = $max > 0 ? min(100, round($nb/$max*100)) : 0;
            ?>
            <div class="stock-carte <?= $nb < SEUIL_STOCK_CRITIQUE ? 'critique' : '' ?>">
                <div class="stock-groupe"><span class="badge-sang <?= $gc ?>"><?= $g ?></span></div>
                <div class="stock-quantite"><?= $nb ?></div>
                <div class="stock-sous"><?= number_format($ml) ?> ml total</div>
                <div class="jauge-conteneur">
                    <div class="jauge-barre <?= $niv ?>" data-pct="<?= $pct ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <?php if ($nb < SEUIL_STOCK_CRITIQUE): ?>
                <div style="font-size:.65rem;color:var(--rouge);font-weight:700;margin-top:4px;">⚠ CRITIQUE</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ FILTRES ════════════ -->
<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Groupe, notes…">
            </div>
            <select name="groupe" style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;background:var(--fond);">
                <option value="">Tous groupes</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                <option value="<?= $g ?>" <?= $fGroupe===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
            <select name="etat" style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;background:var(--fond);">
                <option value="">Tous états</option>
                <option value="disponible" <?= $fEtat==='disponible'?'selected':'' ?>>Disponible</option>
                <option value="distribue"  <?= $fEtat==='distribue'?'selected':'' ?>>Distribué</option>
                <option value="expire"     <?= $fEtat==='expire'?'selected':'' ?>>Expiré</option>
                <option value="perime"     <?= $fEtat==='perime'?'selected':'' ?>>Périmé</option>
            </select>
            <button type="submit" class="btn btn-secondaire">Filtrer</button>
            <?php if ($search||$fGroupe||$fEtat): ?><a href="poche.php" class="btn btn-secondaire">✕</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- ════════════ TABLEAU ════════════ -->
<div class="carte-tableau">
    <div class="table-wrapper">
        <table id="tableauPrincipal">
            <thead>
                <tr>
                    <th>#</th><th>Groupe</th><th>Quantité</th>
                    <th>Collecte</th><th>Expiration</th>
                    <th>Jours restants</th><th>Donneur</th>
                    <th>État</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($poches)): ?>
                <tr><td colspan="9">
                    <div class="etat-vide">
                        <div class="etat-vide-icone">🩸</div>
                        <div class="etat-vide-titre">Aucune poche trouvée</div>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($poches as $p):
                    $gc  = str_replace(['+','-'], ['pos','neg'], $p['groupe_sanguin']);
                    $jr  = (new DateTime())->diff(new DateTime($p['date_expiration']))->days;
                    $exp = new DateTime($p['date_expiration']) < new DateTime() ? -1 : $jr;
                    $jrCouleur = $exp < 0 ? 'var(--rouge)' : ($exp <= 3 ? 'var(--rouge)' : ($exp <= 7 ? 'var(--orange)' : 'var(--vert)'));
                ?>
                <tr>
                    <td style="color:var(--texte-fin);font-size:.78rem;">#<?= $p['id'] ?></td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $p['groupe_sanguin'] ?></span></td>
                    <td><strong><?= $p['quantite_ml'] ?> ml</strong></td>
                    <td><?= date('d/m/Y', strtotime($p['date_collecte'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['date_expiration'])) ?></td>
                    <td style="font-weight:700;color:<?= $jrCouleur ?>">
                        <?= $exp < 0 ? '⛔ Expiré' : $exp.' j' ?>
                    </td>
                    <td><?= $p['donneur_nom'] ? htmlspecialchars($p['donneur_nom']) : '<span style="color:var(--texte-fin)">—</span>' ?></td>
                    <td>
                        <span class="badge-statut <?= $p['etat'] ?>">
                            <?= ucfirst($p['etat']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions-tableau">
                            <button class="btn btn-secondaire btn-sm btn-icone-seul"
                                    data-tooltip="Modifier"
                                    onclick="ouvrirModifPoche(<?= htmlspecialchars(json_encode($p)) ?>)">✏️</button>
                            <?php if ($userActuel['role'] === 'admin'): ?>
                            <button class="btn btn-danger btn-sm btn-icone-seul"
                                    data-tooltip="Retirer"
                                    onclick="confirmerSuppression('poche.php?supprimer=<?= $p['id'] ?>','Retirer cette poche du stock ?')">🗑️</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span>Résultat <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> / <?= $total ?></span>
        <div class="pagination-pages">
            <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
            <a href="?q=<?= urlencode($search) ?>&groupe=<?= urlencode($fGroupe) ?>&etat=<?= urlencode($fEtat) ?>&page=<?= $i ?>"
               class="page-btn <?= $i===$page?'actif':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════════════ MODAL POCHE ════════════ -->
<div class="modal-overlay" id="modalPoche" style="display:<?= $erreurForm||$editPoche?'flex':'none' ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-titre" id="titreModalPoche">🩸 Ajouter une poche</div>
            <button class="modal-fermer" onclick="fermerModal('modalPoche')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-corps">
                <input type="hidden" name="id_edit" id="idEditPoche" value="<?= $editPoche?$editPoche['id']:0 ?>">
                <?php if ($erreurForm): ?>
                <div class="alerte danger" style="margin-bottom:14px;"><span>⚠</span><div><?= htmlspecialchars($erreurForm) ?></div></div>
                <?php endif; ?>
                <div class="form-grille col-2">
                    <div class="champ">
                        <label>Groupe sanguin <span class="requis">*</span></label>
                        <select name="groupe_sanguin" id="pGroupe" required>
                            <option value="">— Choisir —</option>
                            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($editPoche['groupe_sanguin']??'')===$g?'selected':'' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Quantité (ml) <span class="requis">*</span></label>
                        <input type="number" name="quantite_ml" id="pQte" min="100" max="1000"
                               value="<?= $editPoche['quantite_ml'] ?? 450 ?>" required>
                    </div>
                    <div class="champ">
                        <label>Date de collecte <span class="requis">*</span></label>
                        <input type="date" name="date_collecte" id="pCollecte"
                               value="<?= $editPoche['date_collecte'] ?? date('Y-m-d') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Date d'expiration <span class="requis">*</span></label>
                        <input type="date" name="date_expiration" id="pExpir"
                               value="<?= $editPoche['date_expiration'] ?? date('Y-m-d', strtotime('+42 days')) ?>" required>
                    </div>
                    <div class="champ">
                        <label>État</label>
                        <select name="etat" id="pEtat">
                            <?php foreach(['disponible','distribue','expire','perime'] as $e): ?>
                            <option value="<?= $e ?>" <?= ($editPoche['etat']??'disponible')===$e?'selected':'' ?>><?= ucfirst($e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ pleine-largeur">
                        <label>Notes</label>
                        <input type="text" name="notes" id="pNotes"
                               value="<?= htmlspecialchars($editPoche['notes']??'') ?>"
                               placeholder="Observations éventuelles">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondaire" onclick="fermerModal('modalPoche')">Annuler</button>
                <button type="submit" class="btn btn-primaire">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal confirm -->
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
function reinitFormPoche() {
    document.getElementById('titreModalPoche').textContent = '🩸 Ajouter une poche';
    document.getElementById('idEditPoche').value = 0;
    document.getElementById('pGroupe').value = '';
    document.getElementById('pQte').value = 450;
    document.getElementById('pCollecte').value = new Date().toISOString().split('T')[0];
    const exp = new Date(); exp.setDate(exp.getDate()+42);
    document.getElementById('pExpir').value = exp.toISOString().split('T')[0];
    document.getElementById('pEtat').value = 'disponible';
    document.getElementById('pNotes').value = '';
}
function ouvrirModifPoche(p) {
    document.getElementById('titreModalPoche').textContent = '✏️ Modifier la poche';
    document.getElementById('idEditPoche').value   = p.id;
    document.getElementById('pGroupe').value       = p.groupe_sanguin;
    document.getElementById('pQte').value          = p.quantite_ml;
    document.getElementById('pCollecte').value     = p.date_collecte;
    document.getElementById('pExpir').value        = p.date_expiration;
    document.getElementById('pEtat').value         = p.etat;
    document.getElementById('pNotes').value        = p.notes || '';
    ouvrirModal('modalPoche');
}
</script>

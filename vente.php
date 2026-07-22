<?php
// ============================================================
//  GESTION DES DISTRIBUTIONS — pages/vente.php
// ============================================================
$root       = '../';
$pageTitre  = 'Distributions de sang';
$pageModule = 'ventes';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

$erreurForm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole('admin','agent','medecin');

    $poche_id    = (int)($_POST['poche_id']    ?? 0);
    $hopital_id  = (int)($_POST['hopital_id']  ?? 0);
    $client_nom  = trim($_POST['client_nom']   ?? '');
    $quantite    = (int)($_POST['quantite_ml'] ?? 0);
    $date_op     = $_POST['date_operation']    ?? date('Y-m-d');
    $notes       = trim($_POST['notes']        ?? '');

    if (!$poche_id || !$quantite || !$date_op) {
        $erreurForm = 'Veuillez sélectionner une poche et renseigner la quantité.';
    } elseif (!$hopital_id && !$client_nom) {
        $erreurForm = 'Veuillez indiquer l\'hôpital bénéficiaire ou le nom du client.';
    } else {
        // Vérifier la poche
        $poche = $db->prepare("SELECT * FROM poches WHERE id=? AND etat='disponible'");
        $poche->execute([$poche_id]);
        $poche = $poche->fetch();

        if (!$poche) {
            $erreurForm = 'Cette poche n\'est pas disponible.';
        } elseif ($quantite > $poche['quantite_ml']) {
            $erreurForm = "Quantité demandée ($quantite ml) supérieure au stock de cette poche ({$poche['quantite_ml']} ml).";
        } else {
            // Enregistrer la vente
            $db->prepare(
                'INSERT INTO ventes (poche_id,hopital_id,client_nom,quantite_ml,date_operation,utilisateur_id,notes) VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $poche_id,
                $hopital_id ?: null,
                $client_nom ?: null,
                $quantite,
                $date_op,
                $_SESSION['user_id'],
                $notes ?: null
            ]);
            $vid = $db->lastInsertId();

            // Mettre à jour le stock
            if ($quantite >= $poche['quantite_ml']) {
                $db->prepare("UPDATE poches SET etat='distribue' WHERE id=?")->execute([$poche_id]);
            } else {
                $db->prepare("UPDATE poches SET quantite_ml = quantite_ml - ? WHERE id=?")->execute([$quantite,$poche_id]);
            }

            logAction('DISTRIBUTION','ventes',$vid,"Poche #{$poche_id} → {$quantite}ml");
            header('Location: vente.php?msg='.urlencode('Distribution enregistrée avec succès.').'&type=success');
            exit;
        }
    }
}

// Liste
$search  = trim($_GET['q'] ?? '');
$fGroupe = $_GET['groupe'] ?? '';
$page    = max(1,(int)($_GET['page'] ?? 1));
$limit   = 15; $offset = ($page-1)*$limit;

$where  = ['1=1']; $params = [];
if ($search)  { $where[] = "(h.nom LIKE ? OR v.client_nom LIKE ? OR p.groupe_sanguin LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($fGroupe) { $where[] = "p.groupe_sanguin=?"; $params[] = $fGroupe; }
$ws = 'WHERE '.implode(' AND ', $where);

$totalR = $db->prepare("SELECT COUNT(*) FROM ventes v JOIN poches p ON p.id=v.poche_id LEFT JOIN hopitaux h ON h.id=v.hopital_id $ws");
$totalR->execute($params); $total = $totalR->fetchColumn();
$totalPages = ceil($total/$limit);

$stmt = $db->prepare(
    "SELECT v.*, p.groupe_sanguin, p.quantite_ml as stock_poche,
            h.nom as hopital_nom,
            CONCAT(u.prenom,' ',u.nom) as operateur
     FROM ventes v
     JOIN poches p ON p.id = v.poche_id
     LEFT JOIN hopitaux h ON h.id = v.hopital_id
     LEFT JOIN utilisateurs u ON u.id = v.utilisateur_id
     $ws ORDER BY v.date_operation DESC, v.cree_le DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$ventes = $stmt->fetchAll();

// Pour le formulaire
$pochesDispos = $db->query(
    "SELECT id, groupe_sanguin, quantite_ml, date_expiration
     FROM poches WHERE etat='disponible'
     ORDER BY date_expiration ASC"
)->fetchAll();

$hopitauxListe = $db->query("SELECT id, nom FROM hopitaux WHERE actif=1 ORDER BY nom")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">📦 Distributions</div>
        <div class="section-sous-titre"><?= $total ?> distribution(s) enregistrée(s)</div>
    </div>
    <button class="btn btn-primaire" data-ouvrir-modal="modalVente">＋ Nouvelle distribution</button>
</div>

<!-- Filtres -->
<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Hôpital, client, groupe…">
            </div>
            <select name="groupe" style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;background:var(--fond);">
                <option value="">Tous groupes</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $g): ?>
                <option value="<?= $g ?>" <?= $fGroupe===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondaire">Filtrer</button>
            <?php if ($search||$fGroupe): ?><a href="vente.php" class="btn btn-secondaire">✕</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Tableau -->
<div class="carte-tableau">
    <div class="table-wrapper">
        <table id="tableauPrincipal">
            <thead>
                <tr>
                    <th>#</th><th>Date</th><th>Groupe</th>
                    <th>Quantité</th><th>Bénéficiaire</th>
                    <th>Poche #</th><th>Opérateur</th><th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ventes)): ?>
                <tr><td colspan="8">
                    <div class="etat-vide">
                        <div class="etat-vide-icone">📦</div>
                        <div class="etat-vide-titre">Aucune distribution</div>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($ventes as $v):
                    $gc = str_replace(['+','-'], ['pos','neg'], $v['groupe_sanguin']);
                    $beneficiaire = $v['hopital_nom'] ?? $v['client_nom'] ?? '—';
                ?>
                <tr>
                    <td style="color:var(--texte-fin);font-size:.78rem;">#<?= $v['id'] ?></td>
                    <td><strong><?= date('d/m/Y', strtotime($v['date_operation'])) ?></strong>
                        <div style="font-size:.72rem;color:var(--texte-fin)"><?= date('H:i', strtotime($v['cree_le'])) ?></div>
                    </td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $v['groupe_sanguin'] ?></span></td>
                    <td><strong><?= $v['quantite_ml'] ?> ml</strong></td>
                    <td>
                        <?php if ($v['hopital_nom']): ?>
                        <div style="display:flex;align-items:center;gap:4px;">
                            🏥 <?= htmlspecialchars($v['hopital_nom']) ?>
                        </div>
                        <?php else: ?>
                        👤 <?= htmlspecialchars($v['client_nom'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--texte-fin);font-size:.8rem;">Poche #<?= $v['poche_id'] ?></td>
                    <td style="font-size:.8rem;"><?= htmlspecialchars($v['operateur'] ?? '—') ?></td>
                    <td style="font-size:.78rem;color:var(--texte-fin)">
                        <?= htmlspecialchars(mb_strimwidth($v['notes'] ?? '', 0, 30, '…')) ?>
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
            <a href="?q=<?= urlencode($search) ?>&groupe=<?= urlencode($fGroupe) ?>&page=<?= $i ?>"
               class="page-btn <?= $i===$page?'actif':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal distribution -->
<div class="modal-overlay" id="modalVente" style="display:<?= $erreurForm?'flex':'none' ?>">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <div class="modal-titre">📦 Nouvelle distribution</div>
            <button class="modal-fermer" onclick="fermerModal('modalVente')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-corps">
                <?php if ($erreurForm): ?>
                <div class="alerte danger" style="margin-bottom:14px;"><span>⚠</span><div><?= htmlspecialchars($erreurForm) ?></div></div>
                <?php endif; ?>

                <div class="form-grille col-2">
                    <div class="champ pleine-largeur">
                        <label>Poche de sang <span class="requis">*</span></label>
                        <select name="poche_id" id="selectPoche" required onchange="updateStockInfo()">
                            <option value="">— Sélectionner une poche —</option>
                            <?php foreach ($pochesDispos as $p):
                                $gc = str_replace(['+','-'], ['pos','neg'], $p['groupe_sanguin']);
                                $jr = (new DateTime())->diff(new DateTime($p['date_expiration']))->days;
                            ?>
                            <option value="<?= $p['id'] ?>"
                                    data-qte="<?= $p['quantite_ml'] ?>"
                                    data-groupe="<?= $p['groupe_sanguin'] ?>">
                                Poche #<?= $p['id'] ?> — <?= $p['groupe_sanguin'] ?> — <?= $p['quantite_ml'] ?> ml — Exp: <?= date('d/m/Y', strtotime($p['date_expiration'])) ?> (<?= $jr ?> j)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="stockInfo" style="font-size:.78rem;color:var(--texte-fin);margin-top:4px;"></div>
                    </div>

                    <div class="champ">
                        <label>Quantité à distribuer (ml) <span class="requis">*</span></label>
                        <input type="number" name="quantite_ml" id="qteVente"
                               min="1" value="450" required>
                    </div>

                    <div class="champ">
                        <label>Date de l'opération <span class="requis">*</span></label>
                        <input type="date" name="date_operation" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="champ pleine-largeur">
                        <label>Hôpital bénéficiaire</label>
                        <select name="hopital_id" id="selectHopital" onchange="toggleClient()">
                            <option value="">— Hôpital (si applicable) —</option>
                            <?php foreach ($hopitauxListe as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="champ pleine-largeur" id="champClientNom">
                        <label>Ou nom du client / bénéficiaire</label>
                        <input type="text" name="client_nom" placeholder="Si hors hôpital enregistré">
                    </div>

                    <div class="champ pleine-largeur">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Motif, observations…">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondaire" onclick="fermerModal('modalVente')">Annuler</button>
                <button type="submit" class="btn btn-primaire">✅ Enregistrer la distribution</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function updateStockInfo() {
    const sel  = document.getElementById('selectPoche');
    const opt  = sel.options[sel.selectedIndex];
    const info = document.getElementById('stockInfo');
    const qte  = document.getElementById('qteVente');
    if (opt.value) {
        const stock  = parseInt(opt.dataset.qte);
        const groupe = opt.dataset.groupe;
        info.innerHTML = `Stock disponible : <strong>${stock} ml</strong> — Groupe <strong>${groupe}</strong>`;
        qte.max = stock;
        qte.value = Math.min(parseInt(qte.value)||450, stock);
    } else {
        info.innerHTML = '';
        qte.max = '';
    }
}
function toggleClient() {
    const hop   = document.getElementById('selectHopital').value;
    const champ = document.getElementById('champClientNom');
    champ.style.display = hop ? 'none' : '';
}
</script>

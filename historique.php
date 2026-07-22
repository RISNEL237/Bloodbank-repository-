<?php
// ============================================================
//  HISTORIQUE DES OPÉRATIONS — pages/historique.php
// ============================================================
$root       = '../';
$pageTitre  = 'Historique des opérations';
$pageModule = 'historique';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

$search    = trim($_GET['q'] ?? '');
$fAction   = $_GET['action'] ?? '';
$fDateDeb  = $_GET['date_debut'] ?? '';
$fDateFin  = $_GET['date_fin']   ?? '';
$page      = max(1,(int)($_GET['page'] ?? 1));
$limit     = 20; $offset = ($page-1)*$limit;

$where = ['1=1']; $params = [];

if ($search) {
    $where[] = '(h.action LIKE ? OR h.detail LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)';
    $s = "%$search%"; $params = array_merge($params,[$s,$s,$s,$s]);
}
if ($fAction) { $where[] = 'h.action = ?'; $params[] = $fAction; }
if ($fDateDeb) { $where[] = 'DATE(h.cree_le) >= ?'; $params[] = $fDateDeb; }
if ($fDateFin) { $where[] = 'DATE(h.cree_le) <= ?'; $params[] = $fDateFin; }

$ws = 'WHERE ' . implode(' AND ', $where);

$totalR = $db->prepare("SELECT COUNT(*) FROM historique h LEFT JOIN utilisateurs u ON u.id=h.utilisateur_id $ws");
$totalR->execute($params);
$total = $totalR->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt = $db->prepare(
    "SELECT h.*, CONCAT(u.prenom,' ',u.nom) as user_nom, u.role as user_role
     FROM historique h
     LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
     $ws ORDER BY h.cree_le DESC LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$historique = $stmt->fetchAll();

// Liste des types d'actions distincts pour le filtre
$actionsDistinctes = $db->query("SELECT DISTINCT action FROM historique ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Mapping icônes par type d'action
function iconeAction(string $action): string {
    if (str_contains($action, 'AJOUT'))         return '➕';
    if (str_contains($action, 'MODIFICATION'))  return '✏️';
    if (str_contains($action, 'SUPPRESSION'))   return '🗑️';
    if (str_contains($action, 'CONNEXION'))     return '🔑';
    if (str_contains($action, 'DECONNEXION'))   return '🚪';
    if (str_contains($action, 'DISTRIBUTION'))  return '📦';
    return '📋';
}
function couleurAction(string $action): string {
    if (str_contains($action, 'AJOUT'))         return 'var(--vert)';
    if (str_contains($action, 'MODIFICATION'))  return 'var(--bleu)';
    if (str_contains($action, 'SUPPRESSION'))   return 'var(--rouge)';
    if (str_contains($action, 'DISTRIBUTION'))  return 'var(--orange)';
    return 'var(--texte-doux)';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">📋 Historique des opérations</div>
        <div class="section-sous-titre"><?= number_format($total) ?> opération(s) enregistrée(s)</div>
    </div>
</div>

<!-- Filtres -->
<div class="carte-tableau" style="margin-bottom:18px;">
    <div style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <div class="champ-recherche">
                <span class="ic-recherche">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Action, utilisateur, détail…">
            </div>
            <select name="action" style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;background:var(--fond);">
                <option value="">Toutes actions</option>
                <?php foreach ($actionsDistinctes as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $fAction===$a?'selected':'' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($fDateDeb) ?>"
                   style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;">
            <span style="color:var(--texte-fin);font-size:.8rem;">à</span>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($fDateFin) ?>"
                   style="padding:8px 13px;border:1px solid var(--bordure);border-radius:var(--rayon-sm);font-family:inherit;font-size:.82rem;">
            <button type="submit" class="btn btn-secondaire">Filtrer</button>
            <?php if ($search||$fAction||$fDateDeb||$fDateFin): ?>
            <a href="historique.php" class="btn btn-secondaire">✕ Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Timeline historique -->
<div class="carte-tableau">
    <div style="padding:24px;">
        <?php if (empty($historique)): ?>
        <div class="etat-vide">
            <div class="etat-vide-icone">📋</div>
            <div class="etat-vide-titre">Aucune opération trouvée</div>
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($historique as $h): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <?= date('d/m/Y à H:i:s', strtotime($h['cree_le'])) ?>
                </div>
                <div class="timeline-action" style="display:flex;align-items:center;gap:8px;">
                    <span><?= iconeAction($h['action']) ?></span>
                    <span style="color:<?= couleurAction($h['action']) ?>"><?= htmlspecialchars($h['action']) ?></span>
                    <?php if ($h['user_nom']): ?>
                    <span class="badge-role <?= $h['user_role'] ?: 'agent' ?>" style="margin-left:6px;">
                        <?= htmlspecialchars($h['user_nom']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($h['detail']): ?>
                <div class="timeline-detail"><?= htmlspecialchars($h['detail']) ?></div>
                <?php endif; ?>
                <?php if ($h['table_cible']): ?>
                <div style="font-size:.72rem;color:var(--texte-fin);margin-top:2px;">
                    Table : <code style="background:var(--fond);padding:1px 5px;border-radius:3px;"><?= htmlspecialchars($h['table_cible']) ?></code>
                    <?php if ($h['enregistrement_id']): ?> · ID #<?= $h['enregistrement_id'] ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span>Résultat <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> / <?= $total ?></span>
        <div class="pagination-pages">
            <?php
            $qs = http_build_query(['q'=>$search,'action'=>$fAction,'date_debut'=>$fDateDeb,'date_fin'=>$fDateFin]);
            for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
            <a href="?<?= $qs ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'actif':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

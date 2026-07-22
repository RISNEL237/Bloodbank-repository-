<?php
// ============================================================
//  RECHERCHE GLOBALE — pages/recherche.php
// ============================================================
$root       = '../';
$pageTitre  = 'Recherche globale';
$pageModule = 'recherche';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();
$q  = trim($_GET['q'] ?? '');

$resDonneurs = $resPoches = $resHopitaux = $resVentes = [];

if ($q !== '') {
    $s = "%$q%";

    $stmt = $db->prepare(
        "SELECT * FROM donneurs WHERE actif=1 AND
         (nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR groupe_sanguin LIKE ?)
         ORDER BY cree_le DESC LIMIT 10"
    );
    $stmt->execute([$s,$s,$s,$s]);
    $resDonneurs = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT * FROM poches WHERE
         (groupe_sanguin LIKE ? OR etat LIKE ? OR notes LIKE ?)
         ORDER BY date_collecte DESC LIMIT 10"
    );
    $stmt->execute([$s,$s,$s]);
    $resPoches = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT * FROM hopitaux WHERE actif=1 AND
         (nom LIKE ? OR adresse LIKE ? OR telephone LIKE ?)
         ORDER BY nom LIMIT 10"
    );
    $stmt->execute([$s,$s,$s]);
    $resHopitaux = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT v.*, p.groupe_sanguin, h.nom as hopital_nom
         FROM ventes v
         JOIN poches p ON p.id = v.poche_id
         LEFT JOIN hopitaux h ON h.id = v.hopital_id
         WHERE (h.nom LIKE ? OR v.client_nom LIKE ? OR p.groupe_sanguin LIKE ?)
         ORDER BY v.date_operation DESC LIMIT 10"
    );
    $stmt->execute([$s,$s,$s]);
    $resVentes = $stmt->fetchAll();
}

$totalResultats = count($resDonneurs) + count($resPoches) + count($resHopitaux) + count($resVentes);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">🔍 Recherche globale</div>
        <div class="section-sous-titre">Donneurs, poches, hôpitaux, distributions et dates</div>
    </div>
</div>

<!-- Barre de recherche large -->
<div class="carte-tableau" style="margin-bottom:22px;">
    <div style="padding:24px;">
        <form method="GET">
            <div style="position:relative;">
                <span style="position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:20px;">🔍</span>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Rechercher un donneur, groupe sanguin, hôpital, poche, date…"
                       style="width:100%;padding:14px 16px 14px 48px;border:2px solid var(--bordure);
                              border-radius:10px;font-size:1rem;font-family:inherit;transition:var(--transition);"
                       autofocus>
            </div>
        </form>
        <?php if ($q): ?>
        <div style="margin-top:10px;font-size:.82rem;color:var(--texte-fin);">
            <?= $totalResultats ?> résultat(s) pour « <strong><?= htmlspecialchars($q) ?></strong> »
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$q): ?>
<div class="carte-tableau">
    <div class="etat-vide">
        <div class="etat-vide-icone">🔍</div>
        <div class="etat-vide-titre">Commencez votre recherche</div>
        <div class="etat-vide-texte">Tapez un nom, un groupe sanguin, une adresse ou une date.</div>
    </div>
</div>
<?php elseif ($totalResultats === 0): ?>
<div class="carte-tableau">
    <div class="etat-vide">
        <div class="etat-vide-icone">😕</div>
        <div class="etat-vide-titre">Aucun résultat trouvé</div>
        <div class="etat-vide-texte">Essayez avec d'autres termes de recherche.</div>
    </div>
</div>
<?php else: ?>

<!-- Résultats Donneurs -->
<?php if (!empty($resDonneurs)): ?>
<div class="carte-tableau" style="margin-bottom:18px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">👤 Donneurs (<?= count($resDonneurs) ?>)</span>
        <a href="donneur.php?q=<?= urlencode($q) ?>" class="btn btn-secondaire btn-sm">Voir tout dans Donneurs →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Nom</th><th>Groupe</th><th>Téléphone</th><th>Inscrit le</th></tr></thead>
            <tbody>
                <?php foreach ($resDonneurs as $d): $gc = str_replace(['+','-'],['pos','neg'],$d['groupe_sanguin']); ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></strong></td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $d['groupe_sanguin'] ?></span></td>
                    <td><?= htmlspecialchars($d['telephone'] ?: '—') ?></td>
                    <td><?= date('d/m/Y', strtotime($d['date_inscription'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Résultats Poches -->
<?php if (!empty($resPoches)): ?>
<div class="carte-tableau" style="margin-bottom:18px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">🩸 Poches (<?= count($resPoches) ?>)</span>
        <a href="poche.php?q=<?= urlencode($q) ?>" class="btn btn-secondaire btn-sm">Voir tout dans Poches →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Groupe</th><th>Quantité</th><th>Expiration</th><th>État</th></tr></thead>
            <tbody>
                <?php foreach ($resPoches as $p): $gc = str_replace(['+','-'],['pos','neg'],$p['groupe_sanguin']); ?>
                <tr>
                    <td>#<?= $p['id'] ?></td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $p['groupe_sanguin'] ?></span></td>
                    <td><?= $p['quantite_ml'] ?> ml</td>
                    <td><?= date('d/m/Y', strtotime($p['date_expiration'])) ?></td>
                    <td><span class="badge-statut <?= $p['etat'] ?>"><?= ucfirst($p['etat']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Résultats Hôpitaux -->
<?php if (!empty($resHopitaux)): ?>
<div class="carte-tableau" style="margin-bottom:18px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">🏥 Hôpitaux (<?= count($resHopitaux) ?>)</span>
        <a href="hopital.php?q=<?= urlencode($q) ?>" class="btn btn-secondaire btn-sm">Voir tout dans Hôpitaux →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Nom</th><th>Adresse</th><th>Téléphone</th></tr></thead>
            <tbody>
                <?php foreach ($resHopitaux as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($h['adresse'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($h['telephone'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Résultats Ventes -->
<?php if (!empty($resVentes)): ?>
<div class="carte-tableau" style="margin-bottom:18px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">📦 Distributions (<?= count($resVentes) ?>)</span>
        <a href="vente.php?q=<?= urlencode($q) ?>" class="btn btn-secondaire btn-sm">Voir tout dans Distributions →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date</th><th>Groupe</th><th>Quantité</th><th>Bénéficiaire</th></tr></thead>
            <tbody>
                <?php foreach ($resVentes as $v): $gc = str_replace(['+','-'],['pos','neg'],$v['groupe_sanguin']); ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($v['date_operation'])) ?></td>
                    <td><span class="badge-sang <?= $gc ?>"><?= $v['groupe_sanguin'] ?></span></td>
                    <td><?= $v['quantite_ml'] ?> ml</td>
                    <td><?= htmlspecialchars($v['hopital_nom'] ?? $v['client_nom'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

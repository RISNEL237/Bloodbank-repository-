<?php
// ============================================================
//  TABLEAU DE BORD — pages/dashboard.php
// ============================================================
$root       = '../';
$pageTitre  = 'Tableau de bord';
$pageModule = 'dashboard';

require_once __DIR__ . '/../includes/auth_check.php';

$db = getDB();

// ── Statistiques principales ─────────────────────────────────
$stats = [
    'donneurs'  => $db->query("SELECT COUNT(*) FROM donneurs WHERE actif=1")->fetchColumn(),
    'poches'    => $db->query("SELECT COUNT(*) FROM poches WHERE etat='disponible'")->fetchColumn(),
    'hopitaux'  => $db->query("SELECT COUNT(*) FROM hopitaux WHERE actif=1")->fetchColumn(),
    'ventes'    => $db->query("SELECT COUNT(*) FROM ventes")->fetchColumn(),
    'dons_mois' => $db->query("SELECT COUNT(*) FROM dons WHERE MONTH(date_don)=MONTH(CURDATE()) AND YEAR(date_don)=YEAR(CURDATE())")->fetchColumn(),
    'poches_exp'=> $db->query("SELECT COUNT(*) FROM poches WHERE etat='disponible' AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
];

// ── Stock par groupe sanguin ──────────────────────────────────
$stocksRaw = $db->query(
    "SELECT groupe_sanguin, COUNT(*) as nb FROM poches WHERE etat='disponible' GROUP BY groupe_sanguin"
)->fetchAll();
$stocks = array_column($stocksRaw, 'nb', 'groupe_sanguin');
$groupes = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$maxStock = max(array_merge(array_values($stocks), [1]));

// ── Dons par mois (6 derniers mois) ──────────────────────────
$donsMois = $db->query(
    "SELECT DATE_FORMAT(date_don,'%b %Y') as mois, COUNT(*) as nb
     FROM dons
     WHERE date_don >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY YEAR(date_don), MONTH(date_don)
     ORDER BY date_don ASC"
)->fetchAll();

// ── Dernières activités ───────────────────────────────────────
$activites = $db->query(
    "SELECT h.*, CONCAT(u.prenom,' ',u.nom) as user_nom
     FROM historique h
     LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
     ORDER BY h.cree_le DESC LIMIT 8"
)->fetchAll();

// ── Poches proches expiration ─────────────────────────────────
$pochesExp = $db->query(
    "SELECT p.*, DATEDIFF(p.date_expiration, CURDATE()) as jours_restants
     FROM poches p
     WHERE p.etat='disponible' AND p.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
     ORDER BY p.date_expiration ASC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ════════════ STATS CARDS ════════════ -->
<div class="stats-grid">
    <div class="stat-card" data-couleur="rouge">
        <div class="stat-icone">👤</div>
        <div class="stat-corps">
            <div class="stat-valeur" data-val="<?= $stats['donneurs'] ?>"><?= $stats['donneurs'] ?></div>
            <div class="stat-label">Donneurs actifs</div>
            <div class="stat-evolution hausse">↑ Ce mois</div>
        </div>
    </div>
    <div class="stat-card" data-couleur="vert">
        <div class="stat-icone">🩸</div>
        <div class="stat-corps">
            <div class="stat-valeur" data-val="<?= $stats['poches'] ?>"><?= $stats['poches'] ?></div>
            <div class="stat-label">Poches disponibles</div>
            <?php if ($stats['poches_exp'] > 0): ?>
            <div class="stat-evolution baisse">⚠ <?= $stats['poches_exp'] ?> expirent bientôt</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card" data-couleur="bleu">
        <div class="stat-icone">🏥</div>
        <div class="stat-corps">
            <div class="stat-valeur" data-val="<?= $stats['hopitaux'] ?>"><?= $stats['hopitaux'] ?></div>
            <div class="stat-label">Hôpitaux partenaires</div>
        </div>
    </div>
    <div class="stat-card" data-couleur="orange">
        <div class="stat-icone">📦</div>
        <div class="stat-corps">
            <div class="stat-valeur" data-val="<?= $stats['ventes'] ?>"><?= $stats['ventes'] ?></div>
            <div class="stat-label">Distributions totales</div>
            <div class="stat-evolution hausse">📅 <?= $stats['dons_mois'] ?> dons ce mois</div>
        </div>
    </div>
</div>

<!-- ════════════ GRAPHIQUES ════════════ -->
<div class="graphiques-grid">

    <!-- Graphique dons par mois -->
    <div class="carte-graphique">
        <div class="carte-graphique-titre">
            📈 Dons enregistrés — 6 derniers mois
            <span style="font-size:.75rem;color:var(--texte-fin);font-weight:400;">mensuel</span>
        </div>
        <canvas id="graphDons" height="100"></canvas>
    </div>

    <!-- Répartition stock -->
    <div class="carte-graphique">
        <div class="carte-graphique-titre">🩸 Stock par groupe sanguin</div>
        <canvas id="graphStocks" height="160"></canvas>
    </div>
</div>

<!-- ════════════ STOCK PAR GROUPE ════════════ -->
<div class="carte-tableau" style="margin-bottom:24px;">
    <div class="carte-tableau-header">
        <span class="carte-tableau-titre">🩸 Stock disponible par groupe sanguin</span>
        <a href="poche.php" class="btn btn-secondaire btn-sm">Gérer les poches →</a>
    </div>
    <div style="padding:16px 20px;">
        <div class="stock-grid">
            <?php foreach ($groupes as $g):
                $nb  = $stocks[$g] ?? 0;
                $pct = $maxStock > 0 ? min(100, round($nb / $maxStock * 100)) : 0;
                $niv = $nb < SEUIL_STOCK_CRITIQUE ? 'critique' : ($nb < 10 ? 'bas' : 'bon');
                $cls = $nb < SEUIL_STOCK_CRITIQUE ? ' critique' : '';
                $gc  = str_replace(['+','-'], ['pos','neg'], $g);
            ?>
            <div class="stock-carte<?= $cls ?>">
                <div class="stock-groupe">
                    <span class="badge-sang <?= $gc ?>"><?= $g ?></span>
                </div>
                <div class="stock-quantite"><?= $nb ?></div>
                <div class="stock-sous">poches dispo.</div>
                <div class="jauge-conteneur">
                    <div class="jauge-barre <?= $niv ?>" data-pct="<?= $pct ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <?php if ($nb < SEUIL_STOCK_CRITIQUE): ?>
                <div style="font-size:.65rem;color:var(--rouge);font-weight:700;margin-top:5px;">⚠ CRITIQUE</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ════════════ RANGÉE BAS ════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

    <!-- Activité récente -->
    <div class="carte-tableau">
        <div class="carte-tableau-header">
            <span class="carte-tableau-titre">📋 Activité récente</span>
            <a href="historique.php" class="btn btn-secondaire btn-sm">Tout voir</a>
        </div>
        <div style="padding:16px 20px;">
            <?php if (empty($activites)): ?>
            <div class="etat-vide">
                <div class="etat-vide-icone">📋</div>
                <div class="etat-vide-titre">Aucune activité</div>
            </div>
            <?php else: ?>
            <div class="timeline">
                <?php foreach ($activites as $a): ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <?= date('d/m/Y à H:i', strtotime($a['cree_le'])) ?>
                        <?php if ($a['user_nom']): ?>
                        · <strong><?= htmlspecialchars($a['user_nom']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-action"><?= htmlspecialchars($a['action']) ?></div>
                    <?php if ($a['detail']): ?>
                    <div class="timeline-detail"><?= htmlspecialchars($a['detail']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Poches proche expiration -->
    <div class="carte-tableau">
        <div class="carte-tableau-header">
            <span class="carte-tableau-titre">⏳ Poches proches de l'expiration</span>
            <a href="poche.php" class="btn btn-secondaire btn-sm">Gérer</a>
        </div>
        <?php if (empty($pochesExp)): ?>
        <div class="etat-vide">
            <div class="etat-vide-icone">✅</div>
            <div class="etat-vide-titre">Aucune poche à risque</div>
            <div class="etat-vide-texte">Toutes les poches sont dans les délais.</div>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Expiration</th>
                        <th>Jours restants</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pochesExp as $p):
                        $j = (int)$p['jours_restants'];
                        $couleur = $j <= 3 ? 'var(--rouge)' : ($j <= 7 ? 'var(--orange)' : 'var(--vert)');
                        $gc = str_replace(['+','-'], ['pos','neg'], $p['groupe_sanguin']);
                    ?>
                    <tr>
                        <td><span class="badge-sang <?= $gc ?>"><?= $p['groupe_sanguin'] ?></span></td>
                        <td><?= date('d/m/Y', strtotime($p['date_expiration'])) ?></td>
                        <td style="font-weight:700;color:<?= $couleur ?>">
                            <?= $j <= 0 ? '⛔ EXPIRÉ' : $j . ' jour(s)' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Données PHP → JS
const donsMoisLabels = <?= json_encode(array_column($donsMois, 'mois')) ?>;
const donsMoisData   = <?= json_encode(array_column($donsMois, 'nb')) ?>;
const stockLabels    = <?= json_encode(array_keys($stocks)) ?>;
const stockData      = <?= json_encode(array_values($stocks)) ?>;

document.addEventListener('DOMContentLoaded', () => {
    initGraphiqueDons(document.getElementById('graphDons'), donsMoisLabels, donsMoisData);
    initGraphiqueStocks(document.getElementById('graphStocks'), stockLabels, stockData);
});
</script>

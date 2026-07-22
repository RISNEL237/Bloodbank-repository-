<?php
// ============================================================
//  ENREGISTRER UN DON — actions/save_don.php
//  Affiche le formulaire ET traite la soumission
//  Règle métier : refuse un don si le dernier date de < 90 jours
// ============================================================
$root       = '../';
$pageTitre  = 'Enregistrer un don';
$pageModule = 'donneurs';

require_once __DIR__ . '/../includes/auth_check.php';
exigerRole('admin','agent','medecin');

$db = getDB();

$donneur_id = (int)($_GET['donneur_id'] ?? $_POST['donneur_id'] ?? 0);
$erreur     = '';
$succes     = '';

// Charger le donneur
$stmt = $db->prepare('SELECT * FROM donneurs WHERE id = ? AND actif = 1');
$stmt->execute([$donneur_id]);
$donneur = $stmt->fetch();

if (!$donneur) {
    header('Location: ../pages/donneur.php?msg=' . urlencode('Donneur introuvable.') . '&type=danger');
    exit;
}

// Dernier don
$stmt = $db->prepare('SELECT * FROM dons WHERE donneur_id = ? ORDER BY date_don DESC LIMIT 1');
$stmt->execute([$donneur_id]);
$dernierDon = $stmt->fetch();

$joursDepuisDernier = null;
$peutDonner = true;
$joursRestants = 0;

if ($dernierDon) {
    $diff = (new DateTime())->diff(new DateTime($dernierDon['date_don']));
    $joursDepuisDernier = $diff->days;
    if ($joursDepuisDernier < DELAI_MIN_DON) {
        $peutDonner = false;
        $joursRestants = DELAI_MIN_DON - $joursDepuisDernier;
    }
}

// ── Traitement du formulaire ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $date_don = $_POST['date_don'] ?? date('Y-m-d');
    $quantite = (int)($_POST['quantite_ml'] ?? 450);
    $notes    = trim($_POST['notes'] ?? '');

    // ── RÈGLE MÉTIER CRITIQUE : vérification serveur, ne JAMAIS se fier au JS ──
    if ($dernierDon) {
        $diffPourSoumission = (new DateTime($date_don))->diff(new DateTime($dernierDon['date_don']));
        // On compare par rapport à la date du don soumis pour rester cohérent
        $joursEntreDons = (int)$diffPourSoumission->days;
        $dateDonObj     = new DateTime($date_don);
        $dateDernierObj = new DateTime($dernierDon['date_don']);

        if ($dateDonObj < $dateDernierObj) {
            $erreur = 'La date du don ne peut pas être antérieure au dernier don enregistré.';
        } elseif ($joursEntreDons < DELAI_MIN_DON) {
            $erreur = sprintf(
                'Don refusé : le dernier don de ce donneur date de %d jour(s). '
                . 'Un délai minimum de %d jours est requis entre deux dons (il reste %d jour(s) à attendre).',
                $joursEntreDons, DELAI_MIN_DON, DELAI_MIN_DON - $joursEntreDons
            );
        }
    }

    if (!$erreur) {
        if ($quantite < 100 || $quantite > 1000) {
            $erreur = 'La quantité doit être comprise entre 100 et 1000 ml.';
        } elseif (!$date_don) {
            $erreur = 'La date du don est obligatoire.';
        }
    }

    if (!$erreur) {
        // Enregistrer le don
        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO dons (donneur_id, quantite_ml, date_don, notes) VALUES (?,?,?,?)'
            );
            $ins->execute([$donneur_id, $quantite, $date_don, $notes ?: null]);
            $donId = $db->lastInsertId();

            // Créer automatiquement une poche associée
            $dateExpiration = (new DateTime($date_don))->modify('+42 days')->format('Y-m-d');
            $insPoche = $db->prepare(
                'INSERT INTO poches (don_id, groupe_sanguin, quantite_ml, date_collecte, date_expiration, etat) VALUES (?,?,?,?,?,?)'
            );
            $insPoche->execute([
                $donId, $donneur['groupe_sanguin'], $quantite, $date_don, $dateExpiration, 'disponible'
            ]);
            $pocheId = $db->lastInsertId();

            $db->commit();

            logAction('DON_ENREGISTRE', 'dons', $donId,
                "Donneur: {$donneur['prenom']} {$donneur['nom']} — {$quantite}ml — Poche #{$pocheId} créée");

            header('Location: ../pages/donneur.php?historique=' . $donneur_id
                . '&msg=' . urlencode('Don enregistré avec succès. Poche #' . $pocheId . ' créée.')
                . '&type=success');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $erreur = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-header">
    <div>
        <div class="section-titre">🩸 Enregistrer un don</div>
        <div class="section-sous-titre">
            Donneur : <strong><?= htmlspecialchars($donneur['prenom'].' '.$donneur['nom']) ?></strong>
        </div>
    </div>
    <a href="../pages/donneur.php?historique=<?= $donneur_id ?>" class="btn btn-secondaire">← Retour à l'historique</a>
</div>

<div style="max-width:640px;">

    <!-- Carte info donneur -->
    <div class="carte-form" style="margin-bottom:18px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <div class="sidebar-avatar" style="width:50px;height:50px;font-size:1.1rem;background:var(--rouge);">
                <?= strtoupper(mb_substr($donneur['prenom'],0,1).mb_substr($donneur['nom'],0,1)) ?>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:1rem;"><?= htmlspecialchars($donneur['prenom'].' '.$donneur['nom']) ?></div>
                <div style="font-size:.8rem;color:var(--texte-fin);">
                    <?= $donneur['sexe']==='M'?'Homme':'Femme' ?> · <?= htmlspecialchars($donneur['telephone'] ?: 'Pas de téléphone') ?>
                </div>
            </div>
            <span class="badge-sang <?= str_replace(['+','-'],['pos','neg'],$donneur['groupe_sanguin']) ?>" style="font-size:.95rem;padding:6px 14px;">
                <?= $donneur['groupe_sanguin'] ?>
            </span>
        </div>
    </div>

    <!-- Alerte règle des 90 jours -->
    <?php if ($dernierDon): ?>
        <?php if (!$peutDonner): ?>
        <div class="alerte danger">
            <span class="alerte-icone">⛔</span>
            <div>
                <div class="alerte-titre">Don non autorisé pour le moment</div>
                <div class="alerte-texte">
                    Dernier don le <strong><?= date('d/m/Y', strtotime($dernierDon['date_don'])) ?></strong>
                    (il y a <?= $joursDepuisDernier ?> jours).
                    Un délai minimum de <strong><?= DELAI_MIN_DON ?> jours</strong> est requis entre deux dons.
                    <br>⏳ Il reste <strong><?= $joursRestants ?> jour(s)</strong> avant le prochain don possible
                    (à partir du <?= date('d/m/Y', strtotime($dernierDon['date_don'] . ' +' . DELAI_MIN_DON . ' days')) ?>).
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alerte success">
            <span class="alerte-icone">✅</span>
            <div>
                <div class="alerte-titre">Don autorisé</div>
                <div class="alerte-texte">
                    Dernier don le <?= date('d/m/Y', strtotime($dernierDon['date_don'])) ?>
                    (il y a <?= $joursDepuisDernier ?> jours) — délai minimum respecté.
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
    <div class="alerte info">
        <span class="alerte-icone">ℹ️</span>
        <div>
            <div class="alerte-titre">Premier don</div>
            <div class="alerte-texte">Ce donneur n'a encore enregistré aucun don.</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
    <div class="alerte danger">
        <span class="alerte-icone">⚠</span>
        <div>
            <div class="alerte-titre">Erreur</div>
            <div class="alerte-texte"><?= htmlspecialchars($erreur) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="carte-form">
        <div class="form-titre">📝 Détails du don</div>
        <form method="POST" id="formDon" <?= !$peutDonner ? 'onsubmit="return confirmerForcage()"' : '' ?>>
            <input type="hidden" name="donneur_id" value="<?= $donneur_id ?>">
            <div class="form-grille col-2">
                <div class="champ">
                    <label>Date du don <span class="requis">*</span></label>
                    <input type="date" name="date_don" value="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d') ?>" required
                           <?= !$peutDonner ? 'disabled' : '' ?>>
                </div>
                <div class="champ">
                    <label>Quantité (ml) <span class="requis">*</span></label>
                    <input type="number" name="quantite_ml" value="450" min="100" max="1000" required
                           <?= !$peutDonner ? 'disabled' : '' ?>>
                </div>
                <div class="champ pleine-largeur">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Observations médicales éventuelles"
                           <?= !$peutDonner ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="form-actions">
                <a href="../pages/donneur.php?historique=<?= $donneur_id ?>" class="btn btn-secondaire">Annuler</a>
                <button type="submit" class="btn btn-primaire" <?= !$peutDonner ? 'disabled style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                    🩸 Enregistrer le don
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

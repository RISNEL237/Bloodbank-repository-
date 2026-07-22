<?php
// ============================================================
//  EN-TÊTE GLOBAL — sidebar + topbar
//  Variables attendues par la page appelante :
//    $pageTitre    : titre affiché dans le topbar
//    $pageModule   : slug du module actif (donneurs, poches, …)
// ============================================================
$pageTitre  = $pageTitre  ?? 'Tableau de bord';
$pageModule = $pageModule ?? 'dashboard';

// Récupérer les alertes de stock critique
$db = getDB();
$alertesStock = $db->query(
    "SELECT groupe_sanguin, COUNT(*) as nb
     FROM poches
     WHERE etat = 'disponible'
     GROUP BY groupe_sanguin
     HAVING nb < " . SEUIL_STOCK_CRITIQUE
)->fetchAll();

$nbAlertes = count($alertesStock);

// Chemin relatif vers la racine (géré par chaque page)
$root = $root ?? '../';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitre) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $root ?>css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🩸</text></svg>">
</head>
<body>
<div class="layout">

<!-- ══════════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">🩸</div>
        <div class="sidebar-logo-texte">
            <strong><?= APP_NAME ?></strong>
            <span>Banque de Sang</span>
        </div>
    </div>

    <!-- Profil utilisateur -->
    <div class="sidebar-profil">
        <div class="sidebar-avatar">
            <?= strtoupper(mb_substr($userActuel['prenom'], 0, 1) . mb_substr($userActuel['nom'], 0, 1)) ?>
        </div>
        <div class="sidebar-profil-info">
            <strong><?= htmlspecialchars($userActuel['prenom'] . ' ' . $userActuel['nom']) ?></strong>
            <span class="badge-role <?= $userActuel['role'] ?>">
                <?= ucfirst($userActuel['role']) ?>
            </span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Principal</div>

        <a href="<?= $root ?>pages/dashboard.php"
           class="nav-item <?= $pageModule === 'dashboard' ? 'actif' : '' ?>">
            <span class="nav-icon">📊</span> Tableau de bord
        </a>

        <div class="sidebar-section-label">Gestion</div>

        <a href="<?= $root ?>pages/donneur.php"
           class="nav-item <?= $pageModule === 'donneurs' ? 'actif' : '' ?>">
            <span class="nav-icon">👤</span> Donneurs
        </a>

        <a href="<?= $root ?>pages/poche.php"
           class="nav-item <?= $pageModule === 'poches' ? 'actif' : '' ?>">
            <span class="nav-icon">🩸</span> Poches de sang
            <?php if ($nbAlertes > 0): ?>
                <span class="nav-badge alerte"><?= $nbAlertes ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= $root ?>pages/hopital.php"
           class="nav-item <?= $pageModule === 'hopitaux' ? 'actif' : '' ?>">
            <span class="nav-icon">🏥</span> Hôpitaux
        </a>

        <a href="<?= $root ?>pages/vente.php"
           class="nav-item <?= $pageModule === 'ventes' ? 'actif' : '' ?>">
            <span class="nav-icon">📦</span> Distributions
        </a>

        <div class="sidebar-section-label">Analyse</div>

        <a href="<?= $root ?>pages/recherche.php"
           class="nav-item <?= $pageModule === 'recherche' ? 'actif' : '' ?>">
            <span class="nav-icon">🔍</span> Recherche
        </a>

        <a href="<?= $root ?>pages/historique.php"
           class="nav-item <?= $pageModule === 'historique' ? 'actif' : '' ?>">
            <span class="nav-icon">📋</span> Historique
        </a>

        <?php if ($userActuel['role'] === 'admin'): ?>
        <div class="sidebar-section-label">Administration</div>
        <a href="<?= $root ?>pages/utilisateurs.php"
           class="nav-item <?= $pageModule === 'utilisateurs' ? 'actif' : '' ?>">
            <span class="nav-icon">⚙️</span> Utilisateurs
        </a>
        <?php endif; ?>
    </nav>

    <!-- Déconnexion -->
    <div class="sidebar-footer">
        <a href="<?= $root ?>auth/logout.php" class="btn-deconnexion">
            <span>🚪</span> Déconnexion
        </a>
    </div>
</aside>

<!-- ══════════════════════════════════════════════════════════
     CONTENU PRINCIPAL
══════════════════════════════════════════════════════════ -->
<div class="contenu">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-gauche">
            <div class="topbar-titre"><?= htmlspecialchars($pageTitre) ?></div>
            <div class="breadcrumb">
                <span><?= APP_NAME ?></span>
                <span class="sep">›</span>
                <span><?= htmlspecialchars($pageTitre) ?></span>
            </div>
        </div>
        <div class="topbar-droite">
            <?php if ($nbAlertes > 0): ?>
            <button class="btn-topbar" data-tooltip="<?= $nbAlertes ?> alerte(s) de stock" onclick="document.getElementById('alertes-modal')?.classList.toggle('hidden')">
                🔔 <span class="notif-point"></span>
            </button>
            <?php endif; ?>
            <span style="font-size:.8rem;color:var(--texte-fin)">
                <?= date('d/m/Y') ?>
            </span>
        </div>
    </header>

    <!-- Alertes de stock en bandeau si présentes -->
    <?php if ($nbAlertes > 0): ?>
    <div style="padding:14px 28px 0">
        <div class="banniere-alerte">
            <div class="alerte-dot"></div>
            <strong style="font-size:.83rem;color:#742A2A">⚠ Stock critique :</strong>
            <?php foreach ($alertesStock as $a): ?>
                <span class="puce-alerte"><?= $a['groupe_sanguin'] ?> : <?= $a['nb'] ?> poche(s)</span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Zone page -->
    <main class="page">

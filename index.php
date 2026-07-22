<?php
// ============================================================
//  PAGE D'ACCUEIL PUBLIQUE — index.php
// ============================================================
require_once __DIR__ . '/config/config.php';

// Statistiques publiques
$db = getDB();
$nbDonneurs  = $db->query("SELECT COUNT(*) FROM donneurs WHERE actif=1")->fetchColumn();
$nbPoches    = $db->query("SELECT COUNT(*) FROM poches WHERE etat='disponible'")->fetchColumn();
$nbHopitaux  = $db->query("SELECT COUNT(*) FROM hopitaux WHERE actif=1")->fetchColumn();
$nbVentes    = $db->query("SELECT COUNT(*) FROM ventes")->fetchColumn();

// Stock par groupe sanguin
$stocks = $db->query(
    "SELECT groupe_sanguin, COUNT(*) as nb
     FROM poches WHERE etat='disponible'
     GROUP BY groupe_sanguin ORDER BY groupe_sanguin"
)->fetchAll();
$stockMap = array_column($stocks, 'nb', 'groupe_sanguin');

// Rediriger si déjà connecté
if (!empty($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SangGestion — Système de Gestion de Banque de Sang</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🩸</text></svg>">
</head>
<body>

<!-- ════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════ -->
<div class="accueil-hero">
    <nav class="accueil-nav">
        <div class="accueil-logo">
            <div class="logo-ic">🩸</div>
            <div>
                <strong>SangGestion</strong>
                <span>Banque de Sang</span>
            </div>
        </div>
        <div class="accueil-nav-liens">
            <a href="auth/auth.php" class="btn-nav btn-nav-fantome">Connexion</a>
            <a href="auth/auth.php?mode=inscription" class="btn-nav btn-nav-plein">Créer un compte</a>
        </div>
    </nav>

    <div class="hero-corps">
        <div class="hero-texte">
            <div class="hero-eyebrow">🏥 Système de gestion médical</div>
            <h1 class="hero-h1">
                Gérez votre banque de sang
                <em>avec précision et efficacité</em>
            </h1>
            <p class="hero-desc">
                SangGestion est une solution complète pour la gestion des donneurs,
                des poches de sang, des hôpitaux partenaires et des distributions —
                conçue pour les professionnels de santé.
            </p>
            <div class="hero-actions">
                <a href="auth/auth.php" class="btn-hero-primaire">🔑 Accéder au système</a>
                <a href="#fonctionnalites" class="btn-hero-secondaire">En savoir plus ↓</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= number_format($nbDonneurs) ?></div>
                    <div class="hero-stat-lab">Donneurs</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= number_format($nbPoches) ?></div>
                    <div class="hero-stat-lab">Poches disponibles</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= number_format($nbHopitaux) ?></div>
                    <div class="hero-stat-lab">Hôpitaux</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= number_format($nbVentes) ?></div>
                    <div class="hero-stat-lab">Distributions</div>
                </div>
            </div>
        </div>

        <!-- Carte stock en temps réel -->
        <div class="hero-carte-flottante">
            <div class="hero-carte-titre">📊 Stock disponible en temps réel</div>
            <div class="hero-groupes">
                <?php
                $groupes = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
                foreach ($groupes as $g):
                    $nb = $stockMap[$g] ?? 0;
                    $classe = $nb < SEUIL_STOCK_CRITIQUE ? 'style="border:1px solid rgba(255,100,100,.4)"' : '';
                ?>
                <div class="hero-groupe-item" <?= $classe ?>>
                    <span class="hero-groupe-nom"><?= $g ?></span>
                    <span class="hero-groupe-nb"><?= $nb ?> 🩸</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($nbPoches < 20): ?>
            <div style="margin-top:12px;text-align:center;font-size:.72rem;color:rgba(255,120,120,.9);font-weight:600;">
                ⚠ Stock global faible — approvisionnement nécessaire
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     FONCTIONNALITÉS
════════════════════════════════════════════════════════ -->
<section id="fonctionnalites" style="padding:70px 48px;background:#fff;">
    <div style="max-width:1100px;margin:0 auto;">
        <div style="text-align:center;margin-bottom:44px;">
            <div style="display:inline-block;background:var(--rouge-clair);color:var(--rouge);
                        padding:4px 14px;border-radius:20px;font-size:.72rem;font-weight:700;
                        text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
                Fonctionnalités
            </div>
            <h2 style="font-size:2rem;font-weight:800;color:var(--texte);margin-bottom:10px;">
                Tout ce dont vous avez besoin
            </h2>
            <p style="color:var(--texte-fin);max-width:500px;margin:0 auto;">
                Une plateforme complète pour gérer tous les aspects d'une banque de sang moderne.
            </p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px;">
            <?php
            $features = [
                ['🩸', 'Gestion des donneurs',    'Inscription, suivi des dons, respect automatique du délai de 90 jours entre deux dons.', '#C0152A'],
                ['📦', 'Stock en temps réel',      'Suivi précis de chaque poche de sang avec alertes automatiques de stock critique.', '#2B6CB0'],
                ['🏥', 'Hôpitaux partenaires',     'Gérez vos hôpitaux bénéficiaires et suivez toutes les distributions effectuées.', '#276749'],
                ['📊', 'Tableaux de bord',         'Graphiques et statistiques pour une vision globale et instantanée de votre banque.', '#805AD5'],
                ['📋', 'Historique complet',       'Traçabilité totale de chaque opération avec date, heure et utilisateur responsable.', '#C05621'],
                ['🔒', 'Gestion des accès',        'Trois niveaux de rôles (Admin, Agent, Médecin) avec authentification sécurisée.', '#2D3748'],
            ];
            foreach ($features as [$ic, $titre, $desc, $col]): ?>
            <div style="background:var(--fond);border-radius:12px;padding:24px;border:1px solid var(--bordure);
                        transition:all .2s ease;" onmouseover="this.style.boxShadow='0 8px 24px rgba(0,0,0,.08)';this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.boxShadow='';this.style.transform=''">
                <div style="width:46px;height:46px;background:<?= $col ?>20;border-radius:10px;
                            display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;">
                    <?= $ic ?>
                </div>
                <h3 style="font-size:.95rem;font-weight:700;color:var(--texte);margin-bottom:8px;"><?= $titre ?></h3>
                <p style="font-size:.82rem;color:var(--texte-fin);line-height:1.6;"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════ -->
<footer style="background:var(--sidebar-bg);color:var(--sidebar-texte);padding:28px 48px;
               display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:22px;">🩸</span>
        <span style="font-weight:700;color:#fff;">SangGestion</span>
        <span style="font-size:.75rem;opacity:.5;">v1.0.0</span>
    </div>
    <div style="font-size:.78rem;opacity:.6;">
        Projet académique — Gestion de Banque de Sang
    </div>
    <a href="auth/auth.php" style="padding:8px 18px;background:var(--rouge);color:#fff;
       border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;">
        Accéder →
    </a>
</footer>

<script src="js/script.js"></script>
</body>
</html>

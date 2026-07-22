<?php
// ============================================================
//  VÉRIFICATION D'AUTHENTIFICATION
//  À inclure en haut de chaque page protégée
// ============================================================
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/auth.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Recharger l'utilisateur depuis la DB à chaque requête
function getUtilisateurActuel(): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, nom, prenom, email, role FROM utilisateurs WHERE id = ? AND actif = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u) {
        session_destroy();
        header('Location: ../auth/auth.php');
        exit;
    }
    return $u;
}

// Vérifier un rôle minimum
function exigerRole(string ...$roles): void {
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        header('HTTP/1.1 403 Forbidden');
        die('<p style="font-family:sans-serif;color:#c0152a;padding:40px">
             ⛔ Accès refusé. Vous n\'avez pas les droits nécessaires.</p>');
    }
}

// Enregistrer une action dans l'historique
function logAction(string $action, string $table = '', int $id = 0, string $detail = ''): void {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO historique (utilisateur_id, action, table_cible, enregistrement_id, detail, ip)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $table ?: null,
        $id    ?: null,
        $detail ?: null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

$userActuel = getUtilisateurActuel();

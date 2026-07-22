<?php
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['user_id'])) {
    $db = getDB();
    $db->prepare('INSERT INTO historique (utilisateur_id, action, detail, ip) VALUES (?,?,?,?)')
       ->execute([$_SESSION['user_id'], 'DECONNEXION', 'Déconnexion', $_SERVER['REMOTE_ADDR']]);
}

session_unset();
session_destroy();
header('Location: ../auth/auth.php?msg=' . urlencode('Vous avez été déconnecté.') . '&type=info');
exit;

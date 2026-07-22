<?php
// ============================================================
//  SUPPRESSION GÉNÉRIQUE — actions/delete.php
//  Usage : delete.php?table=donneurs&id=5&retour=donneur.php
//  Effectue une suppression LOGIQUE (champ actif=0) quand
//  la table le permet, sinon un changement d'état sécurisé.
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
exigerRole('admin', 'agent');

$tablesAutorisees = [
    'donneurs' => ['champ' => 'actif', 'val' => 0, 'libelle' => 'Donneur'],
    'hopitaux' => ['champ' => 'actif', 'val' => 0, 'libelle' => 'Hôpital'],
    'poches'   => ['champ' => 'etat',  'val' => 'perime', 'libelle' => 'Poche'],
];

$table  = $_GET['table']  ?? '';
$id     = (int)($_GET['id'] ?? 0);
$retour = $_GET['retour'] ?? 'dashboard.php';

// Sécurité : retour doit être un fichier local sans chemin externe
$retour = basename($retour);

if (!isset($tablesAutorisees[$table]) || $id <= 0) {
    header('Location: ' . $retour . '?msg=' . urlencode('Requête de suppression invalide.') . '&type=danger');
    exit;
}

$conf  = $tablesAutorisees[$table];
$db    = getDB();

try {
    $stmt = $db->prepare("UPDATE {$table} SET {$conf['champ']} = ? WHERE id = ?");
    $stmt->execute([$conf['val'], $id]);

    logAction('SUPPRESSION_' . strtoupper($table), $table, $id);

    header('Location: ' . $retour . '?msg=' . urlencode($conf['libelle'] . ' supprimé avec succès.') . '&type=success');
} catch (Exception $e) {
    header('Location: ' . $retour . '?msg=' . urlencode('Erreur lors de la suppression.') . '&type=danger');
}
exit;

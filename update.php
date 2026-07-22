<?php
// ============================================================
//  MISE À JOUR RAPIDE (AJAX) — actions/update.php
//  Usage : PATCH-style endpoint pour mises à jour ponctuelles
//  (ex: changer l'état d'une poche depuis un select inline)
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['succes' => false, 'erreur' => 'Méthode non autorisée.']);
    exit;
}

$champsAutorises = [
    'poches' => ['etat'],
    'donneurs' => ['telephone', 'adresse'],
];

$table = $_POST['table'] ?? '';
$id    = (int)($_POST['id'] ?? 0);
$champ = $_POST['champ'] ?? '';
$val   = $_POST['valeur'] ?? '';

if (!isset($champsAutorises[$table]) || !in_array($champ, $champsAutorises[$table], true) || $id <= 0) {
    echo json_encode(['succes' => false, 'erreur' => 'Paramètres invalides.']);
    exit;
}

try {
    exigerRole('admin', 'agent');
    $db = getDB();
    $stmt = $db->prepare("UPDATE {$table} SET {$champ} = ? WHERE id = ?");
    $stmt->execute([$val, $id]);

    logAction('MISE_A_JOUR_' . strtoupper($table), $table, $id, "$champ = $val");

    echo json_encode(['succes' => true]);
} catch (Exception $e) {
    echo json_encode(['succes' => false, 'erreur' => 'Erreur serveur.']);
}

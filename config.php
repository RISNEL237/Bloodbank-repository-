<?php
// ============================================================
//  CONFIGURATION BASE DE DONNÉES
// ============================================================
define('DB_HOST',     'localhost');
define('DB_NAME',     'gestion_sang');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

define('APP_NAME',    'SangGestion');
define('APP_VERSION', '1.0.0');

// Seuil d'alerte stock faible (nombre de poches)
define('SEUIL_STOCK_CRITIQUE', 5);

// Délai minimum entre deux dons (jours)
define('DELAI_MIN_DON', 90);

// ── Connexion PDO ────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['erreur' => 'Connexion DB échouée : ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Démarrage session sécurisé ───────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path'     => '/',
        'secure'   => false, // true en production HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

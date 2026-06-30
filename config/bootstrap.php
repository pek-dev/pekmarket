<?php
/**
 * Fichier de bootstrap - Charge toute la configuration
 * À inclure en PREMIER dans chaque page PHP
 */

// Éviter le double chargement
if (defined('BOOTSTRAP_LOADED')) return;
define('BOOTSTRAP_LOADED', true);

// Charger dans l'ordre
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Charger les includes si existent
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
}
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
}
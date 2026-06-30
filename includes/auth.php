<?php
/**
 * Fichier d'authentification
 * 
 * ️ Toutes les fonctions d'auth sont déjà dans config/functions.php :
 * - isLoggedIn()
 * - requireLogin()
 * - redirectByRole()
 * - isSeller()
 * - isAdmin()
 * - isCustomer()
 * 
 * Ce fichier est conservé pour compatibilité mais n'ajoute rien.
 */

// Fonction spécifique : vérifier les permissions par rôle
if (!function_exists('checkPermission')) {
    function checkPermission($requiredRole) {
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        
        $roleHierarchy = ['customer' => 1, 'seller' => 2, 'admin' => 3];
        $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            header('Location: ' . BASE_URL);
            exit;
        }
    }
}
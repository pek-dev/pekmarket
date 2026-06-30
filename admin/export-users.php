<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/includes/header.php';


// Sécurité : seul l'admin peut exporter
requireLogin();
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit;
}

// Récupérer les filtres (mêmes que users.php)
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// Construction de la requête
$where = ["1=1"];
$params = [];

if ($roleFilter !== 'all') {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter !== 'all') {
    $where[] = "u.is_active = ?";
    $params[] = $statusFilter === 'active' ? 1 : 0;
}
if ($searchQuery) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereClause = implode(' AND ', $where);

// Récupérer les utilisateurs avec statistiques
$usersStmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as products_count,
           COUNT(DISTINCT o.id) as orders_count,
           COALESCE(SUM(o.total), 0) as total_spent,
           (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count,
           (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as reviews_count,
           (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id AND status = 'active') as active_subscriptions
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();

// Statistiques globales pour le rapport
$globalStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role='customer' THEN 1 ELSE 0 END) as customers,
        SUM(CASE WHEN role='seller' THEN 1 ELSE 0 END) as sellers,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN is_verified=1 THEN 1 ELSE 0 END) as verified
    FROM users
");
$globalStats->execute();
$stats = $globalStats->fetch();

// Nom du fichier avec date
$filename = 'pekdev_users_' . date('Y-m-d_H-i-s') . '.xlsx';

// En-têtes HTTP pour forcer le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// ============================================
// GÉNÉRATION DU FICHIER XLSX (format OpenXML)
// ============================================

// Fonction helper pour échapper le XML
function xmlEscape($string) {
    return htmlspecialchars((string)$string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Fonction helper pour formater la date Excel
function excelDate($dateString) {
    if (empty($dateString)) return '';
    $timestamp = strtotime($dateString);
    // Excel date serial (days since 1900-01-01)
    return ($timestamp / 86400) + 25569;
}

// Fonction helper pour formater le prix
function formatPriceExcel($price) {
    return number_format((float)$price, 0, ',', ' ') . ' FBu';
}

// Styles XML
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="5">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="12"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
        <font><sz val="10"/><name val="Calibri"/><color rgb="FF666666"/></font>
        <font><b/><sz val="14"/><name val="Calibri"/><color rgb="FF1E40AF"/></font>
    </fonts>
    <fills count="5">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF1E40AF"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF0F9FF"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/></border>
        <border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="6">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
        <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><numberFormat numFmtId="4" formatCode="#,##0"/></xf>
        <xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>';

// Contenu de la feuille
$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" 
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetPr><outlinePr summaryBelow="0"/></sheetPr>
    <dimension ref="A1:R' . (count($users) + 10) . '"/>
    <sheetViews><sheetView tabSelected="1" workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>
    <sheetFormatPr defaultRowHeight="15" defaultColWidth="12"/>
    <cols>
        <col min="1" max="1" width="6"/>
        <col min="2" max="3" width="18"/>
        <col min="4" max="4" width="28"/>
        <col min="5" max="5" width="18"/>
        <col min="6" max="6" width="14"/>
        <col min="7" max="7" width="14"/>
        <col min="8" max="9" width="16"/>
        <col min="10" max="10" width="12"/>
        <col min="11" max="11" width="12"/>
        <col min="12" max="12" width="12"/>
        <col min="13" max="13" width="12"/>
        <col min="14" max="14" width="12"/>
        <col min="15" max="15" width="12"/>
        <col min="16" max="16" width="18"/>
        <col min="17" max="17" width="20"/>
        <col min="18" max="18" width="16"/>
    </cols>
    <sheetData>';

// Ligne 1 : Titre du rapport
$sheet .= '<row r="1" ht="30">
    <c r="A1" s="1"><t>PekDev Market - Rapport Utilisateurs</t></c>
</row>';

// Ligne 2 : Date d'export
$sheet .= '<row r="2" ht="20">
    <c r="A2" s="3"><t>Généré le : ' . date('d/m/Y à H:i:s') . ' par ' . xmlEscape($_SESSION['user_first_name'] ?? 'Admin') . '</t></c>
</row>';

// Ligne 3 : Filtres appliqués
$filtersText = 'Filtres : Rôle=' . ($roleFilter === 'all' ? 'Tous' : ucfirst($roleFilter)) . 
               ', Statut=' . ($statusFilter === 'all' ? 'Tous' : ucfirst($statusFilter));
if ($searchQuery) $filtersText .= ', Recherche="' . xmlEscape($searchQuery) . '"';
$sheet .= '<row r="3" ht="20">
    <c r="A3" s="3"><t>' . $filtersText . ' | Total : ' . count($users) . ' utilisateur(s)</t></c>
</row>';

// Ligne 5 : Statistiques globales
$sheet .= '<row r="5" ht="25">
    <c r="A5" s="5"><t>STATISTIQUES GLOBALES</t></c>
</row>
<row r="6" ht="20">
    <c r="A6" s="2"><t>Total utilisateurs</t></c><c r="B6" s="2"><v>' . $stats['total'] . '</v></c>
    <c r="D6" s="2"><t>Clients</t></c><c r="E6" s="2"><v>' . $stats['customers'] . '</v></c>
    <c r="G6" s="2"><t>Vendeurs</t></c><c r="H6" s="2"><v>' . $stats['sellers'] . '</v></c>
    <c r="J6" s="2"><t>Administrateurs</t></c><c r="K6" s="2"><v>' . $stats['admins'] . '</v></c>
</row>
<row r="7" ht="20">
    <c r="A7" s="2"><t>Utilisateurs actifs</t></c><c r="B7" s="2"><v>' . $stats['active'] . '</v></c>
    <c r="D7" s="2"><t>Utilisateurs inactifs</t></c><c r="E7" s="2"><v>' . $stats['inactive'] . '</v></c>
    <c r="G7" s="2"><t>Utilisateurs vérifiés</t></c><c r="H7" s="2"><v>' . $stats['verified'] . '</v></c>
</row>';

// Ligne 9 : En-têtes de la table
$headers = [
    'ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle', 
    'Province', 'Ville', 'Vérifié', 'Actif', 
    'Produits', 'Commandes', 'Dépensé (FBu)', 'Favoris', 'Avis', 'Abonnement actif',
    'Date d\'inscription', 'Dernière connexion'
];

$sheet .= '<row r="9" ht="22">';
$col = 'A';
foreach ($headers as $header) {
    $sheet .= '<c r="' . $col . '9" s="1"><t>' . xmlEscape($header) . '</t></c>';
    $col++;
}
$sheet .= '</row>';

// Données des utilisateurs
$row = 10;
foreach ($users as $user) {
    $sheet .= '<row r="' . $row . '" ht="18">';
    
    // ID
    $sheet .= '<c r="A' . $row . '" s="2"><v>' . $user['id'] . '</v></c>';
    
    // Prénom
    $sheet .= '<c r="B' . $row . '" s="2"><t>' . xmlEscape($user['first_name']) . '</t></c>';
    
    // Nom
    $sheet .= '<c r="C' . $row . '" s="2"><t>' . xmlEscape($user['last_name']) . '</t></c>';
    
    // Email
    $sheet .= '<c r="D' . $row . '" s="2"><t>' . xmlEscape($user['email']) . '</t></c>';
    
    // Téléphone
    $sheet .= '<c r="E' . $row . '" s="2"><t>' . xmlEscape($user['phone'] ?? '') . '</t></c>';
    
    // Rôle
    $sheet .= '<c r="F' . $row . '" s="2"><t>' . xmlEscape(ucfirst($user['role'])) . '</t></c>';
    
    // Province
    $sheet .= '<c r="G' . $row . '" s="2"><t>' . xmlEscape($user['province'] ?? '') . '</t></c>';
    
    // Ville
    $sheet .= '<c r="H' . $row . '" s="2"><t>' . xmlEscape($user['city'] ?? '') . '</t></c>';
    
    // Vérifié (Oui/Non)
    $sheet .= '<c r="I' . $row . '" s="2"><t>' . ($user['is_verified'] ? 'Oui' : 'Non') . '</t></c>';
    
    // Actif (Oui/Non)
    $sheet .= '<c r="J' . $row . '" s="2"><t>' . ($user['is_active'] ? 'Oui' : 'Non') . '</t></c>';
    
    // Produits
    $sheet .= '<c r="K' . $row . '" s="4"><v>' . $user['products_count'] . '</v></c>';
    
    // Commandes
    $sheet .= '<c r="L' . $row . '" s="4"><v>' . $user['orders_count'] . '</v></c>';
    
    // Dépensé
    $sheet .= '<c r="M' . $row . '" s="4"><v>' . $user['total_spent'] . '</v></c>';
    
    // Favoris
    $sheet .= '<c r="N' . $row . '" s="4"><v>' . $user['favorites_count'] . '</v></c>';
    
    // Avis
    $sheet .= '<c r="O' . $row . '" s="4"><v>' . $user['reviews_count'] . '</v></c>';
    
    // Abonnement actif
    $sheet .= '<c r="P' . $row . '" s="2"><t>' . ($user['active_subscriptions'] > 0 ? 'Oui (' . $user['active_subscriptions'] . ')' : 'Non') . '</t></c>';
    
    // Date d'inscription
    $sheet .= '<c r="Q' . $row . '" s="2"><t>' . date('d/m/Y H:i', strtotime($user['created_at'])) . '</t></c>';
    
    // Dernière connexion
    $lastLogin = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais';
    $sheet .= '<c r="R' . $row . '" s="2"><t>' . $lastLogin . '</t></c>';
    
    $sheet .= '</row>';
    $row++;
}

$sheet .= '</sheetData>';

// Auto-filtre sur la ligne d'en-têtes
$sheet .= '<autoFilter ref="A9:R9"/>';

// Pied de page
$sheet .= '<row r="' . ($row + 1) . '" ht="20">
    <c r="A' . ($row + 1) . '" s="3"><t>--- Fin du rapport - PekDev Market ' . date('Y') . ' ---</t></c>
</row>';

$sheet .= '</worksheet>';

// ============================================
// ASSEMBLAGE DU FICHIER XLSX (ZIP)
// ============================================

// Créer les fichiers du package OpenXML
$files = [];
$files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

$files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

$files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" 
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Utilisateurs" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';

$files['xl/worksheets/sheet1.xml'] = $sheet;
$files['xl/styles.xml'] = $styles;

// Créer le ZIP (format XLSX)
$zip = new ZipArchive();
$tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');

if ($zip->open($tempFile, ZipArchive::CREATE) !== TRUE) {
    die('Erreur lors de la création du fichier Excel.');
}

foreach ($files as $name => $content) {
    $zip->addFromString($name, $content);
}

$zip->close();

// Envoyer le fichier au navigateur
readfile($tempFile);
unlink($tempFile);
exit;
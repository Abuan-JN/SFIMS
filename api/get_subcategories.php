<?php
require_once '../includes/db.php';

if (isset($_GET['main_id'])) {
    $stmt = $pdo->prepare("SELECT sub_id, name, code FROM ref_subcategories WHERE main_id = ? ORDER BY name");
    $stmt->execute([$_GET['main_id']]);
    echo json_encode($stmt->fetchAll());
}
?>

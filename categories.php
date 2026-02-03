<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    $action = $_POST['action'];
    
    try {
        if ($type == 'main') {
            if ($action == 'add') {
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $stmt = $pdo->prepare("INSERT INTO ref_main_categories (name, code) VALUES (?, ?)");
                $stmt->execute([$name, $code]);
                $message = "Main Category added.";
            } elseif ($action == 'delete') {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM ref_main_categories WHERE main_id = ?");
                $stmt->execute([$id]);
                $message = "Main Category deleted.";
            }
        } elseif ($type == 'sub') {
             if ($action == 'add') {
                $main_id = $_POST['main_id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $stmt = $pdo->prepare("INSERT INTO ref_subcategories (main_id, name, code) VALUES (?, ?, ?)");
                $stmt->execute([$main_id, $name, $code]);
                $message = "Subcategory added.";
            } elseif ($action == 'delete') {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM ref_subcategories WHERE sub_id = ?");
                $stmt->execute([$id]);
                $message = "Subcategory deleted.";
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage() . " (Likely duplicate code or dependency)";
    }
}

$main_categories = $pdo->query("SELECT * FROM ref_main_categories ORDER BY code")->fetchAll();
$sub_categories = $pdo->query("
    SELECT s.*, m.name as main_name 
    FROM ref_subcategories s 
    JOIN ref_main_categories m ON s.main_id = m.main_id 
    ORDER BY m.code, s.code
")->fetchAll();

include 'includes/header.php';
?>

<h2>Category Configuration</h2>

<?php if($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Main Categories -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Main Categories</div>
            <div class="card-body">
                <form method="POST" class="row g-2 mb-3">
                    <input type="hidden" name="type" value="main">
                    <input type="hidden" name="action" value="add">
                    <div class="col-6">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Name" required>
                    </div>
                    <div class="col-3">
                        <input type="number" name="code" class="form-control form-control-sm" placeholder="Code" required>
                    </div>
                    <div class="col-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
                    </div>
                </form>
                
                <table class="table table-sm table-striped">
                    <thead><tr><th>Code</th><th>Name</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($main_categories as $mc): ?>
                        <tr>
                            <td><?= $mc['code'] ?></td>
                            <td><?= htmlspecialchars($mc['name']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete? This will fail if subcategories exist.');">
                                    <input type="hidden" name="type" value="main">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $mc['main_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger py-0">Del</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Subcategories -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Subcategories</div>
            <div class="card-body">
                <form method="POST" class="row g-2 mb-3">
                    <input type="hidden" name="type" value="sub">
                    <input type="hidden" name="action" value="add">
                    <div class="col-4">
                        <select name="main_id" class="form-select form-select-sm" required>
                            <option value="">Main Cat...</option>
                            <?php foreach ($main_categories as $mc): ?>
                                <option value="<?= $mc['main_id'] ?>"><?= htmlspecialchars($mc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Name" required>
                    </div>
                    <div class="col-2">
                        <input type="number" name="code" class="form-control form-control-sm" placeholder="Code" required>
                    </div>
                    <div class="col-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
                    </div>
                </form>
                
                <table class="table table-sm table-striped">
                    <thead><tr><th>Main</th><th>Code</th><th>Name</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($sub_categories as $sc): ?>
                        <tr>
                            <td><?= htmlspecialchars($sc['main_name']) ?></td>
                            <td><?= $sc['code'] ?></td>
                            <td><?= htmlspecialchars($sc['name']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="type" value="sub">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $sc['sub_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger py-0">Del</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

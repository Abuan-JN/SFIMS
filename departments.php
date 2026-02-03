<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            if ($name) {
                $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $message = "Department added.";
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['department_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
                $stmt->execute([$id]);
                $message = "Department deleted.";
            } catch (PDOException $e) {
                $message = "Cannot delete department. Likely has related users or items.";
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['department_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
             if ($name) {
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE department_id = ?");
                $stmt->execute([$name, $description, $id]);
                $message = "Department updated.";
            }
        }
    }
}

$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
include 'includes/header.php';
?>

<h2>Department Management</h2>

<?php if($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Add Department Form -->
<div class="card mb-4">
    <div class="card-header">Add Department</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Department Name" required>
            </div>
            <div class="col-md-6">
                <input type="text" name="description" class="form-control" placeholder="Description">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($departments as $dept): ?>
        <tr>
            <form method="POST">
                <td><?= $dept['department_id'] ?></td>
                <td>
                    <input type="text" name="name" value="<?= htmlspecialchars($dept['name']) ?>" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="text" name="description" value="<?= htmlspecialchars($dept['description']) ?>" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="hidden" name="department_id" value="<?= $dept['department_id'] ?>">
                    <button type="submit" name="action" value="edit" class="btn btn-sm btn-warning">Save</button>
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this department?')">Delete</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>

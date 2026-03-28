<?php

namespace App\Middleware;

class AuditMiddleware
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Models\Model::getConnection();
    }

    public function handle(): bool
    {
        // Only log POST, PUT, DELETE requests
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            return true;
        }

        // Skip logging for certain endpoints
        $skipEndpoints = [
            'ajax/mark_notif_read.php',
            'auth/login.php',
            'auth/logout.php'
        ];

        $currentEndpoint = $_SERVER['SCRIPT_NAME'] ?? '';
        foreach ($skipEndpoints as $endpoint) {
            if (strpos($currentEndpoint, $endpoint) !== false) {
                return true;
            }
        }

        // Log the action
        $this->logAction();

        return true;
    }

    private function logAction(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $actionType = $this->getActionType();
            $entityName = $this->getEntityName();
            $entityId = $this->getEntityId();
            $description = $this->getDescription();
            $oldValues = $this->getOldValues();
            $newValues = $this->getNewValues();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $sql = "INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description, old_values, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $actionType,
                $entityName,
                $entityId,
                $description,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ipAddress,
                $userAgent
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the request
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }

    private function getActionType(): string
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Map HTTP methods to action types
        $actionMap = [
            'POST' => 'CREATE',
            'PUT' => 'UPDATE',
            'DELETE' => 'DELETE'
        ];

        // Check for specific actions in POST data
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            if (strpos($action, 'delete') !== false) {
                return 'DELETE';
            }
            if (strpos($action, 'update') !== false || strpos($action, 'edit') !== false) {
                return 'UPDATE';
            }
            if (strpos($action, 'create') !== false || strpos($action, 'add') !== false) {
                return 'CREATE';
            }
        }

        return $actionMap[$method] ?? 'UNKNOWN';
    }

    private function getEntityName(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Extract entity from script path
        $parts = explode('/', $scriptName);
        $filename = end($parts);
        $entity = str_replace('.php', '', $filename);

        // Map filenames to entity names
        $entityMap = [
            'items_add' => 'Item',
            'items_edit' => 'Item',
            'items_delete' => 'Item',
            'receive' => 'Transaction',
            'disburse' => 'Transaction',
            'move' => 'Transaction',
            'condemn' => 'Transaction',
            'users' => 'User',
            'categories' => 'Category',
            'sub_categories' => 'SubCategory',
            'departments' => 'Department',
            'buildings' => 'Building',
            'rooms' => 'Room',
            'import_items' => 'Item',
            'import_stock' => 'Transaction',
            'import_master' => 'MasterData'
        ];

        return $entityMap[$entity] ?? ucfirst($entity);
    }

    private function getEntityId(): ?int
    {
        // Check for ID in POST data
        if (isset($_POST['id'])) {
            return (int) $_POST['id'];
        }

        // Check for ID in GET data
        if (isset($_GET['id'])) {
            return (int) $_GET['id'];
        }

        // Check for specific ID fields
        $idFields = ['item_id', 'user_id', 'category_id', 'department_id', 'building_id', 'room_id'];
        foreach ($idFields as $field) {
            if (isset($_POST[$field])) {
                return (int) $_POST[$field];
            }
        }

        return null;
    }

    private function getDescription(): string
    {
        $entityName = $this->getEntityName();
        $actionType = $this->getActionType();
        $entityId = $this->getEntityId();

        $description = "{$actionType} {$entityName}";
        
        if ($entityId) {
            $description .= " (ID: {$entityId})";
        }

        // Add more context based on entity type
        switch ($entityName) {
            case 'Item':
                if (isset($_POST['name'])) {
                    $description .= ": {$_POST['name']}";
                }
                break;
            case 'User':
                if (isset($_POST['username'])) {
                    $description .= ": {$_POST['username']}";
                }
                break;
            case 'Transaction':
                if (isset($_POST['type'])) {
                    $description .= " ({$_POST['type']})";
                }
                if (isset($_POST['quantity'])) {
                    $description .= " - Qty: {$_POST['quantity']}";
                }
                break;
        }

        return $description;
    }

    private function getOldValues(): ?array
    {
        // For updates, we would need to fetch the old values from the database
        // This is a simplified implementation
        $actionType = $this->getActionType();
        
        if ($actionType !== 'UPDATE') {
            return null;
        }

        $entityId = $this->getEntityId();
        if (!$entityId) {
            return null;
        }

        // In a real implementation, you would fetch the old values from the database
        // For now, return null
        return null;
    }

    private function getNewValues(): ?array
    {
        $actionType = $this->getActionType();
        
        if ($actionType === 'DELETE') {
            return null;
        }

        // Return relevant POST data
        $relevantFields = $this->getRelevantFields();
        $newValues = [];

        foreach ($relevantFields as $field) {
            if (isset($_POST[$field])) {
                $newValues[$field] = $_POST[$field];
            }
        }

        return empty($newValues) ? null : $newValues;
    }

    private function getRelevantFields(): array
    {
        $entityName = $this->getEntityName();

        switch ($entityName) {
            case 'Item':
                return ['name', 'description', 'category_id', 'sub_category_id', 'uom', 'threshold_quantity', 'current_quantity'];
            case 'User':
                return ['full_name', 'username', 'email', 'role', 'status'];
            case 'Transaction':
                return ['item_id', 'type', 'quantity', 'date', 'department_id', 'room_id', 'recipient_name', 'contact_number', 'source_supplier', 'remarks'];
            case 'Category':
                return ['name'];
            case 'Department':
                return ['name'];
            case 'Building':
                return ['name'];
            case 'Room':
                return ['building_id', 'name', 'floor'];
            default:
                return [];
        }
    }
}

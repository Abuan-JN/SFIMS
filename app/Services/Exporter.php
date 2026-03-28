<?php

namespace App\Services;

class Exporter
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Models\Model::getConnection();
    }

    public function export(string $entity, string $format = 'csv', array $filters = []): string
    {
        $data = $this->getData($entity, $filters);
        
        switch ($format) {
            case 'csv':
                return $this->toCsv($data, $entity);
            case 'json':
                return $this->toJson($data);
            case 'excel':
                return $this->toExcel($data, $entity);
            case 'pdf':
                return $this->toPdf($data, $entity);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    private function getData(string $entity, array $filters = []): array
    {
        switch ($entity) {
            case 'items':
                return $this->getItemsData($filters);
            case 'transactions':
                return $this->getTransactionsData($filters);
            case 'users':
                return $this->getUsersData($filters);
            case 'categories':
                return $this->getCategoriesData($filters);
            case 'departments':
                return $this->getDepartmentsData($filters);
            case 'audit_logs':
                return $this->getAuditLogsData($filters);
            default:
                throw new \Exception("Unknown entity: {$entity}");
        }
    }

    private function getItemsData(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "i.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['sub_category_id'])) {
            $where[] = "i.sub_category_id = ?";
            $params[] = $filters['sub_category_id'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT i.*, c.name as category_name, sc.name as sub_category_name 
                FROM items i 
                LEFT JOIN categories c ON i.category_id = c.id 
                LEFT JOIN sub_categories sc ON i.sub_category_id = sc.id 
                WHERE $whereClause 
                ORDER BY i.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    private function getTransactionsData(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "t.date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "t.date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['item_id'])) {
            $where[] = "t.item_id = ?";
            $params[] = $filters['item_id'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT t.*, i.name as item_name, u.full_name as user_name, d.name as department_name 
                FROM transactions t 
                LEFT JOIN items i ON t.item_id = i.id 
                LEFT JOIN users u ON t.user_id = u.id 
                LEFT JOIN departments d ON t.department_id = d.id 
                WHERE $whereClause 
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    private function getUsersData(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT id, full_name, username, email, role, status, created_at FROM users WHERE $whereClause ORDER BY full_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    private function getCategoriesData(array $filters = []): array
    {
        $sql = "SELECT c.*, COUNT(i.id) as item_count 
                FROM categories c 
                LEFT JOIN items i ON c.id = i.category_id 
                GROUP BY c.id 
                ORDER BY c.name ASC";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll();
    }

    private function getDepartmentsData(array $filters = []): array
    {
        $sql = "SELECT d.*, COUNT(DISTINCT t.id) as transaction_count 
                FROM departments d 
                LEFT JOIN transactions t ON d.id = t.department_id 
                GROUP BY d.id 
                ORDER BY d.name ASC";
        
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll();
    }

    private function getAuditLogsData(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['action_type'])) {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT al.*, u.full_name as user_name 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE $whereClause 
                ORDER BY al.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    private function toCsv(array $data, string $entity): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://output', 'w');
        ob_start();

        // Add BOM for UTF-8
        echo "\xEF\xBB\xBF";

        // Add header
        fputcsv($output, array_keys($data[0]));

        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        
        return ob_get_clean();
    }

    private function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function toExcel(array $data, string $entity): string
    {
        // For simplicity, we'll generate CSV with Excel-specific formatting
        // In production, you would use a library like PhpSpreadsheet
        return $this->toCsv($data, $entity);
    }

    private function toPdf(array $data, string $entity): string
    {
        // For simplicity, we'll generate HTML that can be converted to PDF
        // In production, you would use a library like TCPDF or Dompdf
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$entity} Export</title>";
        $html .= "<style>body{font-family:Arial,sans-serif;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f2f2f2;}</style>";
        $html .= "</head><body>";
        $html .= "<h1>" . ucfirst($entity) . " Export</h1>";
        $html .= "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>";
        
        if (!empty($data)) {
            $html .= "<table><thead><tr>";
            foreach (array_keys($data[0]) as $header) {
                $html .= "<th>" . htmlspecialchars($header) . "</th>";
            }
            $html .= "</tr></thead><tbody>";
            
            foreach ($data as $row) {
                $html .= "<tr>";
                foreach ($row as $value) {
                    $html .= "<td>" . htmlspecialchars($value) . "</td>";
                }
                $html .= "</tr>";
            }
            
            $html .= "</tbody></table>";
        } else {
            $html .= "<p>No data available.</p>";
        }
        
        $html .= "</body></html>";
        
        return $html;
    }

    public function import(string $entity, string $filePath, array $options = []): array
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        switch ($fileExtension) {
            case 'csv':
                return $this->importFromCsv($entity, $filePath, $options);
            case 'json':
                return $this->importFromJson($entity, $filePath, $options);
            default:
                throw new \Exception("Unsupported file format: {$fileExtension}");
        }
    }

    private function importFromCsv(string $entity, string $filePath, array $options = []): array
    {
        $file = fopen($filePath, 'r');
        
        if ($file === false) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        $headers = fgetcsv($file);
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($headers, $row);
            
            try {
                $this->importRow($entity, $data, $options);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $imported + 2, // +2 for header and 1-based index
                    'data' => $data,
                    'error' => $e->getMessage()
                ];
            }
        }

        fclose($file);

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => $imported + count($errors)
        ];
    }

    private function importFromJson(string $entity, string $filePath, array $options = []): array
    {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if ($data === null) {
            throw new \Exception("Invalid JSON file");
        }

        $imported = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            try {
                $this->importRow($entity, $row, $options);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'data' => $row,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => $imported + count($errors)
        ];
    }

    private function importRow(string $entity, array $data, array $options = []): void
    {
        switch ($entity) {
            case 'items':
                $this->importItem($data, $options);
                break;
            case 'users':
                $this->importUser($data, $options);
                break;
            case 'categories':
                $this->importCategory($data, $options);
                break;
            case 'departments':
                $this->importDepartment($data, $options);
                break;
            default:
                throw new \Exception("Unknown entity: {$entity}");
        }
    }

    private function importItem(array $data, array $options = []): void
    {
        $validator = \App\Services\Validator::make($data, [
            'name' => 'required|max:255',
            'category_id' => 'required|numeric',
            'uom' => 'required|max:50'
        ]);

        if (!$validator->validate()) {
            throw new \Exception(implode(', ', $validator->getErrors()));
        }

        $sql = "INSERT INTO items (name, description, category_id, sub_category_id, uom, threshold_quantity, current_quantity) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'],
            $data['sub_category_id'] ?? null,
            $data['uom'],
            $data['threshold_quantity'] ?? 0,
            $data['current_quantity'] ?? 0
        ]);
    }

    private function importUser(array $data, array $options = []): void
    {
        $validator = \App\Services\Validator::make($data, [
            'full_name' => 'required|max:255',
            'username' => 'required|max:50',
            'email' => 'required|email',
            'role' => 'required|in:Admin,Staff'
        ]);

        if (!$validator->validate()) {
            throw new \Exception(implode(', ', $validator->getErrors()));
        }

        $password = $data['password'] ?? bin2hex(random_bytes(8));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (full_name, username, email, password_hash, role, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['full_name'],
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['role'],
            $data['status'] ?? 'pending'
        ]);
    }

    private function importCategory(array $data, array $options = []): void
    {
        $validator = \App\Services\Validator::make($data, [
            'name' => 'required|max:100'
        ]);

        if (!$validator->validate()) {
            throw new \Exception(implode(', ', $validator->getErrors()));
        }

        $sql = "INSERT INTO categories (name) VALUES (?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$data['name']]);
    }

    private function importDepartment(array $data, array $options = []): void
    {
        $validator = \App\Services\Validator::make($data, [
            'name' => 'required|max:100'
        ]);

        if (!$validator->validate()) {
            throw new \Exception(implode(', ', $validator->getErrors()));
        }

        $sql = "INSERT INTO departments (name) VALUES (?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$data['name']]);
    }

    public function getExportHeaders(string $entity): array
    {
        switch ($entity) {
            case 'items':
                return ['id', 'name', 'description', 'category_name', 'sub_category_name', 'uom', 'threshold_quantity', 'current_quantity', 'created_at'];
            case 'transactions':
                return ['id', 'item_name', 'type', 'quantity', 'date', 'department_name', 'user_name', 'remarks', 'created_at'];
            case 'users':
                return ['id', 'full_name', 'username', 'email', 'role', 'status', 'created_at'];
            case 'categories':
                return ['id', 'name', 'item_count', 'created_at'];
            case 'departments':
                return ['id', 'name', 'transaction_count', 'created_at'];
            case 'audit_logs':
                return ['id', 'user_name', 'action_type', 'entity_type', 'entity_id', 'description', 'created_at'];
            default:
                return [];
        }
    }
}

<?php

namespace App\Services;

class Search
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Models\Model::getConnection();
    }

    public function fullText(string $query, string $table, array $columns): array
    {
        $matchColumns = implode(',', $columns);
        
        $sql = "SELECT *, MATCH($matchColumns) AGAINST(? IN BOOLEAN MODE) as relevance 
                FROM $table 
                WHERE MATCH($matchColumns) AGAINST(? IN BOOLEAN MODE) 
                ORDER BY relevance DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$query, $query]);
        
        return $stmt->fetchAll();
    }

    public function searchItems(string $query, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ["1=1"];
        $params = [];

        // Full-text search
        if (!empty($query)) {
            $where[] = "(i.name LIKE ? OR i.description LIKE ? OR i.uom LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where[] = "i.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // Sub-category filter
        if (!empty($filters['sub_category_id'])) {
            $where[] = "i.sub_category_id = ?";
            $params[] = $filters['sub_category_id'];
        }

        // Stock level filter
        if (!empty($filters['stock_level'])) {
            switch ($filters['stock_level']) {
                case 'in_stock':
                    $where[] = "i.current_quantity > i.threshold_quantity";
                    break;
                case 'low_stock':
                    $where[] = "i.current_quantity <= i.threshold_quantity AND i.current_quantity > 0";
                    break;
                case 'out_of_stock':
                    $where[] = "i.current_quantity = 0";
                    break;
            }
        }

        // Price range filter
        if (!empty($filters['min_price'])) {
            $where[] = "i.unit_price >= ?";
            $params[] = $filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $where[] = "i.unit_price <= ?";
            $params[] = $filters['max_price'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total results
        $countSql = "SELECT COUNT(*) as total FROM items i WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT i.*, c.name as category_name, sc.name as sub_category_name 
                FROM items i 
                LEFT JOIN categories c ON i.category_id = c.id 
                LEFT JOIN sub_categories sc ON i.sub_category_id = sc.id 
                WHERE $whereClause 
                ORDER BY i.name ASC 
                LIMIT $perPage OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    public function searchTransactions(string $query, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ["1=1"];
        $params = [];

        // Full-text search
        if (!empty($query)) {
            $where[] = "(t.remarks LIKE ? OR t.source_supplier LIKE ? OR t.recipient_name LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Type filter
        if (!empty($filters['type'])) {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "t.date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "t.date <= ?";
            $params[] = $filters['date_to'];
        }

        // Item filter
        if (!empty($filters['item_id'])) {
            $where[] = "t.item_id = ?";
            $params[] = $filters['item_id'];
        }

        // Department filter
        if (!empty($filters['department_id'])) {
            $where[] = "t.department_id = ?";
            $params[] = $filters['department_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total results
        $countSql = "SELECT COUNT(*) as total FROM transactions t WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT t.*, i.name as item_name, u.full_name as user_name, d.name as department_name 
                FROM transactions t 
                LEFT JOIN items i ON t.item_id = i.id 
                LEFT JOIN users u ON t.user_id = u.id 
                LEFT JOIN departments d ON t.department_id = d.id 
                WHERE $whereClause 
                ORDER BY t.created_at DESC 
                LIMIT $perPage OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();

        return [
            'transactions' => $transactions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    public function searchUsers(string $query, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ["1=1"];
        $params = [];

        // Full-text search
        if (!empty($query)) {
            $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Role filter
        if (!empty($filters['role'])) {
            $where[] = "u.role = ?";
            $params[] = $filters['role'];
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "u.status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total results
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT u.* FROM users u WHERE $whereClause ORDER BY u.full_name ASC LIMIT $perPage OFFSET $offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    public function getSearchSuggestions(string $query, string $type = 'items', int $limit = 10): array
    {
        $suggestions = [];

        switch ($type) {
            case 'items':
                $sql = "SELECT DISTINCT name FROM items WHERE name LIKE ? ORDER BY name ASC LIMIT ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(["%{$query}%", $limit]);
                $suggestions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                break;

            case 'users':
                $sql = "SELECT DISTINCT full_name FROM users WHERE full_name LIKE ? ORDER BY full_name ASC LIMIT ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(["%{$query}%", $limit]);
                $suggestions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                break;

            case 'suppliers':
                $sql = "SELECT DISTINCT source_supplier FROM transactions WHERE source_supplier LIKE ? AND source_supplier IS NOT NULL ORDER BY source_supplier ASC LIMIT ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(["%{$query}%", $limit]);
                $suggestions = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                break;
        }

        return $suggestions;
    }
}

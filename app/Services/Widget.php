<?php

namespace App\Services;

class Widget
{
    private $db;
    private $cache;

    public function __construct()
    {
        $this->db = \App\Models\Model::getConnection();
        $this->cache = \App\Services\Cache::getInstance();
    }

    public function getStockAlerts(int $limit = 10): array
    {
        return $this->cache->remember('widget_stock_alerts_' . $limit, function() use ($limit) {
            $sql = "SELECT i.*, c.name as category_name 
                    FROM items i 
                    JOIN categories c ON i.category_id = c.id 
                    WHERE i.current_quantity <= i.threshold_quantity 
                    ORDER BY i.current_quantity ASC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
        }, 300); // Cache for 5 minutes
    }

    public function getRecentTransactions(int $limit = 10): array
    {
        return $this->cache->remember('widget_recent_transactions_' . $limit, function() use ($limit) {
            $sql = "SELECT t.*, i.name as item_name, u.full_name as user_name 
                    FROM transactions t 
                    LEFT JOIN items i ON t.item_id = i.id 
                    LEFT JOIN users u ON t.user_id = u.id 
                    ORDER BY t.created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
        }, 60); // Cache for 1 minute
    }

    public function getInventoryStats(): array
    {
        return $this->cache->remember('widget_inventory_stats', function() {
            $stats = [];

            // Total items
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM items");
            $stats['total_items'] = $stmt->fetch()['total'];

            // Total categories
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM categories");
            $stats['total_categories'] = $stmt->fetch()['total'];

            // Total departments
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM departments");
            $stats['total_departments'] = $stmt->fetch()['total'];

            // Low stock items
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE current_quantity <= threshold_quantity");
            $stats['low_stock_items'] = $stmt->fetch()['total'];

            // Out of stock items
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE current_quantity = 0");
            $stats['out_of_stock_items'] = $stmt->fetch()['total'];

            // Total transactions this month
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM transactions WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
            $stats['transactions_this_month'] = $stmt->fetch()['total'];

            // Total users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users");
            $stats['total_users'] = $stmt->fetch()['total'];

            // Pending users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
            $stats['pending_users'] = $stmt->fetch()['total'];

            return $stats;
        }, 600); // Cache for 10 minutes
    }

    public function getCategoryDistribution(): array
    {
        return $this->cache->remember('widget_category_distribution', function() {
            $sql = "SELECT c.name, COUNT(i.id) as item_count, SUM(i.current_quantity) as total_quantity
                    FROM categories c
                    LEFT JOIN items i ON c.id = i.category_id
                    GROUP BY c.id, c.name
                    ORDER BY item_count DESC";
            
            $stmt = $this->db->query($sql);
            
            return $stmt->fetchAll();
        }, 600); // Cache for 10 minutes
    }

    public function getMonthlyTransactionTrend(int $months = 6): array
    {
        return $this->cache->remember('widget_monthly_trend_' . $months, function() use ($months) {
            $sql = "SELECT 
                        DATE_FORMAT(date, '%Y-%m') as month,
                        type,
                        COUNT(*) as count,
                        SUM(quantity) as total_quantity
                    FROM transactions
                    WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL ? MONTH)
                    GROUP BY DATE_FORMAT(date, '%Y-%m'), type
                    ORDER BY month DESC, type";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$months]);
            
            return $stmt->fetchAll();
        }, 600); // Cache for 10 minutes
    }

    public function getTopItems(int $limit = 5): array
    {
        return $this->cache->remember('widget_top_items_' . $limit, function() use ($limit) {
            $sql = "SELECT i.*, c.name as category_name, 
                    (SELECT COUNT(*) FROM transactions t WHERE t.item_id = i.id) as transaction_count
                    FROM items i
                    LEFT JOIN categories c ON i.category_id = c.id
                    ORDER BY transaction_count DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
        }, 600); // Cache for 10 minutes
    }

    public function getRecentActivity(int $limit = 10): array
    {
        return $this->cache->remember('widget_recent_activity_' . $limit, function() use ($limit) {
            $sql = "SELECT al.*, u.full_name as user_name
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    ORDER BY al.created_at DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll();
        }, 60); // Cache for 1 minute
    }

    public function getDepartmentStats(): array
    {
        return $this->cache->remember('widget_department_stats', function() {
            $sql = "SELECT d.name, 
                    COUNT(DISTINCT t.id) as transaction_count,
                    COUNT(DISTINCT t.item_id) as unique_items,
                    SUM(CASE WHEN t.type = 'DISBURSE' THEN t.quantity ELSE 0 END) as items_received
                    FROM departments d
                    LEFT JOIN transactions t ON d.id = t.department_id
                    GROUP BY d.id, d.name
                    ORDER BY transaction_count DESC";
            
            $stmt = $this->db->query($sql);
            
            return $stmt->fetchAll();
        }, 600); // Cache for 10 minutes
    }

    public function renderWidget(string $widgetName, array $data = []): string
    {
        $viewFile = dirname(__DIR__, 2) . "/app/Views/widgets/{$widgetName}.php";
        
        if (!file_exists($viewFile)) {
            return "<div class='alert alert-warning'>Widget '{$widgetName}' not found</div>";
        }
        
        ob_start();
        extract($data);
        include $viewFile;
        
        return ob_get_clean();
    }
}

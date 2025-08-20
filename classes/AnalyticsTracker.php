<?php
class AnalyticsTracker 
{
    private $con;
    
    public function __construct($database) 
    {
        $this->con = $database;
    }
    
    public function trackSearch($searchTerm, $searchType, $resultsCount, $responseTimeMs = null) 
    {
        try {
            // Check if search_analytics table exists, if not, create it
            $this->ensureAnalyticsTable();
            
            $stmt = $this->con->prepare("INSERT INTO search_analytics (search_term, search_type, results_count, user_ip, user_agent, response_time_ms) VALUES (?, ?, ?, ?, ?, ?)");
            
            $userIp = $this->getUserIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt->execute([
                $searchTerm,
                $searchType,
                $resultsCount,
                $userIp,
                $userAgent,
                $responseTimeMs
            ]);
            
            return true;
        } catch (Exception $e) {
            // Log error but don't break the search functionality
            error_log("Analytics tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    public function trackClick($searchTerm, $resultId, $resultType) 
    {
        try {
            $this->ensureAnalyticsTable();
            
            // Update the most recent search with click information
            $stmt = $this->con->prepare("UPDATE search_analytics SET clicked_result_id = ?, clicked_result_type = ? WHERE search_term = ? AND user_ip = ? ORDER BY search_date DESC LIMIT 1");
            
            $userIp = $this->getUserIP();
            
            $stmt->execute([
                $resultId,
                $resultType,
                $searchTerm,
                $userIp
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Click tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getUserIP() 
    {
        // Get real IP address even if behind proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
    
    private function ensureAnalyticsTable() 
    {
        try {
            // Check if table exists
            $stmt = $this->con->prepare("SHOW TABLES LIKE 'search_analytics'");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Create the table
                $createTable = "CREATE TABLE `search_analytics` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `search_term` varchar(255) NOT NULL,
                  `search_type` ENUM('sites', 'images') NOT NULL DEFAULT 'sites',
                  `results_count` int(11) NOT NULL DEFAULT 0,
                  `user_ip` varchar(45),
                  `user_agent` text,
                  `response_time_ms` int(11),
                  `clicked_result_id` int(11) NULL,
                  `clicked_result_type` ENUM('site', 'image') NULL,
                  `search_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  INDEX `idx_search_term` (`search_term`),
                  INDEX `idx_search_type` (`search_type`),
                  INDEX `idx_search_date` (`search_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                $this->con->exec($createTable);
            }
        } catch (Exception $e) {
            error_log("Error ensuring analytics table: " . $e->getMessage());
        }
    }
    
    public function getTopSearches($limit = 10, $days = 30) 
    {
        try {
            $this->ensureAnalyticsTable();
            
            $stmt = $this->con->prepare("SELECT search_term, COUNT(*) as count FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY search_term ORDER BY count DESC LIMIT ?");
            $stmt->execute([$days, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getSearchStats($days = 30) 
    {
        try {
            $this->ensureAnalyticsTable();
            
            $stats = [];
            
            // Total searches
            $stmt = $this->con->prepare("SELECT COUNT(*) as total FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $stats['total_searches'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Unique terms
            $stmt = $this->con->prepare("SELECT COUNT(DISTINCT search_term) as unique FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $stats['unique_terms'] = $stmt->fetch(PDO::FETCH_ASSOC)['unique'];
            
            // Average response time
            $stmt = $this->con->prepare("SELECT AVG(response_time_ms) as avg_time FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL ? DAY) AND response_time_ms IS NOT NULL");
            $stmt->execute([$days]);
            $stats['avg_response_time'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_time'] ?? 0, 2);
            
            // Click through rate
            $stmt = $this->con->prepare("SELECT COUNT(*) as clicks FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL ? DAY) AND clicked_result_id IS NOT NULL");
            $stmt->execute([$days]);
            $clicks = $stmt->fetch(PDO::FETCH_ASSOC)['clicks'];
            $stats['click_through_rate'] = $stats['total_searches'] > 0 ? round(($clicks / $stats['total_searches']) * 100, 2) : 0;
            
            return $stats;
        } catch (Exception $e) {
            return [
                'total_searches' => 0,
                'unique_terms' => 0,
                'avg_response_time' => 0,
                'click_through_rate' => 0
            ];
        }
    }
}
?>
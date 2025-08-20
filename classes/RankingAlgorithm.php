<?php
/**
 * Advanced Ranking Algorithm for Doogle Search Engine
 * 
 * This class implements a sophisticated ranking system that considers:
 * - Content relevance and quality
 * - Page authority and link analysis
 * - User engagement signals
 * - Freshness and recency
 * - Semantic matching
 * - Performance metrics
 */
class RankingAlgorithm
{
    private $db;
    private $weights;
    private $debug;
    
    public function __construct($database, $debug = false) 
    {
        $this->db = $database;
        $this->debug = $debug;
        
        // Ranking weights - these can be tuned for optimal performance
        $this->weights = [
            'content_relevance' => 0.35,    // How well content matches query
            'authority_score' => 0.25,      // Page authority/PageRank
            'user_signals' => 0.20,         // CTR, dwell time, etc.
            'freshness' => 0.10,            // Content recency
            'quality_score' => 0.10         // Content quality indicators
        ];
    }
    
    /**
     * Main ranking function - ranks search results
     */
    public function rankResults($searchTerm, $results, $searchType = 'sites') 
    {
        if (empty($results)) {
            return [];
        }
        
        $rankedResults = [];
        $queryTerms = $this->extractQueryTerms($searchTerm);
        
        foreach ($results as $result) {
            $score = $this->calculateTotalScore($result, $queryTerms, $searchType);
            $result['ranking_score'] = $score;
            $result['ranking_details'] = $this->debug ? $this->getScoreBreakdown($result, $queryTerms, $searchType) : null;
            $rankedResults[] = $result;
        }
        
        // Sort by ranking score (highest first)
        usort($rankedResults, function($a, $b) {
            return $b['ranking_score'] <=> $a['ranking_score'];
        });
        
        return $rankedResults;
    }
    
    /**
     * Calculate total ranking score for a single result
     */
    private function calculateTotalScore($result, $queryTerms, $searchType) 
    {
        $scores = [
            'content_relevance' => $this->calculateContentRelevance($result, $queryTerms, $searchType),
            'authority_score' => $this->calculateAuthorityScore($result, $searchType),
            'user_signals' => $this->calculateUserSignals($result, $searchType),
            'freshness' => $this->calculateFreshnessScore($result, $searchType),
            'quality_score' => $this->calculateQualityScore($result, $searchType)
        ];
        
        // Calculate weighted total
        $totalScore = 0;
        foreach ($scores as $factor => $score) {
            $totalScore += $score * $this->weights[$factor];
        }
        
        // Apply boost factors
        $totalScore = $this->applyBoostFactors($totalScore, $result, $queryTerms, $searchType);
        
        return round($totalScore, 4);
    }
    
    /**
     * Content Relevance Score (35% weight)
     * Measures how well the content matches the search query
     */
    private function calculateContentRelevance($result, $queryTerms, $searchType) 
    {
        $score = 0;
        
        if ($searchType === 'sites') {
            // Title matching (highest weight)
            $titleScore = $this->calculateTextRelevance($result['title'] ?? '', $queryTerms, 1.0);
            
            // Description matching
            $descScore = $this->calculateTextRelevance($result['description'] ?? '', $queryTerms, 0.7);
            
            // Keywords matching
            $keywordScore = $this->calculateTextRelevance($result['keywords'] ?? '', $queryTerms, 0.5);
            
            // URL matching (for exact matches)
            $urlScore = $this->calculateTextRelevance($result['url'] ?? '', $queryTerms, 0.3);
            
            $score = ($titleScore * 0.5) + ($descScore * 0.3) + ($keywordScore * 0.15) + ($urlScore * 0.05);
        } else {
            // Image relevance
            $altScore = $this->calculateTextRelevance($result['alt'] ?? '', $queryTerms, 1.0);
            $titleScore = $this->calculateTextRelevance($result['title'] ?? '', $queryTerms, 0.8);
            
            $score = ($altScore * 0.7) + ($titleScore * 0.3);
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Calculate text relevance using various matching techniques
     */
    private function calculateTextRelevance($text, $queryTerms, $weight) 
    {
        if (empty($text) || empty($queryTerms)) {
            return 0;
        }
        
        $text = strtolower($text);
        $score = 0;
        $termCount = count($queryTerms);
        
        foreach ($queryTerms as $term) {
            $term = strtolower(trim($term));
            if (empty($term)) continue;
            
            // Exact phrase match (highest score)
            if (strpos($text, $term) !== false) {
                $score += 1.0;
                
                // Bonus for word boundaries
                if (preg_match('/\b' . preg_quote($term, '/') . '\b/', $text)) {
                    $score += 0.5;
                }
                
                // Bonus for position (earlier = better)
                $position = strpos($text, $term);
                $positionBonus = max(0, (strlen($text) - $position) / strlen($text) * 0.3);
                $score += $positionBonus;
            }
            
            // Partial matches
            $similarity = 0;
            similar_text($term, $text, $similarity);
            $score += ($similarity / 100) * 0.3;
        }
        
        return min(1.0, ($score / $termCount) * $weight);
    }
    
    /**
     * Authority Score (25% weight)
     * Based on page authority, domain authority, and link analysis
     */
    private function calculateAuthorityScore($result, $searchType) 
    {
        if ($searchType !== 'sites') {
            return 0.5; // Default for images
        }
        
        $score = 0;
        $url = $result['url'] ?? '';
        
        // Domain authority indicators
        $domain = parse_url($url, PHP_URL_HOST);
        if ($domain) {
            // Well-known domains get higher scores
            $authorityDomains = [
                'wikipedia.org' => 0.95,
                'github.com' => 0.90,
                'stackoverflow.com' => 0.85,
                'medium.com' => 0.80,
                'reddit.com' => 0.75
            ];
            
            foreach ($authorityDomains as $authDomain => $authScore) {
                if (strpos($domain, $authDomain) !== false) {
                    $score = max($score, $authScore);
                    break;
                }
            }
        }
        
        // URL structure indicators
        if (strpos($url, 'https://') === 0) {
            $score += 0.1; // HTTPS bonus
        }
        
        // Path depth (shorter is often better for authority pages)
        $pathDepth = substr_count(parse_url($url, PHP_URL_PATH) ?? '/', '/');
        $score += max(0, (5 - $pathDepth) / 10);
        
        // Click-based authority (pages that get more clicks are more authoritative)
        $clicks = (int)($result['clicks'] ?? 0);
        if ($clicks > 0) {
            // Logarithmic scale for clicks
            $clickScore = min(0.3, log10($clicks + 1) / 4);
            $score += $clickScore;
        }
        
        return min(1.0, $score);
    }
    
    /**
     * User Signals Score (20% weight)
     * Based on user engagement metrics
     */
    private function calculateUserSignals($result, $searchType) 
    {
        $score = 0.5; // Default baseline
        $id = $result['id'] ?? 0;
        
        if ($id <= 0) {
            return $score;
        }
        
        try {
            // Get user engagement data from analytics
            $table = $searchType === 'sites' ? 'site' : 'image';
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as click_count,
                    AVG(CASE WHEN clicked_result_id IS NOT NULL THEN 1 ELSE 0 END) as ctr
                FROM search_analytics 
                WHERE clicked_result_type = ? AND clicked_result_id = ? 
                AND search_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$table, $id]);
            $engagement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($engagement) {
                // Click-through rate (CTR) scoring
                $ctr = (float)$engagement['ctr'];
                $score += min(0.4, $ctr * 2); // Max 40% bonus for high CTR
                
                // Click volume scoring
                $clickCount = (int)$engagement['click_count'];
                if ($clickCount > 0) {
                    $score += min(0.3, log10($clickCount + 1) / 10);
                }
            }
            
            // Recency of clicks (recent clicks are more valuable)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as recent_clicks 
                FROM search_analytics 
                WHERE clicked_result_type = ? AND clicked_result_id = ? 
                AND search_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$table, $id]);
            $recentClicks = $stmt->fetch(PDO::FETCH_ASSOC)['recent_clicks'] ?? 0;
            
            if ($recentClicks > 0) {
                $score += min(0.2, $recentClicks / 50); // Bonus for recent engagement
            }
            
        } catch (Exception $e) {
            // If analytics table doesn't exist, use basic click count
            $clicks = (int)($result['clicks'] ?? 0);
            $score = 0.5 + min(0.3, $clicks / 100);
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Freshness Score (10% weight)
     * Newer content gets higher scores for time-sensitive queries
     */
    private function calculateFreshnessScore($result, $searchType) 
    {
        $createdAt = $result['created_at'] ?? null;
        $updatedAt = $result['updated_at'] ?? null;
        
        if (!$createdAt && !$updatedAt) {
            return 0.5; // Default for content without timestamps
        }
        
        $timestamp = $updatedAt ?: $createdAt;
        $daysSinceCreated = (time() - strtotime($timestamp)) / (24 * 3600);
        
        // Fresher content gets higher scores
        if ($daysSinceCreated <= 1) {
            return 1.0; // Very fresh
        } elseif ($daysSinceCreated <= 7) {
            return 0.9; // Fresh
        } elseif ($daysSinceCreated <= 30) {
            return 0.7; // Recent
        } elseif ($daysSinceCreated <= 90) {
            return 0.5; // Moderate
        } elseif ($daysSinceCreated <= 365) {
            return 0.3; // Old
        } else {
            return 0.1; // Very old
        }
    }
    
    /**
     * Quality Score (10% weight)
     * Based on content quality indicators
     */
    private function calculateQualityScore($result, $searchType) 
    {
        $score = 0.5; // Baseline
        
        if ($searchType === 'sites') {
            $title = $result['title'] ?? '';
            $description = $result['description'] ?? '';
            $url = $result['url'] ?? '';
            
            // Title quality
            if (strlen($title) >= 10 && strlen($title) <= 60) {
                $score += 0.2; // Good title length
            }
            
            // Description quality
            if (strlen($description) >= 50 && strlen($description) <= 160) {
                $score += 0.2; // Good description length
            }
            
            // URL quality
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $score += 0.1; // Valid URL
            }
            
            // Content completeness
            if (!empty($title) && !empty($description) && !empty($result['keywords'] ?? '')) {
                $score += 0.15; // Complete metadata
            }
            
        } else {
            // Image quality indicators
            $alt = $result['alt'] ?? '';
            $title = $result['title'] ?? '';
            
            if (strlen($alt) >= 5) {
                $score += 0.3; // Has meaningful alt text
            }
            
            if (!empty($title)) {
                $score += 0.2; // Has title
            }
            
            // Not broken
            if (empty($result['broken']) || $result['broken'] == 0) {
                $score += 0.3; // Working image
            } else {
                $score = 0.1; // Broken image penalty
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Apply boost factors for special cases
     */
    private function applyBoostFactors($baseScore, $result, $queryTerms, $searchType) 
    {
        $boostedScore = $baseScore;
        
        // Exact title match boost
        if ($searchType === 'sites') {
            $title = strtolower($result['title'] ?? '');
            $query = strtolower(implode(' ', $queryTerms));
            
            if ($title === $query) {
                $boostedScore *= 1.5; // 50% boost for exact title match
            } elseif (strpos($title, $query) === 0) {
                $boostedScore *= 1.3; // 30% boost for title starting with query
            }
        }
        
        // HTTPS boost
        if (isset($result['url']) && strpos($result['url'], 'https://') === 0) {
            $boostedScore *= 1.1; // 10% boost for HTTPS
        }
        
        // Popular content boost (high click count)
        $clicks = (int)($result['clicks'] ?? 0);
        if ($clicks > 100) {
            $boostedScore *= 1.2; // 20% boost for popular content
        } elseif ($clicks > 50) {
            $boostedScore *= 1.1; // 10% boost for moderately popular content
        }
        
        // Penalty for broken images
        if ($searchType === 'images' && !empty($result['broken']) && $result['broken'] == 1) {
            $boostedScore *= 0.1; // Heavy penalty for broken images
        }
        
        return $boostedScore;
    }
    
    /**
     * Extract and normalize query terms
     */
    private function extractQueryTerms($searchTerm) 
    {
        // Remove special characters and normalize
        $searchTerm = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $searchTerm);
        $terms = preg_split('/\s+/', trim($searchTerm));
        
        // Remove empty terms and common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        
        $filteredTerms = [];
        foreach ($terms as $term) {
            $term = trim(strtolower($term));
            if (strlen($term) >= 2 && !in_array($term, $stopWords)) {
                $filteredTerms[] = $term;
            }
        }
        
        return $filteredTerms;
    }
    
    /**
     * Get detailed score breakdown for debugging
     */
    private function getScoreBreakdown($result, $queryTerms, $searchType) 
    {
        return [
            'content_relevance' => $this->calculateContentRelevance($result, $queryTerms, $searchType),
            'authority_score' => $this->calculateAuthorityScore($result, $searchType),
            'user_signals' => $this->calculateUserSignals($result, $searchType),
            'freshness' => $this->calculateFreshnessScore($result, $searchType),
            'quality_score' => $this->calculateQualityScore($result, $searchType),
            'query_terms' => $queryTerms,
            'weights' => $this->weights
        ];
    }
    
    /**
     * Update ranking weights (for A/B testing and optimization)
     */
    public function updateWeights($newWeights) 
    {
        foreach ($newWeights as $factor => $weight) {
            if (isset($this->weights[$factor]) && is_numeric($weight) && $weight >= 0 && $weight <= 1) {
                $this->weights[$factor] = (float)$weight;
            }
        }
        
        // Normalize weights to sum to 1
        $total = array_sum($this->weights);
        if ($total > 0) {
            foreach ($this->weights as &$weight) {
                $weight /= $total;
            }
        }
    }
    
    /**
     * Get current ranking weights
     */
    public function getWeights() 
    {
        return $this->weights;
    }
    
    /**
     * Enable/disable debug mode
     */
    public function setDebugMode($debug) 
    {
        $this->debug = (bool)$debug;
    }
}
?>
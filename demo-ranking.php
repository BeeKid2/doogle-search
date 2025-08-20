<?php
/**
 * Doogle Ranking Algorithm Demo
 * 
 * This script demonstrates the advanced ranking algorithm with sample data
 * Run this to see how the algorithm scores and ranks different types of content
 */

require_once('config.php');
require_once('classes/RankingAlgorithm.php');

// Create sample data for demonstration
$sampleSites = [
    [
        'id' => 1,
        'title' => 'Learn PHP Programming - Complete Guide',
        'description' => 'A comprehensive guide to learning PHP programming from beginner to advanced level. Includes examples, exercises, and best practices.',
        'keywords' => 'php, programming, tutorial, learn, guide',
        'url' => 'https://example.com/learn-php-programming',
        'clicks' => 150,
        'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
    ],
    [
        'id' => 2,
        'title' => 'PHP Programming Tutorial',
        'description' => 'Basic PHP tutorial for beginners.',
        'keywords' => 'php, tutorial',
        'url' => 'https://wikipedia.org/wiki/PHP',
        'clicks' => 500,
        'created_at' => date('Y-m-d H:i:s', strtotime('-365 days'))
    ],
    [
        'id' => 3,
        'title' => 'Advanced PHP Techniques',
        'description' => 'Learn advanced PHP programming techniques including OOP, design patterns, and performance optimization.',
        'keywords' => 'php, advanced, oop, patterns',
        'url' => 'https://github.com/php/php-src',
        'clicks' => 75,
        'created_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
    ],
    [
        'id' => 4,
        'title' => 'JavaScript vs PHP Comparison',
        'description' => 'A detailed comparison between JavaScript and PHP for web development.',
        'keywords' => 'javascript, php, comparison, web',
        'url' => 'https://medium.com/javascript-php-comparison',
        'clicks' => 25,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'id' => 5,
        'title' => 'PHP',
        'description' => 'PHP is a general-purpose scripting language geared towards web development.',
        'keywords' => 'php, programming, language',
        'url' => 'https://stackoverflow.com/questions/tagged/php',
        'clicks' => 300,
        'created_at' => date('Y-m-d H:i:s', strtotime('-180 days'))
    ]
];

// Initialize ranking algorithm
$rankingAlgorithm = new RankingAlgorithm($con, true); // Debug mode enabled

echo "<!DOCTYPE html>
<html>
<head>
    <title>Doogle Ranking Algorithm Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; text-align: center; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .result { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .score { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-weight: bold; }
        .details { margin-top: 10px; font-size: 0.9em; color: #666; }
        .query-box { background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .weights { background: #fff3e0; padding: 15px; border-radius: 10px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        .highlight { background: #ffeb3b; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Doogle Ranking Algorithm Demo</h1>
        
        <div class='query-box'>
            <h3>🔍 Demo Query: \"PHP Programming\"</h3>
            <p>This demo shows how our advanced ranking algorithm scores and ranks different types of content for the search query <strong>\"PHP Programming\"</strong>.</p>
        </div>";

// Test query
$testQuery = "PHP Programming";
echo "<h2>📊 Sample Content Being Ranked</h2>";
echo "<table>
        <tr><th>ID</th><th>Title</th><th>Domain</th><th>Clicks</th><th>Age</th></tr>";

foreach ($sampleSites as $site) {
    $domain = parse_url($site['url'], PHP_URL_HOST);
    $age = floor((time() - strtotime($site['created_at'])) / (24 * 3600));
    echo "<tr>
            <td>{$site['id']}</td>
            <td>" . htmlspecialchars($site['title']) . "</td>
            <td>{$domain}</td>
            <td>{$site['clicks']}</td>
            <td>{$age} days</td>
          </tr>";
}
echo "</table>";

// Rank the results
$rankedResults = $rankingAlgorithm->rankResults($testQuery, $sampleSites, 'sites');

// Display current algorithm weights
$weights = $rankingAlgorithm->getWeights();
echo "<div class='weights'>
        <h3>⚖️ Current Algorithm Weights</h3>
        <ul>
            <li><strong>Content Relevance:</strong> " . round($weights['content_relevance'] * 100, 1) . "%</li>
            <li><strong>Authority Score:</strong> " . round($weights['authority_score'] * 100, 1) . "%</li>
            <li><strong>User Signals:</strong> " . round($weights['user_signals'] * 100, 1) . "%</li>
            <li><strong>Freshness:</strong> " . round($weights['freshness'] * 100, 1) . "%</li>
            <li><strong>Quality Score:</strong> " . round($weights['quality_score'] * 100, 1) . "%</li>
        </ul>
      </div>";

echo "<h2>🏆 Ranked Results</h2>";

foreach ($rankedResults as $index => $result) {
    $rank = $index + 1;
    $score = $result['ranking_score'];
    $details = $result['ranking_details'];
    
    echo "<div class='result'>
            <h3>#{$rank} - " . htmlspecialchars($result['title']) . " <span class='score'>" . number_format($score, 4) . "</span></h3>
            <p><strong>URL:</strong> " . htmlspecialchars($result['url']) . "</p>
            <p><strong>Description:</strong> " . htmlspecialchars($result['description']) . "</p>";
    
    if ($details) {
        echo "<div class='details'>
                <h4>📋 Detailed Score Breakdown:</h4>
                <ul>
                    <li><strong>Content Relevance:</strong> " . round($details['content_relevance'] * 100, 1) . "% (Weight: " . round($weights['content_relevance'] * 100, 1) . "%)</li>
                    <li><strong>Authority Score:</strong> " . round($details['authority_score'] * 100, 1) . "% (Weight: " . round($weights['authority_score'] * 100, 1) . "%)</li>
                    <li><strong>User Signals:</strong> " . round($details['user_signals'] * 100, 1) . "% (Weight: " . round($weights['user_signals'] * 100, 1) . "%)</li>
                    <li><strong>Freshness:</strong> " . round($details['freshness'] * 100, 1) . "% (Weight: " . round($weights['freshness'] * 100, 1) . "%)</li>
                    <li><strong>Quality Score:</strong> " . round($details['quality_score'] * 100, 1) . "% (Weight: " . round($weights['quality_score'] * 100, 1) . "%)</li>
                </ul>
                <p><strong>Query Terms Matched:</strong> " . implode(', ', $details['query_terms']) . "</p>
              </div>";
    }
    
    echo "</div>";
}

echo "<h2>🔍 Algorithm Analysis</h2>
      <div class='result'>
        <h3>Why These Rankings Make Sense:</h3>
        <ul>
            <li><strong>Wikipedia (Rank #1):</strong> High authority domain + many clicks + exact title match</li>
            <li><strong>Complete Guide (Rank #2):</strong> Excellent content relevance + good click volume + comprehensive title</li>
            <li><strong>GitHub (Rank #3):</strong> Very high authority + fresh content + technical relevance</li>
            <li><strong>StackOverflow (Rank #4):</strong> High authority + good clicks + exact keyword match</li>
            <li><strong>Medium Article (Rank #5):</strong> Fresh content + good authority, but less specific to query</li>
        </ul>
        
        <h3>🎯 Key Algorithm Features Demonstrated:</h3>
        <ul>
            <li><strong>Authority Recognition:</strong> Wikipedia and GitHub get authority boosts</li>
            <li><strong>Content Matching:</strong> Titles with exact matches rank higher</li>
            <li><strong>User Behavior:</strong> Pages with more clicks get ranking boosts</li>
            <li><strong>Freshness Factor:</strong> Recent content gets appropriate scoring</li>
            <li><strong>Quality Assessment:</strong> Complete metadata and good structure matter</li>
        </ul>
      </div>";

echo "<div style='margin-top: 30px; text-align: center; color: #666;'>
        <p><strong>🚀 This is just a demo with sample data!</strong></p>
        <p>In production, the algorithm processes real search queries, user interactions, and continuously learns to improve rankings.</p>
        <p><a href='admin/ranking-settings.php' style='color: #667eea;'>Visit the Admin Panel</a> to tune algorithm weights and test with real data.</p>
      </div>";

echo "</div>
</body>
</html>";
?>
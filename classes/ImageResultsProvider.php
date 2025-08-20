<?php
require_once('RankingAlgorithm.php');

class ImageResultsProvider
{
	private $con;
	private $rankingAlgorithm;

	public function __construct($con) 
	{
		$this->con = $con;
		$this->rankingAlgorithm = new RankingAlgorithm($con);
	}

	public function getNumResults($term) 
	{
		$query = $this->con->prepare("SELECT COUNT(*) as total 
										 FROM images 
										 WHERE (title LIKE :term 
										 OR alt LIKE :term)
										 AND broken=0");

		$searchTerm = "%". $term . "%";
		$query->bindParam(":term", $searchTerm);
		$query->execute();

		$row = $query->fetch(PDO::FETCH_ASSOC);
		return $row["total"];
	}

	public function getResultsHtml($page, $pageSize, $term) 
	{
		// Get all matching results first (without LIMIT for proper ranking)
		$query = $this->con->prepare("SELECT * 
										 FROM images 
										 WHERE (title LIKE :term 
										 OR alt LIKE :term)
										 AND broken=0");

		$searchTerm = "%". $term . "%";
		$query->bindParam(":term", $searchTerm);
		$query->execute();
		
		$allResults = $query->fetchAll(PDO::FETCH_ASSOC);
		
		// Apply advanced ranking algorithm
		$rankedResults = $this->rankingAlgorithm->rankResults($term, $allResults, 'images');
		
		// Apply pagination to ranked results
		$fromLimit = ($page - 1) * $pageSize;
		$pagedResults = array_slice($rankedResults, $fromLimit, $pageSize);

		$resultsHtml = "<div class='imageResults'>";

		$count = 0;
		foreach($pagedResults as $row) 
		{
			$count++;
			$id = $row["id"];
			$imageUrl = $row["imageUrl"];
			$siteUrl = $row["siteUrl"];
			$title = $row["title"];
			$alt = $row["alt"];
			$rankingScore = $row["ranking_score"] ?? 0;

			if($title)
				$displayText = $title;
			else if($alt)
				$displayText = $alt;
			else
				$displayText = $imageUrl;
			
			// Add ranking debug info if requested
			$debugInfo = "";
			if (isset($_GET['debug']) && $_GET['debug'] == 1) {
				$debugInfo = " (Score: " . number_format($rankingScore, 3) . ")";
			}
			
			$resultsHtml .= "<div class='gridItem image$count'>
								<a href='$imageUrl' data-fancybox data-caption='$displayText$debugInfo'
									data-siteurl='$siteUrl' data-linkId='$id' data-search-term='$term'>
									
									<script>
									$(document).ready(function() {
										loadImage(\"$imageUrl\", \"image$count\");
									});
									</script>

									<span class='details'>$displayText$debugInfo</span>
								</a>

							</div>";
		}

		$resultsHtml .= "</div>";

		return $resultsHtml;
	}
}
?>
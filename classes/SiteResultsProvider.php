<?php
require_once('RankingAlgorithm.php');

class SiteResultsProvider
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
										 FROM sites WHERE title LIKE :term 
										 OR url LIKE :term 
										 OR keywords LIKE :term 
										 OR description LIKE :term");

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
										 FROM sites WHERE title LIKE :term 
										 OR url LIKE :term 
										 OR keywords LIKE :term 
										 OR description LIKE :term");

		$searchTerm = "%". $term . "%";
		$query->bindParam(":term", $searchTerm);
		$query->execute();
		
		$allResults = $query->fetchAll(PDO::FETCH_ASSOC);
		
		// Apply advanced ranking algorithm
		$rankedResults = $this->rankingAlgorithm->rankResults($term, $allResults, 'sites');
		
		// Apply pagination to ranked results
		$fromLimit = ($page - 1) * $pageSize;
		$pagedResults = array_slice($rankedResults, $fromLimit, $pageSize);

		$resultsHtml = "<div class='siteResults'>";

		foreach($pagedResults as $row) 
		{
			$id = $row["id"];
			$url = $row["url"];
			$title = $row["title"];
			$description = $row["description"];
			$rankingScore = $row["ranking_score"] ?? 0;

			$title = $this->trimField($title, 55);
			$description = $this->trimField($description, 230);
			
			// Add ranking score for debugging (can be removed in production)
			$debugInfo = "";
			if (isset($_GET['debug']) && $_GET['debug'] == 1) {
				$debugInfo = "<div class='ranking-debug' style='font-size: 0.8em; color: #666; margin-top: 5px;'>
								Ranking Score: " . number_format($rankingScore, 4) . "
							  </div>";
			}
			
			$resultsHtml .= "<div class='resultContainer'>
								<h3 class='title'>
									<a class='result' href='$url' data-linkId='$id' data-search-term='$term'>
										$title
									</a>
								</h3>
								<span class='url'>$url</span>
								<span class='description'>$description</span>
								$debugInfo
							</div>";
		}

		$resultsHtml .= "</div>";

		return $resultsHtml;
	}

	private function trimField($string, $characterLimit) 
	{
		$dots = strlen($string) > $characterLimit ? "..." : "";
		return substr($string, 0, $characterLimit) . $dots;
	}	
}
?>
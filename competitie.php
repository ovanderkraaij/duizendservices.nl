<?php

require_once('library/open-database.php');
require_once('library/nbo.php');
require_once('library/bean.php');
require_once('library/constants.php');
require_once('library/util.php');

$repository = new Repository();

if (!isset($seasonId)) {
    $seasonId = isset($_GET['seizoen']) ? $_GET['seizoen'] : 'current';
}

$season = new Season($seasonId);

if (is_null($season->getId())) {
    $season = new Season('current');
}

$leagueBeans = array();

foreach ($season->getLeagues() as $league) {
    $leagueBean = new LeagueBean();
    $leagueBean->id = $league->getId();
    $leagueBean->label = $league->getLabel();
    $leagueBean->virtual = $league->hasVirtualQuestions($season->getId());
    $leagueBeans[] = $leagueBean;
}

echo json_encode($leagueBeans);

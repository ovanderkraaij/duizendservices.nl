<?php

/* Template Name: Stand */

require_once('library/open-database.php');
require_once('library/nbo.php');
require_once('library/bean.php');
require_once('library/constants.php');
require_once('library/util.php');

$repository = new Repository();
$cacheKey = CACHE_KEY_RANKING;

//Paginaspecifieke toewijzingen
$currentLeaders = array();
$currentLeaderStats = array();
$presentation = new Presentation();

if (!isset($seasonId)) {
    $seasonId = isset($_GET['seizoen']) ? $_GET['seizoen'] : 'current';
}

$season = new Season($seasonId);

if (is_null($season->getId())) {
    $season = new Season('current');
}
$thisSeason = new Season("current");
$isThisSeason = $season->getId() == $thisSeason->getId();

if (!isset($leagueId)) {
    $leagueId = isset($_GET['competitie']) ? $_GET['competitie'] : '1';
}

$league = new League($leagueId);

if (is_null($league->getId())) {
    $league = new League(1);
}

$seasonId = $season->getId();

if (!isset($virtual)) {
    $virtual = isset($_GET['virtueel']) ? $_GET['virtueel'] : '0';
}

$notVirtual = ($virtual == '1') ? '0' : '1';

if (!isset($inline)) {
    $inline = isset($_GET['inline']) ? $_GET['inline'] : '0';
}

$items = isset($_GET['items']) ? $_GET['items'] : '10';
$top = isset($_GET['top']) ? $_GET['top'] : '0';
$instance = isset($_GET['instantie']) ? $_GET['instantie'] : '1';
$stats = isset($_GET['statistieken']) ? $_GET['statistieken'] : '1';

//Leiders Glazen Bol
$numbersOneBol = $season->getNumberOne(1, 0, array(), "max", 1);
$leadersBol = array();
if (!empty($numbersOneBol)) {
    $itNumbersOne = $numbersOneBol->getIterator();
    while ($itNumbersOne->valid()) {
        $curNumberOne = $itNumbersOne->current();
        $leadersBol[] = $curNumberOne->user->getId();
        //echo "<!-- Added userId:".$curNumberOne->user->getId()."-->";
        $itNumbersOne->next();
    }
}
//Leiders deze competitie
$numbersOneThisLeague = $season->getNumberOne($league->getId(), 0, $leadersBol, "max", 1);
$leadersThisLeague = array();
if (!empty($numbersOneThisLeague)) {
    $itNumbersOne = $numbersOneThisLeague->getIterator();
    while ($itNumbersOne->valid()) {
        $curNumberOne = $itNumbersOne->current();
        $leadersThisLeague[] = $curNumberOne->user->getId();
        //echo $curNumberOne->user->getUserName();
        $itNumbersOne->next();
    }
}
//Collecties
$startSolutions = $season->getMainQuestionsSolved(1, $virtual);
$startClassifications = $season->getClassifications($leagueId, $virtual);
if (!is_null($startSolutions) && !empty($startClassifications)) {
    $leagues = $season->getLeagues();
    $questionsAnswered = $league->getQuestionsAnswered($seasonId, true, $virtual);
    $questionsAnsweredCount = !is_null($questionsAnswered) ? $questionsAnswered->count() : 0;
    $totalQuestions = $league->getQuestions($seasonId, true);
    $totalQuestionsCount = !is_null($totalQuestions) ? $totalQuestions->count() : 0;
    $lastUpdate = "";
    $curBeans = new ArrayObject();
    $grayArray = array();
    $previousArray = array();
    $winnersArray = array();
    $empty = false;
    $questionsAnswered = 0;
    /**
     * Deze pagina is bedoeld voor alle standen die te vinden zijn
     * in de tabel Classification van de database.
     *
     * Eerst worden de classification van het seizoen en de
     * betreffende league opgehaald, eventueel aangevuld met
     * de virtuele variabele.
     *
     */
    $classifications = $season->getClassifications($leagueId, $virtual);
    if (is_null($classifications) || $classifications->count() == 0) {
        $classifications = $season->getEmptyClassifications($leagueId, $virtual, $season->getId());
        $empty = true;
    }
    $itClassifications = $classifications->getIterator();
    /**
     * Om de tabel op de juiste wijze weer te geven is het
     * nodig te weten hoeveel inzendingen er in totaal zijn
     */
    $counter = 1;
    /**
     * Tijdens de eerste loop worden ook de vorige score opgehaald
     * om zo de vorige stand weer te kunnen geven.
     */
    while ($itClassifications->valid()) {
        $curClassification = $itClassifications->current();
        $curBean = new ClassificationBean($curClassification, $league->getType());
        $curUserId = $curClassification->getUserId();
        $curBean->id = $curUserId;
        if ($curClassification->getSeed() == 1) {
            $leader = new User($curUserId);
            $currentLeaders[] = $leader->getUserName();
        }
        if ($counter != 1 && $curClassification->getSeed() == $lastSeed) {
            $curBean->seed = "";
        } else {
            if ($counter == 1) {
                $lastUpdate = $curClassification->getInsertion();
                //$lastUpdate = DateUtils::utc2cet($lastUpdate, FORMAT, TIMEZONE);
                $lastUpdate = DateUtils::getEuropeanDate($lastUpdate);
                $questionsAnswered = $curClassification->getSequence();
                if ($virtual) {
                    $previousClassifications = $season->getClassifications($leagueId, $notVirtual);
                } else {
                    $previousClassifications = $season->getPreviousClassifications($leagueId, $virtual, $curClassification->getSequence() - 1);
                }
                if (!is_null($previousClassifications) && $previousClassifications->count() > 0) {
                    $itPreviousClassifications = $previousClassifications->getIterator();
                    while ($itPreviousClassifications->valid()) {
                        $curPreviousClassification = $itPreviousClassifications->current();
                        $previousArray[$curPreviousClassification->getUserId()] = $curPreviousClassification->getSeed();
                        $itPreviousClassifications->next();
                    }
                }
                /**
                 * Ophalen van seeds in sum-competitie voor gray-variabele
                 */
                if ($league->getType() != 'sum') {
                    $grayClassifications = $season->getClassifications(1, $virtual, $curClassification->getSequence());
                    if (!is_null($grayClassifications) && $grayClassifications->count() > 0) {
                        $itGrayClassifications = $grayClassifications->getIterator();
                        while ($itGrayClassifications->valid()) {
                            $curGrayClassification = $itGrayClassifications->current();
                            $grayArray[$curGrayClassification->getUserId()] = $curGrayClassification->getSeed() == '1' ? true : false;
                            $itGrayClassifications->next();
                        }
                    }
                }
            }
        }
        if ($league->getType() != 'sum') {
            if (isset ($grayArray[$curClassification->getUserId()])) {
                $curBean->gray = $grayArray[$curClassification->getUserId()];
            } else {
                $curBean->gray = false;
            }
        } else {
            $curBean->gray = false;
        }
        if (isset($previousArray[$curClassification->getUserId()])) {
            $curBean->previousSeed = $previousArray[$curClassification->getUserId()];
        }
        $curBeans->append($curBean);
        $lastSeed = $curClassification->getSeed();
        $counter++;
        $itClassifications->next();
    }
}
foreach ($curBeans as $curBean) {
    echo $curBean->seed . " - ";
    echo $curBean->gray . " - ";
    echo $curBean->seed . " - ";
    echo $curBean->id . " <br/> ";

}



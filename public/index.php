<?php
use bigwhoop\NBATitleBelt\Game;
use bigwhoop\NBATitleBelt\Team;

require __DIR__ . '/../vendor/autoload.php';

const CACHE_LIFE_TIME = 3600;

date_default_timezone_set('UTC');

use \bigwhoop\NBATitleBelt\Parser\BBReferenceParser;

/** @var Team[] $prevChampByYear */
$prevChampByYear = [
    2008 => new Team('BOS'),
    2009 => new Team('LAL'),
    2010 => new Team('LAL'),
    2011 => new Team('DAL'),
    2012 => new Team('MIA'),
    2013 => new Team('MIA'),
];

$theYear = isset($_GET['season']) ? (int)$_GET['season'] : 2013;

if (!array_key_exists($theYear, $prevChampByYear)) {
    exit("Season $theYear is not available.");
}

$cachePath = __DIR__ . '/../tmp/cache/' . $theYear . '.html';
if (file_exists($cachePath) && filemtime($cachePath) + CACHE_LIFE_TIME > time()) {
    readfile($cachePath);
    exit();
}

$theChamp = $prevChampByYear[$theYear];

$parser = new BBReferenceParser(__DIR__ . '/../data/' . $theYear . '.txt');
$games = $parser->getGames();

usort($games, function(Game $a, Game $b) {
    return $a->getDate() > $b->getDate() ? 1 : -1;
});


$holder = $theChamp;
$stats = [];

/** @var Game[] $gameLog */
$gameLog = [];

$statsTmpl = [
    'games' => 0,
    'wins'  => 0,
    'won_as_challenger'  => 0,
    'won_as_defender'    => 0,
    'losses'  => 0,
    'lost_as_challenger' => 0,
    'lost_as_defender'   => 0,
    'win%'  => 0,
];

foreach ($games as $game) {
    $homeTeam = $game->getHomeTeam();
    $awayTeam = $game->getAwayTeam();
    
    if (!array_key_exists($homeTeam->getName(), $stats)) {
        $stats[$homeTeam->getName()] = $statsTmpl;
    }
    
    if (!array_key_exists($awayTeam->getName(), $stats)) {
        $stats[$awayTeam->getName()] = $statsTmpl;
    }
    
    if (!$game->getHomeTeam()->isSame($holder) && !$game->getAwayTeam()->isSame($holder)) {
        continue;
    }
    
    $gameLog[] = $game;
    
    $winnerName = $game->getWinner()->getName();
    $loserName  = $game->getLoser()->getName();
    
    $stats[$winnerName]['games']++;
    $stats[$winnerName]['wins']++;
    $stats[$loserName]['games']++;
    $stats[$loserName]['losses']++;
    
    foreach ([$winnerName, $loserName] as $teamName) {
        $stats[$teamName]['win%'] = round($stats[$teamName]['wins'] / $stats[$teamName]['games'] * 100, 2);
    }
    
    if ($holder->isSame($game->getWinner())) {
        $stats[$winnerName]['won_as_defender']++;
    } else {
        $stats[$winnerName]['won_as_challenger']++;
    }
    
    if ($holder->isSame($game->getLoser())) {
        $stats[$loserName]['lost_as_defender']++;
    } else {
        $stats[$loserName]['lost_as_challenger']++;
    }
    
    $holder = $game->getWinner();
}

if ($theYear == date('Y')) {
    file_put_contents(__DIR__ . '/leader.json', json_encode(['name' => $holder->getName()]));
}

uasort($stats, function(array $a, array $b) {
    if ($a['wins'] == $b['wins']) {
        return $a['games'] > $b['games'] ? 1 : -1;
    }
    return $a['win%'] > $b['win%'] ? -1 : 1;
});


/**
 * @param Team $team
 * @return string
 */
function getTeamLogoURL(Team $team) {
    return '/img/teams/' . $team->getName() . '.gif';
}

/**
 * @param Team $team
 * @return string
 */
function getTeamLogoImgTag(Team $team) {
    return '<img src="' . getTeamLogoURL($team) . '" alt="' . $team->getName() . '">';
}


ob_start();
?>
<!DOCTYPE html>
<html>
    <head>
        <title>NBA Championship Belt <?= $theYear; ?>/<?= $theYear + 1; ?></title>
        <style>
            * { margin: 0; padding: 0; }
            html, body { width: 100%; height: 100%; }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
            }
            
            #container { margin: 20px; }
            
            h1 { font-weight: normal; font-size: 38px; margin: 0 0 5px 0; }
            h2 { font-weight: normal; font-size: 16px; margin: 16px 0 8px 0; text-transform: uppercase; }
            
            li { margin-left: 20px; }
            a { color: #333; }
            
            .champs p img { vertical-align: middle; }
            .champs p { line-height: 24px; }
            
            .seasons { margin: 0 0 20px 0; }
            .title-winner { font-size: 32px; }
            
            table { border-collapse: collapse; }
            th, td { border: 1px solid #666; padding: 2px 4px; text-align: left; }
            th { background-color: #777; color: #fff; }
            th[colspan] { text-align: center; }
            td img { vertical-align: middle; }
            tr:nth-child(even) { background-color: #f5f5f5; }
            
            .col { float: left; margin: 0 50px 20px 0; }
            .clear { clear: left; }
        </style>
    </head>
    <body>
        <div id="container">
            <h1>NBA Championship Belt <?= $theYear; ?>/<?= $theYear + 1; ?></h1>
            
            <p class="seasons">
                <?php foreach ($prevChampByYear as $year => $champ): ?>
                    <a href="?season=<?= $year; ?>"><?= $year; ?>/<?= $year + 1; ?></a>
                <?php endforeach; ?>
            </p>
            
            <div class="col">
                <h2>Winner of Title Belt</h2>
                <div class="title-winner">
                    <?= getTeamLogoImgTag($holder); ?>
                    <?= $holder->getName(); ?>
                </div>
            </div>
            
            <div class="col">
                <h2>NBA Champs</h2>
                <div class="champs">
                    <p>
                        <?= getTeamLogoImgTag($theChamp); ?> <?= $theChamp->getName(); ?>
                        (<?= $theYear - 1; ?>/<?= $theYear; ?>)
                    </p>
                    <p>
                        <?php if (array_key_exists($theYear + 1, $prevChampByYear)): ?>
                            <?= getTeamLogoImgTag($prevChampByYear[$theYear + 1]); ?> <?= $prevChampByYear[$theYear + 1]->getName(); ?>
                        <?php else: ?>
                            TBD.
                        <?php endif; ?>
                        (<?= $theYear; ?>/<?= $theYear + 1; ?>)
                    </p>
                </div>
            </div>
            
            <div class="col">
                <h2>Rules</h2>
                <ul>
                    <li>It starts with the first game of last season's NBA champion.</li>
                    <li>If the belt holding team gets beat it losses the belt to the other team.</li>
                    <li>No playoffs, the race is over after the regular season.</li>
                </ul>
            </div>
            
            <div class="col">
                <h2>Data Sources</h2>
                <p>
                    Idea: <a href="http://www.reddit.com/r/nba/comments/1pn9t2/can_we_keep_track_of_the_owner_of_the/">/u/hckygod91 on reddit</a><br>
                    Scores: <a href="http://www.basketball-reference.com/">basketball-reference.com</a><br>
                    Logos: <a href="http://www.nba.com/">nba.com</a>
                </p>
            </div>
            
            <div class="clear"></div>
        
            <div class="col">
                <h2>Game Log</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Home Team</th>
                            <th>Score</th>
                            <th>Away Team</th>
                            <th>Winner</th>
                            <th>Streak</th>
                            <th>Wins</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $lastWinner = null; $streak = 0; $wins = []; ?>
                        <?php foreach ($gameLog as $game): ?>
                            <?php
                            $winnerName = $game->getWinner()->getName();
                            if (!array_key_exists($winnerName, $wins)) {
                                $wins[$winnerName] = 0;
                            }
                            $wins[$winnerName]++;
                            if ($lastWinner != $winnerName) {
                                $lastWinner = $winnerName;
                                $streak = 0;
                            }
                            $streak++;
                            ?>
                            <tr>
                                <td><?= $game->getHomeTeam()->getName(); ?></td>
                                <td><?= $game->getScore();; ?></td>
                                <td><?= $game->getAwayTeam()->getName(); ?></td>
                                <td><?= getTeamLogoImgTag($game->getWinner()); ?> <?= $winnerName; ?></td>
                                <td><?= $streak; ?></td>
                                <td><?= $wins[$winnerName]; ?></td>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="col">
                <h2>Stats</h2>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">Rank</th>
                            <th rowspan="2">Team</th>
                            <th rowspan="2">Games</th>
                            <th colspan="3">Wins</th>
                            <th colspan="3">Losses</th>
                            <th rowspan="2">Win %</th>
                        </tr>
                        <tr>
                            <th>Total</th>
                            <th><small>As Challenger</small></th>
                            <th><small>As Defender</small></th>
                            <th>Total</th>
                            <th><small>As Challenger</small></th>
                            <th><small>As Defender</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; $prevWinPercentage = $prevGames = null; ?>
                        <?php foreach ($stats as $teamName => $teamStats): ?>
                            <tr>
                                <td>
                                    <?php
                                    if ($prevWinPercentage !== $teamStats['win%'] || $prevGames !== $teamStats['games']) {
                                        echo $rank . '.';
                                        $prevGames = $teamStats['games'];
                                        $prevWinPercentage = $teamStats['win%'];
                                    }
                                    $rank++;
                                    ?>
                                </td>
                                <td><?= getTeamLogoImgTag(new Team($teamName)); ?> <?= $teamName; ?></td>
                                <td><?= $teamStats['games']; ?></td>
                                <td><?= $teamStats['wins']; ?></td>
                                <td><?= $teamStats['won_as_challenger']; ?></td>
                                <td><?= $teamStats['won_as_defender']; ?></td>
                                <td><?= $teamStats['losses']; ?></td>
                                <td><?= $teamStats['lost_as_challenger']; ?></td>
                                <td><?= $teamStats['lost_as_defender']; ?></td>
                                <td><?= $teamStats['win%']; ?>%</td>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="clear"></div>
        </div>
    </body>
</html>
<?php
$content = ob_get_flush();
file_put_contents($cachePath, $content);
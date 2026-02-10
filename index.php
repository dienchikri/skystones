<?php if (!ob_start("ob_gzhandler")) ob_start();

session_start();
if (!isset($_SESSION['bot_mode'])) {
    $_SESSION['bot_mode'] = false;


}


class Stone
{
    public int $top;
    public int $right;
    public int $bottom;
    public int $left;
    public string $owner;
    public string $image;

    public function __construct(string $image, string $owner)
    {
        global $cardStats;

        $this->image = $image;
        $this->owner = $owner;
        $this->top = $cardStats[$image]['top'];
        $this->right = $cardStats[$image]['right'];
        $this->bottom = $cardStats[$image]['bottom'];
        $this->left = $cardStats[$image]['left'];
    }
}

class Board
{
    public array $grid;
    private int $size = 3;

    public function __construct()
    {
        $this->grid = array_fill(0, $this->size, array_fill(0, $this->size, null));
    }

    public function placeStone(int $x, int $y, Stone $stone): bool
    {
        if ($this->grid[$x][$y] !== null) {
            return false;
        }
        $this->grid[$x][$y] = $stone;
        $this->checkcaptures($x, $y, $stone);
        return true;
    }


    //geef door als het grid vol is
    public function isFull(): bool
    {
        foreach ($this->grid as $row) {
            foreach ($row as $cell) {
                if ($cell === null) {
                    return false;
                }
            }
        }
        return true;
    }

    // als de grid vol is vertelt het wie wint
    public function getwinner(): string
    {
        $count = ['A' => 0, 'B' => 0];
        foreach ($this->grid as $row) {
            foreach ($row as $cell) {
                if ($cell !== null) {
                    $count[$cell->owner]++;
                }
            }
        }
        return ($count['A'] > $count['B']) ? 'A' : 'B';
    }


    public function countOwnedStones(): array
    {
        $count = ['A' => 0, 'B' => 0];
        foreach ($this->grid as $row) {
            foreach ($row as $cell) {
                if ($cell !== null) {
                    $count[$cell->owner]++;
                }
            }
        }
        return $count;
    }

    private function checkcaptures(int $x, int $y, Stone $stone): void
    {
        $directions = [[-1, 0, 'top', 'bottom'], [1, 0, 'bottom', 'top'], [0, -1, 'left', 'right'], [0, 1, 'right', 'left']];
        foreach ($directions as [$dx, $dy, $thisSide, $opponentSide]) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            if ($nx >= 0 && $nx < $this->size && $ny >= 0 && $ny < $this->size) {
                $neighbor = $this->grid[$nx][$ny];
                if ($neighbor && $neighbor->owner !== $stone->owner) {
                    if ($stone->{$thisSide} > $neighbor->{$opponentSide}) {
                        $neighbor->owner = $stone->owner;
                    }
                }
            }
        }
    }


}

function generateHand(string $owner): array
{
    global $cardStats;

    // Define probabilities for each card (must add up to 100 or be proportionally accurate)
    $probabilities = [
        "archer" => 10,
        "arkeyan_jouster" => 8,
        "axe" => 8,
        "blaster_troll" => 7,
        "chompy_bot" => 6,

        "conquertron" => 1,
        "cyclops" => 8,
        "drow" => 9,
        "inhuman_shield" => 6,
        "jawbreaker" => 7,
        "mace_troll" => 8,
        "sniper_troll" => 7,
        "spider" => 10,
        "boma" => 6,
        "rsnipe" => 6,
        "snipe" => 7,
        "arkeyan_sniper" => 6,

        "spell_punk" => 5,
        "general_bomb" => 6,
        "chompy_pod" => 8,
        "crystal_golem" => 4,
        "armored_chompy" => 7,
        "brock" => 4,
    ];

    // Create a weighted list
    $weightedCards = [];
    foreach ($probabilities as $card => $probability) {
        for ($i = 0; $i < $probability; $i++) {
            $weightedCards[] = $card;
        }
    }

    $totalStatsTarget = 70;
    $hand = [];
    $pickedCards = [];
    $currentTotal = 0;

    $attempts = 0;

    while ($currentTotal < $totalStatsTarget && count($hand) <= 6) {
        if ($attempts > 1000) break; //zorgt ervoor dat infinite loop niet gebeurt

        $randomCard = $weightedCards[array_rand($weightedCards)];
        $statsSum = array_sum($cardStats[$randomCard]);

        if (!in_array($randomCard, $pickedCards) &&
            ($currentTotal + $statsSum) <= $totalStatsTarget) {
            $hand[] = new Stone($randomCard, $owner);
            $pickedCards[] = $randomCard;
            $currentTotal += $statsSum;


        }
        $attempts++;
    }

    return $hand;
}

function botPlay()
{
    $bot_hand = $_SESSION['hands']['B'];
    $board = $_SESSION['board'];

    if (empty($bot_hand)) return;

    $best_moves = [];

    // Step 1: Try to find capture opportunities
    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
            $stone = $board->grid[$i][$j];
            if ($stone && $stone->owner === 'A') {
                $directions = [
                    [-1, 0, 'top', 'bottom'],
                    [1, 0, 'bottom', 'top'],
                    [0, -1, 'left', 'right'],
                    [0, 1, 'right', 'left'],
                ];

                foreach ($directions as [$dx, $dy, $a_side, $b_side]) {
                    $x = $i + $dx;
                    $y = $j + $dy;

                    if ($x >= 0 && $x < 3 && $y >= 0 && $y < 3 && $board->grid[$x][$y] === null) {
                        foreach ($bot_hand as $index => $bot_stone) {
                            if ($bot_stone->$b_side > $stone->$a_side) {
                                $best_moves[] = [
                                    'x' => $x,
                                    'y' => $y,
                                    'index' => $index,
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    // Step 2: Smarter fallback if no captures
    if (!empty($best_moves)) {
        $move = $best_moves[array_rand($best_moves)];
    } else {
        $smart_moves = [];

        $position_weights = [
            [4, 3, 4],
            [3, 2, 3],
            [4, 3, 4],
        ]; // center=4, corners=3, edges=2

        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                if ($board->grid[$i][$j] !== null) continue;

                foreach ($bot_hand as $index => $card) {
                    $adjacent_opponents = 0;
                    $adjacent_friends = 0;

                    // Check adjacency
                    $adjacents = [
                        [$i - 1, $j],
                        [$i + 1, $j],
                        [$i, $j - 1],
                        [$i, $j + 1],
                    ];

                    foreach ($adjacents as [$x, $y]) {
                        if ($x >= 0 && $x < 3 && $y >= 0 && $y < 3) {
                            $adjacent = $board->grid[$x][$y];
                            if ($adjacent && $adjacent->owner === 'A') $adjacent_opponents++;
                            if ($adjacent && $adjacent->owner === 'B') $adjacent_friends++;
                        }
                    }

                    $total_strength = $card->top + $card->bottom + $card->left + $card->right;

                    $score = (
                        $position_weights[$i][$j] * 2 +
                        $total_strength +
                        ($adjacent_opponents * 2) -
                        ($adjacent_friends)
                    );

                    $smart_moves[] = [
                        'x' => $i,
                        'y' => $j,
                        'index' => $index,
                        'score' => $score,
                    ];
                }
            }
        }

        if (!empty($smart_moves)) {
            usort($smart_moves, fn($a, $b) => $b['score'] <=> $a['score']);
            $move = $smart_moves[0];
        } else {
            return; // No valid moves
        }
    }

    // Play the chosen card
    $stone = $bot_hand[$move['index']];
    unset($bot_hand[$move['index']]);
    $bot_hand = array_values($bot_hand);
    $_SESSION['hands']['B'] = $bot_hand;

    if ($board->placeStone($move['x'], $move['y'], $stone)) {
        $_SESSION['turn'] = 'A';

        if ($board->isFull()) {
            $_SESSION['winner'] = $board->getWinner();
        }
    }

    $_SESSION['board'] = $board;
}


// card names en de stats erbij
$cardStats = [
    "archer" => ["top" => 3, "right" => 2, "bottom" => 3, "left" => 2],
    "arkeyan_jouster" => ["top" => 4, "right" => 3, "bottom" => 2, "left" => 2],
    "axe" => ["top" => 4, "right" => 4, "bottom" => 3, "left" => 2],
    "blaster_troll" => ["top" => 2, "right" => 3, "bottom" => 2, "left" => 3],
    "chompy_bot" => ["top" => 3, "right" => 4, "bottom" => 3, "left" => 1],
    "conquertron" => ["top" => 5, "right" => 4, "bottom" => 5, "left" => 4],  // strongest card
    "cyclops" => ["top" => 3, "right" => 3, "bottom" => 2, "left" => 2],
    "drow" => ["top" => 3, "right" => 4, "bottom" => 2, "left" => 2],
    "inhuman_shield" => ["top" => 2, "right" => 3, "bottom" => 4, "left" => 2],
    "jawbreaker" => ["top" => 4, "right" => 2, "bottom" => 3, "left" => 2],
    "mace_troll" => ["top" => 2, "right" => 3, "bottom" => 3, "left" => 4],
    "sniper_troll" => ["top" => 3, "right" => 2, "bottom" => 2, "left" => 3],
    "spider" => ["top" => 2, "right" => 2, "bottom" => 2, "left" => 4],
    "boma" => ["top" => 3, "right" => 3, "bottom" => 2, "left" => 3],
    "rsnipe" => ["top" => 3, "right" => 2, "bottom" => 4, "left" => 2],
    "snipe" => ["top" => 3, "right" => 2, "bottom" => 3, "left" => 3],
    "arkeyan_sniper" => ["top" => 4, "right" => 3, "bottom" => 2, "left" => 1],


//
//    "spell_punk" => 5,
//    "general_bomb" => 6,
//    "chompy_pod" => 8,
//    "crystal_golem" => 4,
//    "armored_chompy" => 7,
//    "brock" => 4,

    "spell_punk" => ["top" => 1, "right" => 4, "bottom" => 2, "left" => 3],
    "general_bomb" => ["top" => 1, "right" => 3, "bottom" => 3, "left" => 3],
    "crystal_golem" => ["top" => 4, "right" => 2, "bottom" => 3, "left" => 2],
    "chompy_pod" => ["top" => 5, "right" => 2, "bottom" => 1, "left" => 2],
    "armored_chompy" => ["top" => 2, "right" => 2, "bottom" => 2, "left" => 4],
    "brock" => ["top" => 4, "right" => 3, "bottom" => 2, "left" => 3]
];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_bot'])) {
    $_SESSION['bot_mode'] = !$_SESSION['bot_mode'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

//voor de reset knop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $bot_mode = $_SESSION['bot_mode'] ?? false;
    session_destroy();
    session_start();
    $_SESSION['bot_mode'] = $bot_mode;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;


}
$cardCount = ['A' => 0, 'B' => 0];
if (isset($_SESSION['board'])) {
    foreach ($_SESSION['board']->grid as $row) {
        foreach ($row as $cell) {
            if ($cell !== null) {
                $cardCount[$cell->owner]++;
            }
        }
    }
}


if (!isset($_SESSION['board'])) {
    $_SESSION['board'] = new Board();
    $_SESSION['turn'] = 'A';
    $_SESSION['hands'] = ['A' => generateHand('A'), 'B' => generateHand('B')];
    $_SESSION['selectedCard'] = null;
}

$board = $_SESSION['board'];
$turn = $_SESSION['turn'];
$hands = $_SESSION['hands'];
$selectedCard = $_SESSION['selectedCard'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select'])) {
        // Player selects a card
        $selectedCard = (int)$_POST['select'];
        $_SESSION['selectedCard'] = $selectedCard;
    } elseif (isset($_POST['x']) && isset($_POST['y']) && $selectedCard !== null) {
        // Player places a card on the board
        $x = (int)$_POST['x'];
        $y = (int)$_POST['y'];
        $stone = $hands[$turn][$selectedCard];

        if ($board->placeStone($x, $y, $stone)) {
            unset($hands[$turn][$selectedCard]);
            $_SESSION['hands'] = $hands;
            $_SESSION['selectedCard'] = null;

            // Switch turn
            $_SESSION['turn'] = ($turn === 'A') ? 'B' : 'A';
        }

        // Check if the board is full, then determine the winner
        if ($board->isFull()) {
            $winner = $board->getWinner();
            $_SESSION['winner'] = $winner;
        }

        // Bot's Turn: If it's Bot's turn (Player B)
        if ($_SESSION['bot_mode'] && $_SESSION['turn'] === 'B') {
            botPlay(); // Let the bot play its turn

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }


}
$stoneCounts = $board->countOwnedStones();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skystones</title>
    <style>
        :root {
            --gap: 1vh; /* Smaller spacing for tighter layouts */
            --card-size: min(8vw, 30vh); /* Scale based on the smaller of width or height */
            --font-base: min(1.5vw, 1.5vh); /* Keep text readable */
            --border-radius: 0.5vh;
        }


        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;

            font-size: var(--font-base);

            display: flex;
            flex-direction: column;
        }


        .game-container {
            flex: 1;
            overflow: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: var(--gap);
            padding: 1vh;
            box-sizing: border-box;
        }

        .handa, .handb {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--gap);
            width: calc(var(--card-size) * 2 + var(--gap));
        }

        .board {
            display: grid;
            grid-template-columns: repeat(3, var(--card-size));
            grid-template-rows: repeat(3, var(--card-size));
            gap: var(--gap);
            justify-content: center;
            background-image: url("dark-picton-blue-abstract-creative-background-design_851755-197249.avif");

            background-size: cover;
            background-position: center center; /* Center the image */
            background-repeat: no-repeat;


            padding: 1vw;
            border-radius: 2vw;
            margin-left: 2vw;
            margin-right: 2vw;
            max-width: 90vw;

        }


        .cell {
            width: var(--card-size);
            height: var(--card-size);
            border: 0.2vw solid white;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            background-image: url("bluemist.jpg");
            background-size: cover;
            background-position: center;
            border-radius: 1vw;
        }


        .cell img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .cell div {
            position: absolute;
            font-size: 0.9vw;
            color: white;
            background-color: black;
            font-weight: bold;
            padding: 0.2vw;
            border-radius: 0.3vw;
        }

        .top {
            top: 0.5vw;
            left: 50%;
            transform: translateX(-50%);
        }

        .bottom {
            bottom: 0.5vw;
            left: 50%;
            transform: translateX(-50%);
        }

        .left {
            left: 0.5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        .right {
            right: 0.5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        @font-face {
            src: url('pincoyaBlack.otf') format('truetype');
        }

        .card {
            width: var(--card-size);
            height: var(--card-size);
            position: relative;
            border-radius: var(--border-radius);
            box-sizing: border-box;
        }

        .card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .card .top, .card .left, .card .right, .card .bottom {
            position: absolute;
            color: white;
            font-size: 1vw;
            font-weight: bold;
            background-color: black;
            padding: 0.1vw;
            border-radius: 0.2vw;
        }

        .card .top {
            top: 0.5vw;
            left: 50%;
            transform: translateX(-50%);
        }

        .card .left {
            left: 0.5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        .card .right {
            right: 0.5vw;
            top: 50%;
            transform: translateY(-50%);
        }

        .card .bottom {
            bottom: 0.5vw;
            left: 50%;
            transform: translateX(-50%);
        }

        .header {
            background-image: url("darg.avif");
            background-repeat: no-repeat;
            background-size: cover;
            color: white;
            text-align: center;
            padding: 2vw 0;
            font-size: 3vw;
            letter-spacing: 0.3vw;
            max-width: 60vw;
            width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 2vw;
            flex-wrap: wrap;
            height: 6vw;
        }

        .header h1 {
            margin: 0 auto;
            font-size: 3vw;
        }

        .header p {
            margin: 1vw;
            font-size: 2vw;
        }

        .header .player-counter {
            display: flex;
            align-items: center;
            gap: 1.5vw;
        }

        .selected {
            transform: scale(1.3);
            z-index: 1;
        }

        .blue {
            border: 0.3vw solid blue;
        }

        .red {
            border: 0.3vw solid red;
        }

        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10vw, 1fr));
            gap: 1.5vw;
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .button-grid button {
            padding: 1vw 2vw;
            font-size: 1.5vw;
            background-color: darkblue;
            color: white;
            border: none;
            border-radius: 0.5vw;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .button-grid button:hover {
            background-color: #999;
        }

        .wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh; /* Full viewport height */
            width: 100vw; /* Full viewport width */
            background: url('bluewater.jpeg') no-repeat center center;
            background-size: cover;
        }

        .content {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;

        }


        }
    </style>

</head>
<body>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skystones</title>
</head>
<body>
<div class="wrapper">
    <div class="content">
        <header class="header" style="text-align: center; position: relative;">

            <div class="acounter" style="
        width: 7vw; height: 3vw;
        background: black; color: white;
        display: flex; align-items: center; justify-content: center;
        float: left;
        border: 0.5vw solid darkblue;
        border-radius: 1vw;
        margin-left: 4vw;
    "> <?= $stoneCounts['A'] ?>
            </div>

            <h1 style="margin: 0;">SKYSTONES <br>

                <?php if (!isset($_SESSION['winner'])): ?>
                    <p>
                        Player <?= $_SESSION['turn'] ?>'s turn
                    </p>
                <?php else: ?>
                    <p>
                        Winner: <?= $_SESSION['winner'] ?>
                    </p>
                <?php endif; ?>

            </h1>

            <div class="bcounter" style="
        width: 7vw; height: 3vw;
        background: black; color: white;
        display: flex; align-items: center; justify-content: center;
        float: right;
        border: 0.5vw solid red;
        border-radius: 1vw;
        margin-right:  4vw;
    "> <?= $stoneCounts['B'] ?>
            </div>

        </header>
        <div class="game-container">

            <div class="handa">
                <?php foreach ($hands['A'] as $index => $stone): ?>
                    <form method="post">
                        <button type="submit" name="select" value="<?= $index ?>"
                                class="card <?= ($selectedCard === $index && $turn === 'A') ? 'blue selected' : 'blue' ?>">
                            <img src="skystones/<?= $stone->image ?>.png" alt="<?= $stone->image ?>">
                            <div class="top"><?= $stone->top ?></div>
                            <div class="left"><?= $stone->left ?></div>
                            <div class="right"><?= $stone->right ?></div>
                            <div class="bottom"><?= $stone->bottom ?></div>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>

            <div class="board">
                <?php for ($x = 0; $x < 3; $x++): ?>
                    <?php for ($y = 0; $y < 3; $y++): ?>
                        <form method="post">
                            <input type="hidden" name="x" value="<?= $x ?>">
                            <input type="hidden" name="y" value="<?= $y ?>">
                            <button class="cell <?= $board->grid[$x][$y] ? ($board->grid[$x][$y]->owner === 'A' ? 'blue' : 'red') : '' ?>"
                                    type="submit" <?= $board->grid[$x][$y] ? 'disabled' : '' ?>>
                                <?php if ($board->grid[$x][$y]): ?>
                                    <img src="skystones/<?= $board->grid[$x][$y]->image ?>.png"
                                         alt="<?= $board->grid[$x][$y]->image ?>">
                                    <div class="top"><?= $board->grid[$x][$y]->top ?></div>
                                    <div class="left"><?= $board->grid[$x][$y]->left ?></div>
                                    <div class="right"><?= $board->grid[$x][$y]->right ?></div>
                                    <div class="bottom"><?= $board->grid[$x][$y]->bottom ?></div>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>


            <div class="handb">
                <?php foreach ($hands['B'] as $index => $stone): ?>
                    <form method="post">
                        <button type="submit" name="select" value="<?= $index ?>"
                                class="card <?= ($selectedCard === $index && $turn === 'B') ? 'red selected' : 'red' ?>">
                            <img src="skystones/<?= $stone->image ?>.png" alt="<?= $stone->image ?>">
                            <div class="top"><?= $stone->top ?></div>
                            <div class="left"><?= $stone->left ?></div>
                            <div class="right"><?= $stone->right ?></div>
                            <div class="bottom"><?= $stone->bottom ?></div>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>


        <div class="button-grid">
            <form method="post" class="bot">
                <button type="submit" name="toggle_bot">
                    <?= $_SESSION['bot_mode'] ? 'Disable Bot' : 'Enable Bot' ?>
                </button>
            </form>

            <form method="post" class="resets">
                <button type="submit" name="reset">Reset Game</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

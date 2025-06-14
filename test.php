<?php

include(__DIR__ . "/Mahjong.php");

$table = new GameTable();
$table->init(function($prompt, $player_idx, $player_name) {
    echo $prompt . "\n";
    $ret = readline("TO Player {$player_name} > ");
    readline_add_history($ret);
    return $ret;
});

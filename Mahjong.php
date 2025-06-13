<?php

class Mahjong
{
    // 144 張牌
    // 0-35:    萬子
    // 36-71:   筒子
    // 72-107:  條子
    // 108-135: 字牌
    // 136-143: 花牌(春夏秋冬梅蘭菊竹)
    public static function mapToWord($idx)
    {
        $n_map = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];
        if ($idx < 36) {
            $type = '萬';
        } else if ($idx < 72) {
            $type = '筒';
        } else if ($idx < 108) {
            $type = '條';
        }
        if ($idx < 108) {
            return "{$n_map[floor(($idx % 36) / 4)]}{$type}";
        }
        if ($idx < 136) {
            $n_map = ['東風', '南風', '西風', '北風', '紅中', '發財', '白板'];
            return $n_map[floor(($idx - 108) / 4)];
        }
        $n_map = ['春', '夏', '秋', '冬', '梅', '蘭', '菊', '竹'];
        return $n_map[$idx - 136];
    }

    public static function getRandomTileSet()
    {
        // $tile_set = range(0, 143);
        $tile_set = range(0, 135); // 不發花牌
        // 統一轉成一樣的牌
        $tile_set = array_map(function($t) {
            if ($t < 136) {
                return intval(floor($t / 4) * 4);
            }
            return $t; // 花牌不變
        }, $tile_set);
        shuffle($tile_set);
        return $tile_set;
    }

    public static function printTile($set)
    {
        return implode(',', array_map(function ($idx) {
            return self::mapToWord($idx);
        }, $set));
    }

    public static function getTileID($word)
    {
        $n_map = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];
        if (preg_match('/^([一二三四五六七八九])([萬筒條])$/u', $word, $matches)) {
            return array_search($matches[1], $n_map) * 4 + array_search($matches[2], ['萬', '筒', '條']) * 36;
        }
        $n_map = ['東風', '南風', '西風', '北風', '紅中', '發財', '白板'];
        if (in_array($word, $n_map)) {
            return 108 + array_search($word, $n_map) * 4;
        }
        $n_map = ['春', '夏', '秋', '冬', '梅', '蘭', '菊', '竹'];
        if (in_array($word, $n_map)) {
            return 136 + array_search($word, $n_map);
        }
        return false;
    }

    public static function can暗槓($set)
    {
        $count = array_count_values($set);
        foreach ($count as $tile => $num) {
            if ($num >= 4) {
                return $tile;
            }
        }
        return false;
    }
}

class GameTable
{
    protected $tile_set;
    protected $players = [];
    protected $public_events = [];
    protected $player_names = ['聶小倩', '祝英台', '白素貞', '花木蘭'];
    protected $last_throw_tile = null;
    protected $last_throw_player = null;

    public function getInitMessage($player_idx)
    {
        if (true == $this->players[$player_idx]['init']) {
            return '輪到你了';
        }    
        $this->players[$player_idx]['init'] = true;
        return sprintf("你是一個台灣十六張麻將的玩家，你是 %s ，你的手牌是 %s",
            $this->player_names[$player_idx],
            Mahjong::printTile($this->players[$player_idx]['hand']),
        );
    }

    public function init()
    {
        $this->tile_set = Mahjong::getRandomTileSet();

        // 洗牌
        for ($player = 0; $player < 4; $player++) {
            $this->players[$player] = [
                'hand' => array_slice($this->tile_set, 0, 16),
                'door' => [],
                'saw_event_id' => 0,
                'init' => false,
                'drawn_tile' => null,
            ];
            sort($this->players[$player]['hand']);
            $this->tile_set = array_slice($this->tile_set, 16);
            // 補花
            while ($this->players[$player]['hand'][15] >= 136) {
                $this->players[$player]['door'][] = array_pop($this->players[$player]['hand']);
                $this->players[$player]['hand'][] = array_shift($this->tile_set);
                sort($this->players[$player]['hand']);
            }
        }

        // 開打
        $players = ['聶小倩', '祝英台', '白素貞', '花木蘭'];

        // 顯示四家的牌
        for ($i = 0; $i < 4; $i++) {
            echo $players[$i] . "：";
            printf("%s\n",
                Mahjong::printTile($this->players[$i]['hand'])
            );
        }

        // 先檢查是否能夠槓
        foreach ($this->players as $player_idx => $player_data) {
            $kan_tile = Mahjong::can暗槓($player_data['hand']);
            if ($kan_tile !== false) {
                echo "TO: " . $players[$player_idx] . "\n";

                echo $this->getInitMessage($player_idx);
                $allow = [];
                $allow[] = ['暗槓'];
                $input = $this->parseInput($allow, $player_idx);
            }
        }

        $action_stack = [];
        $playing_player = -1;

        while (true) {
            if (count($action_stack)) { // 有人可以吃碰胡槓
                uasort($action_stack, function($a, $b) {
                    // 有胡最優先
                    if (in_array('胡', array_column($a, 0))) {
                        return -1; // a 有胡，b 沒有胡
                    }
                    if (in_array('胡', array_column($b, 0))) {
                        return 1; // b 有胡，a 沒有胡
                    }

                    // 有碰次優先
                    if (in_array('碰', array_column($a, 0))) {
                        return -1; // a 有碰，b 沒有碰
                    }
                    if (in_array('碰', array_column($b, 0))) {
                        return 1; // b 有碰，a 沒有碰
                    }

                    return 0; // 只剩下可以吃的人
                });
                $playing_player = key($action_stack);
                $allow = $action_stack[$playing_player];
                $allow[] = [
                    '放棄',
                    "放棄吃碰胡槓，請回答 {\"放棄\":true}",
                ];
                unset($action_stack[$playing_player]); // 移除已經處理的玩家
                $your_event = '';
            } else {
                // 摸牌
                $playing_player = ($playing_player + 1) % 4; // 換下一個玩家
                $this->players[$playing_player]['drawn_tile'] = array_shift($this->tile_set);
                $allow = $this->checkAllowAction($playing_player);
                $your_event = sprintf("，你摸到的牌是 %s",
                    Mahjong::mapToWord($this->players[$playing_player]['drawn_tile'])
                );
            }

            $message = $this->getInitMessage($playing_player);
            if (count($this->public_events) > 0) {
                for ($idx = $this->players[$playing_player]['saw_event_id']; $idx < count($this->public_events); $idx++) {
                    $message .= "，" . self::changeName($playing_player, $this->public_events[$idx]);
                }
                $this->players[$playing_player]['saw_event_id'] = count($this->public_events) - 1;
            }
            $message .= $your_event;
            echo $message;

            //error_log(json_encode($allow, JSON_UNESCAPED_UNICODE));

            // 處理輸入
            while (true) {
                $input = $this->parseInput($allow, $playing_player);

                if ($input->丟 ?? false) { // 打牌
                    $hands = $this->players[$playing_player]['hand'];
                    $hands[] = $this->players[$playing_player]['drawn_tile'];
                    $tile_id = Mahjong::getTileID($input->丟);
                    $idx = array_search($tile_id, $hands);
                    if ($idx === false) {
                        echo "你沒有 {$input->丟} 這張牌，請重新輸入。\n";
                        continue; // 重新輸入
                    }
                    unset($hands[$idx]);
                    $hands = array_values($hands);
                    $this->players[$playing_player]['hand'] = $hands;
                    $this->players[$playing_player]['drawn_tile'] = null; // 打牌後沒有摸牌
                    $this->public_events[] = sprintf("\$%d打出了%s",
                        $playing_player,
                        $input->丟,
                    );
                    $this->last_throw_tile = $tile_id; // 記錄最後打出的牌
                    $this->last_throw_player = $playing_player; // 記錄最後打牌的玩家
                    $this->players[$playing_player]['saw_event_id'] = count($this->public_events) - 1;
                    break;
                }

                print_r($input);
                exit;
            }

            // 先檢查有沒有人可以胡
            for ($i = 1; $i < 4; $i ++) {
                $checking_player = ($playing_player + $i) % 4;
                $throw_tile = $this->last_throw_tile;
                if (!is_null($throw_tile) && $this->check胡牌(array_merge($this->players[$checking_player]['hand'], [$throw_tile]))) {
                    $action_stack[$checking_player] = $action_stack[$checking_player] ?? [];
                    $action_stack[$checking_player][] = [
                        '胡',
                        "胡牌，請回答 {\"胡\":true}",
                    ];
                }
            }

            // 再檢查有沒有人可以碰
            for ($i = 1; $i < 4; $i ++) {
                $checking_player = ($playing_player + $i) % 4;
                $throw_tile = $this->last_throw_tile;
                if (!is_null($throw_tile) && in_array($throw_tile, $this->players[$checking_player]['hand'])) {
                    $count = array_count_values($this->players[$checking_player]['hand']);
                    if (isset($count[$throw_tile]) && $count[$throw_tile] >= 2) {
                        $action_stack[$checking_player] = $action_stack[$checking_player] ?? [];
                        $action_stack[$checking_player][] = [
                            '碰',
                            "碰牌，請回答 {\"碰\":true}",
                        ];
                    }
                    if ($count[$throw_tile] >= 3) {
                        $action_stack[$checking_player] = $action_stack[$checking_player] ?? [];
                        $action_stack[$checking_player][] = [
                            '槓',
                            "槓牌，請回答 {\"槓\":true}",
                        ]; // 可以槓牌
                    }
                }
            }

            // 檢查下家可不可以吃
            $checking_player = ($playing_player + 1) % 4;
            $throw_tile = $this->last_throw_tile;
            if (!is_null($throw_tile) && $throw_tile < 108 && ($throw_tile % 4) == 0) { // 只有萬、筒、條可以吃牌
                $base = floor($throw_tile / 36) * 36;
                $drown_number = ($throw_tile % 36) / 4;
                for ($start_number = max(0, $drown_number - 2); $start_number < min(7, $drown_number + 3); $start_number ++) {
                    for ($i = 0; $i < 3; $i ++) {
                        if ($start_number + $i == $drown_number) {
                            continue; // 跳過自己摸到的牌
                        }
                        if (!in_array($base + ($start_number + $i) * 4, $this->players[$checking_player]['hand'])) {
                            continue 2; // 如果手上沒有這張牌，則不能吃
                        }
                    }

                    $action_stack[$checking_player] = $action_stack[$checking_player] ?? [];
                    $action_stack[$checking_player][] = [
                        '吃',
                        sprintf("若要吃 %s,%s,%s，請回答 {\"吃\":\"%s\"}",
                            Mahjong::mapToWord($base + ($start_number) * 4),
                            Mahjong::mapToWord($base + ($start_number + 1) * 4),
                            Mahjong::mapToWord($base + ($start_number + 2) * 4),
                            Mahjong::mapToWord($base + ($start_number) * 4),
                        ),
                    ];
                }
            }
        }
    }

    public function changeName($player_id, $message)
    {
        $message = str_replace('$' . (($player_id + 1) % 4), "下家{$this->player_names[($player_id + 1) % 4]}", $message);
        $message = str_replace('$' . (($player_id + 2) % 4), "對家{$this->player_names[($player_id + 2) % 4]}", $message);
        $message = str_replace('$' . (($player_id + 3) % 4), "上家{$this->player_names[($player_id + 3) % 4]}", $message);
        return $message;
    }

    public function checkAllowAction($check_player_id, $throw_player_id = null, $throw_drawn_tile = null)
    {
        $allow = [];
        // 如果摸一張牌，基本上可以打一張牌
        if (!is_null($this->players[$check_player_id]['drawn_tile'])) {
            $allow[] = [
                '丟',
                "打出一張牌，請回答 {\"丟\":\"\$牌名\"}"
            ] ; // 可以打出一張牌
        }

        // 如果手上有三張相同，又摸到一張一樣，允許可以暗槓
        if (count(array_filter($this->players[$check_player_id]['hand'], function($tile) use ($check_player_id) {
            return $tile == $this->players[$check_player_id]['drawn_tile'];
            })) >= 3) {
            $allow[] = [
                '暗槓',
                "暗槓牌，請回答 {\"暗槓\":true}",
            ]; // 可以暗槓
        }

        // 檢查是否可以碰牌
        if (!is_null($throw_player_id) && !is_null($throw_drawn_tile)) {
            $count = array_count_values($this->players[$check_player_id]['hand']);
            if (isset($count[$throw_tile]) && $count[$throw_tile] >= 2) {
                $allow[] = [
                    '碰',
                    "碰牌，請回答 {\"碰\":true}",
                ]; // 可以碰牌
            }
            // 檢查是否可以槓牌
            if ($count[$throw_tile] >= 3) {
                $allow[] = [
                    '槓',
                    "槓牌，請回答 {\"槓\":true}",
                ];; // 可以槓牌
            }
        }

        // 檢查是否可以吃牌
        while (!is_null($throw_player_id) and ($throw_player_id + 1) % 4 == $check_player_id) {
            if ($throw_drawn_tile >= 108) { // 只有萬、筒、條可以吃牌
                break;
            }

            $base = 36 * floor($throw_drawn_tile / 36);
            // 檢查是否可以吃牌
            $drown_number = $throw_drawn_tile % 36 / 4;
            $eat_terms = [];
            for ($start_number = max(0, $drown_number - 2); $start_number < min(7, $drown_number + 3); $start_number ++) {
                for ($i = 0; $i < 3; $i ++) {
                    if ($start_number + $i == $drown_number) {
                        continue; // 跳過自己摸到的牌
                    }
                    if (!in_array($base + ($start_number + $i) * 4, $this->players[$check_player_id]['hand'])) {
                        continue 2; // 如果手上沒有這張牌，則不能吃
                    }
                }
                $allow[] = [
                    '吃',
                    sprintf("若要吃 %s,%s,%s，請回答 {\"吃\":\"%s\"}",
                        Mahjong::mapToWord($base + ($start_number) * 4),
                        Mahjong::mapToWord($base + ($start_number + 1) * 4),
                        Mahjong::mapToWord($base + ($start_number + 2) * 4),
                        Mahjong::mapToWord($base + ($start_number) * 4),
                    ),
                ]; // 可以吃牌
            }
            break;
        }

        if (!is_null($throw_drawn_tile)) {
            if ($this->check胡牌(array_merge($this->players[$check_player_id]['hand'], [$throw_drawn_tile]))) {
                $allow[] = [
                    '胡',
                    "胡牌，請回答 {\"胡\":true}",
                ]; // 可以胡牌
            }
        } else {
            if ($this->check胡牌(array_merge($this->players[$check_player_id]['hand'], [$this->players[$check_player_id]['drawn_tile']]))) {
                $allow[] = [
                    '自摸',
                    "自摸胡牌，請回答 {\"自摸\":true}",
                ]; // 可以胡牌
            }
        }
        return $allow;
    }

    public function check胡牌($input_tiles)
    {
        sort($input_tiles);
        $tile_count = array_count_values($input_tiles);

        foreach ($tile_count as $tile => $count) {
            if ($count < 2) {
                continue;
            }
            // 拿有兩張以上的當作麻將
            $remaining_tiles = $input_tiles;
            $remove_count = 0;
            $remaining_tiles = array_values(array_filter($remaining_tiles, function($t) use ($tile, &$remove_count) {
                if ($t == $tile && $remove_count < 2) {
                    $remove_count++;
                    return false; // 移除兩張
                }
                return true; // 保留其他牌
            })); 

            while ($remaining_tiles) {
                // 檢查最前面三個牌是否是順子或刻子
                if ($remaining_tiles[0] == $remaining_tiles[1] && $remaining_tiles[1] == $remaining_tiles[2]) {
                    // 刻子
                    $remaining_tiles = array_values(array_slice($remaining_tiles, 3));
                    continue;
                }

                // 檢查是否是順子，並且要同花色
                if ($remaining_tiles[0] >= 108) {
                    // 字牌不能順子
                    break;
                }
                if (floor($remaining_tiles[0] / 36) != floor($remaining_tiles[1] / 36) ||
                    floor($remaining_tiles[1] / 36) != floor($remaining_tiles[2] / 36)) {
                    // 不同花色
                    break;
                }
                if ($remaining_tiles[1] == $remaining_tiles[0] + 4 && $remaining_tiles[2] == $remaining_tiles[0] + 8) {
                    // 順子
                    $remaining_tiles = array_slice($remaining_tiles, 3);
                    continue;
                }
                break;
            }
            if (count($remaining_tiles) == 0) {
                return true; // 胡牌成功
            }
        }
        return false;
    }

    public function parseInput($allow, $player_idx)
    {
        while (true) {
            $message = "，請問您要做什麼？";
            foreach ($allow as $term) {
                list($action, $description) = $term;
                $message .= "\n* {$description}";
            }
            $message .= "\n(不需要分析為什麼，如果你想要垃圾話，可以透過 \"talk\":\"垃圾話內容\" 補充)";
            echo $message . "\n";

            $ret = readline("TO Player " . $this->player_names[$player_idx] . " > ");

            if (!$ret = json_decode($ret)) {
                continue;
            }
            foreach ($allow as $term) {
                list($action, $description) = $term;
                if ($action == '丟') {
                    if (!($ret->丟 ?? false)) {
                        continue;
                    }
                    $tile_id = Mahjong::getTileID($ret->丟);
                    if ($tile_id === false) {
                        echo "無效的牌名，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    if ($tile_id === $this->players[$player_idx]['drawn_tile']) {
                        return $ret;
                    } else if (in_array($tile_id, $this->players[$player_idx]['hand'])) {
                        return $ret;
                    } else {
                        error_log(json_encode($this->players[$player_idx]['hand'], JSON_UNESCAPED_UNICODE));
                        echo "你沒有 {$ret->丟}(tile_id={$tile_id}) 這張牌";
                        continue 2; // 重新輸入
                    }
                }
            }
            break;
        }
        return $ret;
    }
}


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
        $tiles = [];
        foreach ($count as $tile => $num) {
            if ($num >= 4) {
                $tiles[] = $tile; // 有四張一樣的牌
            }
        }
        if (count($tiles) > 0) {
            return $tiles; // 返回第一張可以暗槓的牌
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
    protected $last_throw_player = -1;

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
                'saw_event_id' => -1,
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

        $action_stack = [];
        // 先檢查是否能夠槓
        foreach ($this->players as $player_idx => $player_data) {
            $tiles = Mahjong::can暗槓($player_data['hand']);
            if ($tiles) {
                $action_stack[$player_idx] = [];
                foreach ($tiles as $tile) {
                    $action_stack[$player_idx][] = [
                        '暗槓',
                        sprintf("若要暗槓 %s，請回答 {\"暗槓\":\"%s\"}",
                        Mahjong::mapToWord($tile),
                        Mahjong::mapToWord($tile),
                        ),
                    ];
                }
            }
        }

        while (true) {
            $this->print_all_player_tiles($players);

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
            } else {
                // 摸牌
                $playing_player = ($this->last_throw_player + 1) % 4; // 換下一個玩家
                $this->players[$playing_player]['drawn_tile'] = array_shift($this->tile_set);
                $allow = $this->checkAllowAction($playing_player);
            }

            // 處理輸入
            while (true) {
                $input = $this->parseInput($allow, $playing_player);

                if ($input->talk ?? false) {
                    $this->public_events[] = sprintf("\$%d說：「%s」",
                        $playing_player,
                        $input->talk,
                    );
                    $this->players[$playing_player]['saw_event_id'] = count($this->public_events) - 1; // 更新看到的事件
                }
                if ($input->碰 ?? false) { // 碰牌
                    $hands = $this->players[$playing_player]['hand'];
                    $hand_tile_count = array_count_values($hands);
                    $last_throw_tile = $this->last_throw_tile;

                    $c = 2;
                    $hands = array_filter($hands, function($tile_id) use ($last_throw_tile, &$c) {
                        if ($tile_id == $last_throw_tile && $c > 0) {
                            $c--;
                            return false; // 移除兩張碰的牌
                        }
                        return true; // 保留其他牌
                    });
                    $hands = array_values($hands);
                    $this->players[$playing_player]['hand'] = $hands;
                    $this->public_events[] = sprintf("\$%d碰了%s",
                        $playing_player,
                        Mahjong::mapToWord($last_throw_tile),
                    );
                    // 接著丟牌
                }

                if ($input->槓 ?? false) { // 槓牌
                    $hands = $this->players[$playing_player]['hand'];
                    $hand_tile_count = array_count_values($hands);
                    $last_throw_tile = $this->last_throw_tile;
                    $c = 3;
                    $hands = array_filter($hands, function($tile_id) use ($last_throw_tile, &$c) {
                        if ($tile_id == $last_throw_tile && $c > 0) {
                            $c--;
                            return false; // 移除兩張碰的牌
                        }
                        return true; // 保留其他牌
                    });
                    $hands = array_values($hands);
                    $this->players[$playing_player]['hand'] = $hands;
                    $this->public_events[] = sprintf("\$%d槓了%s",
                        $playing_player,
                        Mahjong::mapToWord($last_throw_tile),
                    );
                    $this->players[$playing_player]['saw_event_id'] = count($this->public_events) - 1; // 更新看到的事件
                    // 接著摸牌
                    $action_stack = []; // 清空動作堆疊
                    $player_idx = ($playing_player + 3) % 4;
                    continue 2;
                }

                if ($input->吃 ?? false) { // 吃牌
                    $hands = $this->players[$playing_player]['hand'];
                    $first_tile_id = Mahjong::getTileID($input->吃);
                    $last_throw_tile = $this->last_throw_tile;
                    $removed = [];
                    $hands = array_filter($hands, function($tile_id) use ($first_tile_id, $last_throw_tile, &$removed) {
                        if ($last_throw_tile == $tile_id) {
                            return true; // 手上有吃的牌保留下來
                        }
                        if ($tile_id == $first_tile_id or $tile_id == $first_tile_id + 4 or $tile_id == $first_tile_id + 8) {
                            if ($removed[$tile_id] ?? false) { // 只移除一張
                                return true;
                            }
                            $removed[$tile_id] = true;
                            return false; // 吃的牌移走
                        }
                        return true;
                    });
                    $this->players[$playing_player]['hand'] = array_values($hands);
                    $eating_tiles = [
                        $first_tile_id,
                        $first_tile_id + 4,
                        $first_tile_id + 8,
                    ];
                    $eating_tiles = array_values(array_filter($eating_tiles, function($tile_id) use ($last_throw_tile) {
                        return $tile_id != $last_throw_tile; // 移除摸到的牌
                    }));

                    $this->public_events[] = sprintf("\$%d吃了%s,%s,%s",
                        $playing_player,
                        Mahjong::mapToWord($eating_tiles[0]),
                        Mahjong::mapToWord($last_throw_tile),
                        Mahjong::mapToWord($eating_tiles[1]),
                    );
                    // 接著丟牌
                }

                if ($input->胡 ?? false) { // 胡牌
                    // TODO: 胡牌，要通知大家講完結感言
                }

                if ($input->放棄 ?? false) {
                    // 直接輪下一個人
                    continue 2;
                }

                if ($input->丟 ?? false) { // 打牌
                    $action_stack = []; // 清空動作堆疊
                    $hands = $this->players[$playing_player]['hand'];
                    if ($this->players[$playing_player]['drawn_tile']) {
                        $hands[] = $this->players[$playing_player]['drawn_tile'];
                    }
                    $tile_id = Mahjong::getTileID($input->丟);
                    $idx = array_search($tile_id, $hands);
                    unset($hands[$idx]);
                    sort($hands); // 打牌後要排序
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
                            "碰牌，請回答 {\"碰\":true,\"丟\":\"\$要丟的牌\"}",
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
                for ($start_number = max(0, $drown_number - 2); $start_number < min(7, $drown_number); $start_number ++) {
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
                        sprintf("若要吃 %s,%s,%s，請回答 {\"吃\":\"%s\",\"丟\":\"要丟的牌\"}",
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
                    "碰牌，請回答 {\"碰\":true,\"丟\":\"\$要丟的牌\"}",
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
            for ($start_number = max(0, $drown_number - 2); $start_number < min(7, $drown_number); $start_number ++) {
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
                    sprintf("若要吃 %s,%s,%s，請回答 {\"吃\":\"%s\",\"丟\":\"要丟的牌\"}",
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
        $message = $this->getInitMessage($player_idx);
        if (count($this->public_events) > 0) {
            for ($idx = $this->players[$player_idx]['saw_event_id'] + 1; $idx < count($this->public_events); $idx++) {
                $message .= "，" . self::changeName($player_idx, $this->public_events[$idx]);
            }
            $this->players[$player_idx]['saw_event_id'] = count($this->public_events) - 1;
        }
        if ($this->players[$player_idx]['drawn_tile']) {
            $message .= sprintf("，你摸到的牌是 %s",
                Mahjong::mapToWord($this->players[$player_idx]['drawn_tile'])
            );
        }
        while (true) {
            $message .= "，請問您要做什麼？";
            foreach ($allow as $term) {
                list($action, $description) = $term;
                $message .= "\n* {$description}";
            }
            $message .= "\n(不需要分析為什麼，如果你想要垃圾話，可以透過 \"talk\":\"垃圾話內容\" 補充)";
            echo $message . "\n";

            $ret = readline("TO Player " . $this->player_names[$player_idx] . " > ");
            readline_add_history($ret);

            if (!$ret = json_decode($ret)) {
                continue;
            }
            $hands = $this->players[$player_idx]['hand'];
            $tile_count = array_count_values($hands);

            $output = new StdClass;
            if ($ret->talk ?? false) {
                $output->talk = $ret->talk;
            }
            foreach ($allow as $term) {
                list($action, $description) = $term;
                if ($action == '丟') {
                    if (!($ret->丟 ?? false)) {
                        continue;
                    }
                    $output->丟 = $ret->丟;
                    $tile_id = Mahjong::getTileID($ret->丟);
                    if ($tile_id === false) {
                        $message = "無效的牌名，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    if ($tile_id === $this->players[$player_idx]['drawn_tile']) {
                        return $output;
                    } else if (in_array($tile_id, $this->players[$player_idx]['hand'])) {
                        return $output;
                    } else {
                        $message = "你沒有 {$ret->丟} 這張牌";
                        continue 2; // 重新輸入
                    }
                }

                if (in_array($action, [
                    '碰',
                    '胡',
                    '槓',
                    '放棄',
                ])) {
                    if (($ret->{$action} ?? false) === true) {
                        if ($action == '碰') {
                            if (!($ret->丟 ?? false)) {
                                $message = "碰牌時需要丟牌，請重新輸入，";
                                continue 2; // 重新輸入
                            }
                            $output->丟 = $ret->丟;
                            $tile_id = Mahjong::getTileID($ret->丟);
                            if ($tile_id === false) {
                                $message = "無效的牌名，請重新輸入，";
                                continue 2; // 重新輸入
                            }
                            if ($tile_count[$tile_id] < 1) {
                                $message = "你沒有 {$output->丟} 可以丟，";
                                continue 2;
                            }
                            if ($tile_count[$this->last_throw_tile] < 2) {
                                $message = "你沒有辦法碰 " . Mahjong::mapToWord($this->last_throw_tile) . "，";
                                continue 2;
                            }
                        } elseif ('槓' == $action) {
                            if ($tile_count[$this->last_throw_tile] < 3) {
                                $message = "你沒有辦法槓 " . Mahjong::mapToWord($this->last_throw_tile) . "，";
                                continue 2;
                            }

                        }
                        $output->{$action} = true;
                        return $output; // 放棄
                    }
                }

                if ($action == '吃') {
                    if (!($ret->吃 ?? false)) {
                        continue;
                    }
                    $output->吃 = $ret->吃 ?? null;
                    $output->丟 = $ret->丟 ?? null; // 吃牌時需要丟牌
                    $eat_tile_id = Mahjong::getTileID($output->吃);
                    if ($eat_tile_id === false) {
                        $message = "無效的吃牌名，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    $throw_tile_id = Mahjong::getTileID($output->丟);
                    if ($throw_tile_id === false) {
                        $message = "無效的丟牌名，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    $last_throw_tile = $this->last_throw_tile;
                    $hand_tile_count = array_count_values($this->players[$player_idx]['hand']);
                    for ($i = 0; $i < 3; $i ++) {
                        if ($last_throw_tile == $eat_tile_id + $i * 4) {
                            continue;
                        }
                        if (!in_array($eat_tile_id + $i * 4, $this->players[$player_idx]['hand'])) {
                            $message = "你沒有 {$ret->吃} 這張牌，請重新輸入。\n";
                            continue 2; // 重新輸入
                        }
                        $hand_tile_count[$eat_tile_id + $i * 4]--;
                    }
                    if ($hand_tile_count[$throw_tile_id] <= 0) {
                        $message = "你沒有 {$ret->丟} 這張牌，請重新輸入。\n";
                        continue 2; // 重新輸入
                    }
                    return $output;
                }
            }
            break;
        }
        throw new Exception("無效的輸入，請重新輸入。");
    }

    public function print_all_player_tiles($players)
    {
        echo "======\n";
        // 顯示四家的牌
        for ($i = 0; $i < 4; $i++) {
            echo $players[$i] . "：";
            printf("%s\n",
                Mahjong::printTile($this->players[$i]['hand'])
            );
        }
        echo "======\n";
    }
}


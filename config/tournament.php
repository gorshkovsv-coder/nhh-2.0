<?php

return [
    'points' => [
        // NHL-style: 2 за победу (в т.ч. OT/SO), 1 за поражение в OT/SO, 0 за поражение в основное
        'win' => 2,
        'otl' => 1,
        'loss' => 0,
    ],
    'tiebreakers' => [
        // порядок тай-брейков по умолчанию
        'order' => ['points', 'h2h', 'gd', 'gf', 'tech_losses'],
    ],
];

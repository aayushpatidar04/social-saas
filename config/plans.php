<?php

/*
| Default limits per plan. A super admin can override `max_users` and
| `max_social_accounts` per organization; NULL there means "use this".
| `null` here means unlimited.
*/
return [
    'free'         => ['max_users' => 2,    'max_social_accounts' => 2],
    'starter'      => ['max_users' => 5,    'max_social_accounts' => 5],
    'professional' => ['max_users' => 20,   'max_social_accounts' => 25],
    'enterprise'   => ['max_users' => null, 'max_social_accounts' => null],
];

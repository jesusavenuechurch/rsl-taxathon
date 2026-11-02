<?php

return [

    /*
    | The demo runs on a simulated calendar. "Today" is RSL_TODAY plus however
    | many days an admin has advanced the clock (stored in settings). Set
    | RSL_CLOCK=real to use the server's real date instead.
    */
    'clock' => env('RSL_CLOCK', 'simulated'),
    'today' => env('RSL_TODAY', '2026-11-02'),

    /*
    | Demo users (one per role) are only seeded outside production, or when
    | this is switched on explicitly. Leave the password empty to get a
    | random one printed once by the seeder.
    */
    'seed_demo_users' => (bool) env('RSL_SEED_DEMO_USERS', env('APP_ENV') !== 'production'),
    'demo_user_password' => env('DEMO_USER_PASSWORD'),

];

<?php

declare(strict_types=1);

return [
    /**
     * The approval polymorphic pivot name.
     *
     * Changing this value requires re-running the migration that creates the approvals table.
     *
     * Default: 'approvalable'
     */
    'approval_pivot' => 'approvalable',

    /**
     * The users table name.
     *
     * Default: 'users'
     */
    'users_table' => 'users',

    /**
     * Configurable approval states.
     */
    'states' => [
        'approved' => [
            'name' => 'Approved',
        ],
        'pending' => [
            'name' => 'Pending',
            'default' => true,
        ],
        'rejected' => [
            'name' => 'Rejected',
        ],
    ],
];

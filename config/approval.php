<?php

return [
    'approval' => [
        /**
         * The approval polymorphic pivot name
         *
         * Default: 'approvalable'
         */
        'approval_pivot' => 'approvalable',

        /**
         * The users table name
         *
         * Default: 'users'
         */
        'users_table' => 'users',
    ],

    /**
     * Configurable approval states
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

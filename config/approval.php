<?php

return [
    'approval' => [

        /**
         * The column name for new data.
         *
         * Default: 'new_data'
         */
        'new_data' => 'new_data',


        /**
         * The column name for original data
         *
         * Default: 'original_data
         */
        'original_data' => 'original_data',

        /**
         * The approval polymorphic pivot name
         *
         * Default: 'approvalable'
         */
        'approval_pivot' => 'approvalable',
    ],
];

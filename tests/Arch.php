<?php

test('Arch tests')
    ->expect(['dd', 'ddd', 'dump'])
    ->each
    ->not
    ->toBeUsed();

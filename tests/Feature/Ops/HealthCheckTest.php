<?php

test('/up returns 200 when the database and disk are fine', function () {
    $this->get('/up')->assertOk();
});

test('/up returns 500 when free disk space is below the threshold', function () {
    config(['siperkasa.health_min_free_mb' => PHP_INT_MAX]);

    $this->get('/up')->assertStatus(500);
});

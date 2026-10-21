<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login from the homepage', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

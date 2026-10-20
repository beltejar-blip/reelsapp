<?php

test('the startup page sends visitors to the log in form', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect('/login');
});

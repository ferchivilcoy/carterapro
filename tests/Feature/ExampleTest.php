<?php

test('guests are redirected to the login screen from the root', function () {
    $this->get('/')->assertRedirect(route('login'));
});

<?php

test('la page home redirige les visiteurs non connectés vers login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

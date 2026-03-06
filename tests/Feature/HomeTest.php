<?php

test('/ redirects to /dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

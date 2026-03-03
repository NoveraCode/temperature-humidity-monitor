<?php

test('anyone can visit the home page', function () {
    $response = $this->get(route('home'));
    $response->assertOk();
});

test('home page renders the home inertia component', function () {
    $response = $this->get(route('home'));
    $response->assertInertia(fn ($page) => $page->component('home'));
});

test('home page passes rooms and chartLogs props', function () {
    $response = $this->get(route('home'));
    $response->assertInertia(
        fn ($page) => $page
            ->component('home')
            ->has('rooms')
            ->has('chartLogs'),
    );
});

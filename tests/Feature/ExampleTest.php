<?php

// Ce fichier était le test d'exemple généré par Laravel.
// Le test original vérifiait que GET / redirige vers login.
// Depuis le sprint musée-virtuel, la route home est publique (composant Home.vue).
// La couverture est assurée par MuseePublicTest.php.
test('la page home est accessible sans authentification', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Home'));
});

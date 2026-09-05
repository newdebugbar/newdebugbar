<?php

use Illuminate\Support\Facades\Blade;

it('renders static and Alpine-driven terms with the same definition-row contract', function () {
    $static = Blade::render(<<<'BLADE'
        <dl>
            <x-newdebugbar::inspector-definition-row label="Status">Allowed</x-newdebugbar::inspector-definition-row>
        </dl>
        BLADE);
    $dynamic = Blade::render(<<<'BLADE'
        <dl>
            <x-newdebugbar::inspector-definition-row>
                <x-slot:term x-text="field.label"></x-slot:term>
                <x-slot:value data-ndb-test-value x-text="field.value"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
        </dl>
        BLADE);

    expect($static)
        ->toContain('<dt class="ndb:text-xs ndb:font-medium')
        ->toContain('Status</dt>')
        ->toContain('Allowed</dd>')
        ->and($dynamic)
        ->toContain('x-text="field.label"')
        ->toContain('data-ndb-test-value')
        ->toContain('x-text="field.value"');
});

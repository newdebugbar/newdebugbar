<?php

namespace NewDebugBar\Tests\Fixtures;

use Livewire\Component;

/** Rotates the CSRF token mid-page the way a host login does. */
final class HostSessionRotator extends Component
{
    public bool $rotated = false;

    public function rotate(): void
    {
        session()->regenerate();

        $this->rotated = true;
    }

    public function render(): string
    {
        return <<<'HTML'
            <section data-testid="host-session-rotator">
                <button type="button" data-testid="rotate-session" wire:click="rotate">Log in</button>
                <output data-testid="host-session-rotated">{{ $rotated ? 'rotated' : 'pending' }}</output>
            </section>
            HTML;
    }
}

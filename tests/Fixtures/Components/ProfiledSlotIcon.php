<?php

namespace NewDebugBar\Tests\Fixtures\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Nested class component whose public blade() method is bound into view data. */
final class ProfiledSlotIcon extends Component
{
    public function blade(): View
    {
        return view('profiled-slot-icon');
    }

    public function classes(): array
    {
        return ['ndb-profiled-slot-icon'];
    }

    public function render(): View
    {
        return $this->blade();
    }
}

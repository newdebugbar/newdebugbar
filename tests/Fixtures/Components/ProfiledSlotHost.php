<?php

namespace NewDebugBar\Tests\Fixtures\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Host component whose Blade view opens a named slot around a nested class component. */
final class ProfiledSlotHost extends Component
{
    public function blade(): View
    {
        return view('profiled-slot-host');
    }

    public function render(): View
    {
        return $this->blade();
    }
}

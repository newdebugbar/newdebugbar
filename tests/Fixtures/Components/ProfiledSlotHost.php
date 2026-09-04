<?php

namespace NewDebugBar\Tests\Fixtures\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Opens a named slot around a nested component with a lazy public view method. */
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

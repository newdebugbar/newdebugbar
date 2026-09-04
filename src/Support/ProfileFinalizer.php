<?php

namespace NewDebugBar\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\ProfileStore;
use Throwable;

/** Stores and injects a profile after Laravel has rendered the final response. */
final class ProfileFinalizer
{
    public function __construct(
        private readonly ProfileManager $manager,
        private readonly ProfileStore $store,
        private readonly BarInjector $injector,
        private readonly Application $app,
    ) {}

    public function handle(RequestHandled $event): void
    {
        if ($event->request->attributes->get('newdebugbar.profile-data') === true) {
            $event->response->headers->set('Cache-Control', 'no-store, private');
        }

        if (! $this->manager->isCollecting()) {
            return;
        }

        try {
            $profile = $this->manager->checkpoint($event->request, $event->response);
        } catch (Throwable) {
            $this->manager->discard();

            return;
        }

        try {
            $id = $this->store->put($profile);
        } catch (Throwable) {
            return;
        }

        $event->request->attributes->set('newdebugbar.profile-id', $id);
        $event->response->headers->set('X-NewDebugBar-Profile', $id);

        if ($this->injector->supports($event->response)) {
            try {
                $this->injector->inject($event->response, $id);
            } catch (Throwable) {
                // Debug rendering must never replace the application response.
            }
        }

        try {
            $this->app->terminating(fn () => $this->finishAfterResponse());
            $this->manager->resumeAfterResponse();
        } catch (Throwable) {
            $this->finishAfterResponse();
        }
    }

    private function finishAfterResponse(): void
    {
        try {
            $profile = $this->manager->finishAfterResponse();

            if ($profile !== null) {
                $this->store->put($profile);
            }
        } catch (Throwable) {
            $this->manager->discard();
        }
    }
}

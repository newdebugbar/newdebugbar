import { composeState } from '../runtime.js';
import { createAuthorization } from './authorization.js';
import { createCache } from './cache.js';
import { createHttpClient } from './http-client.js';
import { createController } from './livewire/controller.js';
import { createMail } from './mail.js';
import { createModels } from './models.js';
import { createNotifications } from './notifications.js';
import { createQueries } from './queries.js';
import { createQueue } from './queue.js';
import { createRedis } from './redis.js';
import { createTimeline } from './timeline.js';
import { createEvents } from './events.js';
import { createLogs } from './logs.js';
import { createViews } from './views.js';

const controllers = {
  authorization: createAuthorization,
  cache: createCache,
  http_client: createHttpClient,
  livewire: createController,
  mail: createMail,
  models: createModels,
  notifications: createNotifications,
  queries: createQueries,
  queue: createQueue,
  redis: createRedis,
  timeline: createTimeline,
  events: createEvents,
  logs: createLogs,
  views: createViews,
};

export function createSectionController(section, context) {
  const controller = controllers[section]?.(context);
  if (!controller) return {};

  const { shell, profileId } = context;
  const instance = Symbol();

  return composeState(controller, {
    profileId,
    initialized: false,
    destroyed: false,
    init() {
      shell.mountSection(section, profileId, this, instance);
    },
    destroy() {
      this.destroyed = true;
      this.deactivate?.();
      controller.destroy?.call(this);
      shell.unmountSection(instance);
    },
    refresh() {
      if (this.destroyed || profileId !== shell.summary.id) return;
      controller.refresh.call(this);
    },
  });
}

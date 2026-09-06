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
  return controllers[section]?.(context) ?? {};
}

<?php

namespace NewDebugBar\Support;

use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Throwable;

/** Identifies Laravel's queued mail wrappers without building either message. */
final class QueuedCommunicationInspector
{
    public function __construct(private readonly int $maxItems = 100) {}

    /** @return array<string, mixed>|null */
    public function inspect(mixed $job): ?array
    {
        try {
            if ($job instanceof SendQueuedMailable) {
                $mailable = $job->mailable;

                return [
                    'communication_type' => 'mail',
                    'communication_class' => $mailable::class,
                    'channels' => ['mail'],
                    'notifiable_types' => [],
                    'notifiable_count' => 0,
                    'recipient_count' => $this->mailableRecipientCount($mailable),
                ];
            }

            if ($job instanceof SendQueuedNotifications) {
                $types = [];
                $count = 0;

                foreach ($job->notifiables as $notifiable) {
                    $count++;

                    if (count($types) < $this->maxItems) {
                        $types[] = is_object($notifiable) ? $notifiable::class : get_debug_type($notifiable);
                    }
                }

                return [
                    'communication_type' => 'notification',
                    'communication_class' => $job->notification::class,
                    'channels' => array_slice(array_values(array_filter((array) $job->channels, 'is_string')), 0, $this->maxItems),
                    'notifiable_types' => array_values(array_unique($types)),
                    'notifiable_count' => $count,
                    'recipient_count' => 0,
                ];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function mailableRecipientCount(object $mailable): int
    {
        $count = 0;

        foreach (['to', 'cc', 'bcc'] as $property) {
            $recipients = property_exists($mailable, $property) ? $mailable->{$property} : null;
            $count += is_array($recipients) ? count($recipients) : 0;
        }

        return $count;
    }
}

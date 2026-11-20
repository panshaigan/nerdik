<?php

namespace App\Livewire\Concerns;

use App\Support\Notifications\RelativeAppUrl;
use Illuminate\Support\Facades\Auth;

trait HandlesDatabaseNotificationClick
{
    public function handleNotificationClick(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = $notification->data;
        $isActionable = ($data['actionable'] ?? false) === true
            && isset($data['request_id']);

        // Do not dispatch database-notifications-updated before redirect: that
        // races a second Livewire update against navigation and aborts mid-flight
        // (Sentry UnhandledRejection with Livewire's {status,body,json,errors}).
        // Full-page redirect (not wire:navigate) so the destination and nav badge
        // are not served from Livewire's navigate cache with stale unread state.

        if ($isActionable) {
            $this->redirectToNotificationUrl(
                route('requests.index', ['request' => $data['request_id']]),
            );

            return;
        }

        if (($data['type'] ?? '') === 'scheduled_periodic_digest') {
            $items = $data['items'] ?? [];
            if (count($items) === 1) {
                $itemUrl = RelativeAppUrl::normalize((string) ($items[0]['url'] ?? ''));
                if ($itemUrl !== null) {
                    $this->redirectToNotificationUrl($itemUrl);

                    return;
                }
            }
        }

        $safeUrl = RelativeAppUrl::normalize((string) ($data['url'] ?? ''))
            ?? route('dashboard', [], false);

        $this->redirectToNotificationUrl($safeUrl);
    }

    private function redirectToNotificationUrl(string $url): void
    {
        $this->redirect($url, navigate: false);
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Support\Livewire\SpaNavigate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        // The destination page loads fresh unread indicators.

        if ($isActionable) {
            $this->redirectToNotificationUrl(
                route('requests.index', ['request' => $data['request_id']]),
            );

            return;
        }

        if (($data['type'] ?? '') === 'scheduled_periodic_digest') {
            $items = $data['items'] ?? [];
            if (count($items) === 1) {
                $itemUrl = $items[0]['url'] ?? '';
                if (is_string($itemUrl) && Str::startsWith($itemUrl, '/') && ! Str::startsWith($itemUrl, '//')) {
                    $this->redirectToNotificationUrl($itemUrl);

                    return;
                }
            }
        }

        $url = $data['url'] ?? '';
        $safeUrl = is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? $url
            : route('dashboard');

        $this->redirectToNotificationUrl($safeUrl);
    }

    private function redirectToNotificationUrl(string $url): void
    {
        $this->redirect($url, navigate: SpaNavigate::allowed($url));
    }
}

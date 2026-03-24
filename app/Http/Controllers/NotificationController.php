<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index')->with('status', __('All notifications marked as read.'));
    }

    public function markRead(Request $request, string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('dashboard');

        return redirect($url);
    }
}

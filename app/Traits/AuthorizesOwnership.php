<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait AuthorizesOwnership
{
    /**
     * Enforce that the current user can edit/delete a model only if they own it.
     * Admins are allowed to bypass ownership checks.
     */
    protected function authorizeCreatedBy(Model $entity): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->is_admin === true) {
            return;
        }

        $ownerId = $entity->getAttribute('created_by');
        if ($ownerId === null || (int) $ownerId !== (int) $user->id) {
            abort(403);
        }
    }
}

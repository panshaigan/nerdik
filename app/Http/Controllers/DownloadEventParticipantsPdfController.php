<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ParticipantRosterPdfBuilder;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Response;

class DownloadEventParticipantsPdfController extends Controller
{
    use AuthorizesOwnership;

    public function __invoke(Event $event, ParticipantRosterPdfBuilder $builder): Response
    {
        $this->authorizeCreatedBy($event);

        return $builder->downloadForEvent($event);
    }
}

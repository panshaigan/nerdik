<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ParticipantRosterPdfBuilder;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Response;

class DownloadActivityParticipantsPdfController extends Controller
{
    use AuthorizesOwnership;

    public function __invoke(Activity $activity, ParticipantRosterPdfBuilder $builder): Response
    {
        $this->authorizeCreatedBy($activity);

        return $builder->streamForActivity($activity);
    }
}

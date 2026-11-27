<?php

use App\Enums\GenerationStatus as Status;

it('only moves forward', function (Status $from, Status $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [Status::Pending, Status::Queued, true],
    [Status::Pending, Status::Succeeded, true],   // webhook before the create response
    [Status::Queued, Status::Processing, true],
    [Status::Processing, Status::Succeeded, true],
    [Status::Processing, Status::Failed, true],
    [Status::Processing, Status::Queued, false],
    [Status::Succeeded, Status::Processing, false], // late retried event
    [Status::Succeeded, Status::Succeeded, false],  // duplicate
    [Status::Succeeded, Status::Failed, false],
    [Status::Failed, Status::Succeeded, false],
    [Status::Pending, Status::Rejected, true],
    [Status::Queued, Status::Rejected, false],       // the provider accepted it already
    [Status::Rejected, Status::Succeeded, true],     // the provider did create it after all
]);

<?php

namespace App\Modules\Attendees\Domain;

final readonly class AttendeeRecord
{
    public function __construct(public string $id) {}
}

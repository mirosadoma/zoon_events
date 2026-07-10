<?php

namespace App\Modules\Shared\Domain;

enum DeploymentMode: string
{
    case Saas = 'saas';
    case OnPremise = 'on_premise';
}

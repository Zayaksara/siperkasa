<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class AssetPolicy
{
    use AdminOnly;
}

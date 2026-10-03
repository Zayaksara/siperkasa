<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class AssetImportPolicy
{
    use AdminOnly;
}

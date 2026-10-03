<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class UserPolicy
{
    use AdminOnly;
}

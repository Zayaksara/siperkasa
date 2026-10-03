<?php

namespace App\Policies;

use App\Policies\Concerns\AdminOnly;

class RoomPolicy
{
    use AdminOnly;
}

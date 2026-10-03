<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomRequest extends FormRequest
{
    public function rules(): array
    {
        $room = $this->route('room');
        $buildingId = $room instanceof Room ? $room->building_id : $this->route('building')->id;

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('rooms', 'code')->where('building_id', $buildingId)->ignore($room),
            ],
            'name' => ['required', 'string', 'max:150'],
            'floor' => ['nullable', 'integer', 'between:-5,50'],
        ];
    }
}

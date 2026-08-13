<?php

namespace App\Services;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserProfile;

class ProfileService
{
    /**
     * Both endpoints upsert: the app sends the whole form and does not have to know
     * whether this is the first save after registration or a later edit.
     */
    public function saveProfile(User $user, array $data): UserProfile
    {
        $profile = $user->profile()->updateOrCreate([], $data);

        $user->setRelation('profile', $profile);

        return $profile;
    }

    public function saveMotorcycle(User $user, array $data): Motorcycle
    {
        $motorcycle = $user->motorcycle()->updateOrCreate([], $data);

        $user->setRelation('motorcycle', $motorcycle);

        return $motorcycle;
    }
}

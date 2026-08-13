<?php

namespace App\Services;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function storeAvatar(UserProfile $profile, UploadedFile $file): void
    {
        $this->replaceFile(
            $profile,
            'avatar',
            $file,
            config('motusy.uploads.avatar_directory'),
            config('motusy.uploads.avatar_max_dimension'),
        );
    }

    public function storeMotorcyclePhoto(Motorcycle $motorcycle, UploadedFile $file): void
    {
        $this->replaceFile(
            $motorcycle,
            'photo',
            $file,
            config('motusy.uploads.motorcycle_photo_directory'),
            config('motusy.uploads.motorcycle_photo_max_dimension'),
        );
    }

    public function removeAvatar(UserProfile $profile): void
    {
        $this->clearFile($profile, 'avatar');
    }

    public function removeMotorcyclePhoto(Motorcycle $motorcycle): void
    {
        $this->clearFile($motorcycle, 'photo');
    }

    /**
     * Writes the new file first and deletes the previous one only afterwards, so a
     * failed write never leaves the record pointing at something that is gone.
     *
     * The name is generated and the extension comes from the validated image type,
     * never from the uploaded file name — these files land under the webroot.
     */
    private function replaceFile(
        Model $model,
        string $column,
        UploadedFile $file,
        string $directory,
        int $maxDimension,
    ): void {
        $previous = $model->{$column};

        $image = Image::fromUpload($file)
            // Must run before scaling: the rotation flag lives in EXIF, which the
            // re-encode below discards. Without it, photos taken with the phone held
            // upright come out on their side.
            ->orient()
            ->scale($maxDimension, $maxDimension)
            ->toFormat(config('motusy.uploads.format'))
            ->quality(config('motusy.uploads.quality'));

        $path = $image->storeAs(
            $directory,
            Str::uuid().'.'.$image->extension(),
            config('motusy.uploads.disk'),
        );

        $model->update([$column => $path]);

        $this->deleteFile($previous);
    }

    private function clearFile(Model $model, string $column): void
    {
        $previous = $model->{$column};

        $model->update([$column => null]);

        $this->deleteFile($previous);
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(config('motusy.uploads.disk'))->delete($path);
        }
    }
}

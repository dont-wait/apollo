<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $fileName = fake()->slug().'.jpg';

        return ['uploaded_by' => User::factory(), 'file_name' => $fileName, 'mime_type' => 'image/jpeg', 'size_bytes' => fake()->numberBetween(10000, 5000000), 'storage_key' => 'media/'.fake()->uuid().'/'.$fileName, 'url' => fake()->imageUrl(), 'width' => 1600, 'height' => 900];
    }
}

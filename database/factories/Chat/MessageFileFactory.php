<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFileFactory extends Factory
{
    protected $model = MessageFile::class;

    public function definition(): array
    {
        $mimeType = $this->faker->randomElement([
            'image/jpeg',
            'image/png', 
            'audio/mp3',
            'audio/wav',
            'video/mp4',
            'application/pdf',
            'text/plain'
        ]);
        
        $extension = $this->getExtensionForMimeType($mimeType);
        $filename = $this->faker->word() . '.' . $extension;
        
        return [
            'message_id' => Message::factory(),
            'original_filename' => $filename,
            'encrypted_filename' => $this->faker->uuid() . '.enc',
            'mime_type' => $mimeType,
            'file_size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'encrypted_size' => $this->faker->numberBetween(1100, 10500000), // Slightly larger due to encryption
            'file_hash' => $this->faker->sha256(),
            'encryption_key_encrypted' => [
                'key' => base64_encode($this->faker->sha256()),
                'iv' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{16}')),
                'tag' => base64_encode($this->faker->regexify('[A-Za-z0-9+/]{16}')),
            ],
            'thumbnail_path' => $this->shouldHaveThumbnail($mimeType) ? 
                'thumbnails/' . $this->faker->uuid() . '_thumb.jpg' : null,
            'thumbnail_encrypted' => $this->faker->boolean(70),
            'metadata' => $this->generateMetadataForType($mimeType),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $this->faker->randomElement(['image/jpeg', 'image/png', 'image/gif', 'image/webp']),
            'thumbnail_path' => 'thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'metadata' => [
                'width' => $this->faker->numberBetween(100, 4000),
                'height' => $this->faker->numberBetween(100, 4000),
                'has_exif' => $this->faker->boolean(),
                'camera_make' => $this->faker->optional()->company(),
                'location' => $this->faker->optional()->address(),
            ],
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $this->faker->randomElement(['audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a']),
            'thumbnail_path' => null,
            'metadata' => [
                'duration' => $this->faker->randomFloat(1, 1, 600), // 1 second to 10 minutes
                'bitrate' => $this->faker->randomElement([128, 192, 256, 320]),
                'sample_rate' => $this->faker->randomElement([44100, 48000, 96000]),
                'channels' => $this->faker->randomElement([1, 2]), // mono or stereo
                'artist' => $this->faker->optional()->name(),
                'title' => $this->faker->optional()->words(3, true),
            ],
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $this->faker->randomElement(['audio/mp3', 'audio/wav', 'audio/ogg']),
            'thumbnail_path' => null,
            'metadata' => [
                'duration' => $this->faker->randomFloat(1, 1, 180), // 1 second to 3 minutes
                'bitrate' => $this->faker->randomElement([64, 128, 192]),
                'sample_rate' => $this->faker->randomElement([16000, 22050, 44100]),
                'channels' => 1, // Voice messages are typically mono
                'waveform_data' => $this->generateWaveform(),
                'transcription_pending' => $this->faker->boolean(),
            ],
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $this->faker->randomElement(['video/mp4', 'video/webm', 'video/avi', 'video/mov']),
            'file_size' => $this->faker->numberBetween(5242880, 104857600), // 5MB to 100MB
            'encrypted_size' => $this->faker->numberBetween(5500000, 105000000),
            'thumbnail_path' => 'thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'metadata' => [
                'duration' => $this->faker->randomFloat(1, 10, 1800), // 10 seconds to 30 minutes
                'width' => $this->faker->randomElement([640, 720, 1080, 1920]),
                'height' => $this->faker->randomElement([480, 720, 1080, 1920]),
                'fps' => $this->faker->randomElement([24, 30, 60]),
                'codec' => $this->faker->randomElement(['H.264', 'H.265', 'VP9']),
                'has_audio' => $this->faker->boolean(80),
            ],
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => $this->faker->randomElement([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'text/csv',
            ]),
            'thumbnail_path' => 'thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'metadata' => [
                'pages' => $this->faker->optional()->numberBetween(1, 100),
                'word_count' => $this->faker->optional()->numberBetween(100, 10000),
                'author' => $this->faker->optional()->name(),
                'created_date' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
                'language' => $this->faker->optional()->languageCode(),
            ],
        ]);
    }

    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(52428800, 314572800), // 50MB to 300MB
            'encrypted_size' => $this->faker->numberBetween(55000000, 320000000),
        ]);
    }

    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1024, 102400), // 1KB to 100KB
            'encrypted_size' => $this->faker->numberBetween(1100, 103000),
        ]);
    }

    public function withThumbnail(): static
    {
        return $this->state(fn (array $attributes) => [
            'thumbnail_path' => 'thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'thumbnail_encrypted' => true,
        ]);
    }

    public function withoutThumbnail(): static
    {
        return $this->state(fn (array $attributes) => [
            'thumbnail_path' => null,
            'thumbnail_encrypted' => false,
        ]);
    }

    private function getExtensionForMimeType(string $mimeType): string
    {
        return match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/m4a' => 'm4a',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/avi' => 'avi',
            'video/mov' => 'mov',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            default => 'bin',
        };
    }

    private function shouldHaveThumbnail(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') || 
               str_starts_with($mimeType, 'video/') ||
               $mimeType === 'application/pdf';
    }

    private function generateMetadataForType(string $mimeType): array
    {
        if (str_starts_with($mimeType, 'image/')) {
            return [
                'width' => $this->faker->numberBetween(100, 4000),
                'height' => $this->faker->numberBetween(100, 4000),
            ];
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return [
                'duration' => $this->faker->randomFloat(1, 1, 600),
                'bitrate' => $this->faker->randomElement([128, 192, 256]),
            ];
        }

        if (str_starts_with($mimeType, 'video/')) {
            return [
                'duration' => $this->faker->randomFloat(1, 10, 1800),
                'width' => $this->faker->randomElement([640, 720, 1080]),
                'height' => $this->faker->randomElement([480, 720, 1080]),
            ];
        }

        return [];
    }

    private function generateWaveform(): array
    {
        $points = $this->faker->numberBetween(50, 200);
        $waveform = [];
        
        for ($i = 0; $i < $points; $i++) {
            $waveform[] = $this->faker->randomFloat(2, 0, 1);
        }
        
        return $waveform;
    }
}
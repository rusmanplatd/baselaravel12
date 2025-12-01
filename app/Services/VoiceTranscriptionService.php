<?php

namespace App\Services;

use App\Models\Chat\Message;
use App\Models\VoiceTranscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceTranscriptionService
{
    /**
     * Supported audio formats
     */
    private const SUPPORTED_FORMATS = [
        'audio/mpeg', 'audio/mp3',
        'audio/wav', 'audio/wave',
        'audio/ogg', 'audio/webm',
        'audio/aac', 'audio/m4a',
    ];

    /**
     * Maximum file size in bytes (25MB)
     */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /**
     * Transcribe voice message
     */
    public function transcribeVoiceMessage(Message $message): ?VoiceTranscription
    {
        if (!$this->isVoiceMessage($message)) {
            return null;
        }

        $attachment = $message->attachments()->where('content_type', 'LIKE', 'audio/%')->first();
        if (!$attachment) {
            return null;
        }

        // Check if transcription already exists
        $existingTranscription = VoiceTranscription::where('message_id', $message->id)->first();
        if ($existingTranscription) {
            return $existingTranscription;
        }

        try {
            return $this->processTranscription($message, $attachment);
        } catch (\Exception $e) {
            Log::error('Voice transcription failed', [
                'message_id' => $message->id,
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);
            
            return VoiceTranscription::create([
                'message_id' => $message->id,
                'attachment_id' => $attachment->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process transcription using OpenAI Whisper API
     */
    private function processTranscription(Message $message, $attachment): VoiceTranscription
    {
        // Create pending transcription record
        $transcription = VoiceTranscription::create([
            'message_id' => $message->id,
            'attachment_id' => $attachment->id,
            'status' => 'processing',
            'language' => $this->detectLanguage($message),
            'provider' => 'openai-whisper',
        ]);

        // Get file from storage
        $filePath = Storage::path($attachment->file_path);
        
        if (!file_exists($filePath)) {
            throw new \Exception('Audio file not found');
        }

        // Validate file
        $this->validateAudioFile($filePath, $attachment->content_type);

        // Transcribe using OpenAI Whisper
        $transcriptionData = $this->transcribeWithWhisper($filePath, $transcription->language);

        // Update transcription with results
        $transcription->update([
            'status' => 'completed',
            'transcript' => $transcriptionData['text'] ?? '',
            'confidence' => $transcriptionData['confidence'] ?? null,
            'language' => $transcriptionData['language'] ?? $transcription->language,
            'segments' => $transcriptionData['segments'] ?? null,
            'duration' => $transcriptionData['duration'] ?? null,
            'word_count' => str_word_count($transcriptionData['text'] ?? ''),
            'processed_at' => now(),
        ]);

        // Update message with transcript for search
        if (!empty($transcriptionData['text'])) {
            $message->update([
                'searchable_content' => $transcriptionData['text'],
            ]);
        }

        return $transcription;
    }

    /**
     * Transcribe audio using OpenAI Whisper API
     */
    private function transcribeWithWhisper(string $filePath, string $language = 'auto'): array
    {
        $apiKey = config('services.openai.api_key');
        if (!$apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
        ->attach(
            'file', 
            file_get_contents($filePath), 
            basename($filePath)
        )
        ->post('https://api.openai.com/v1/audio/transcriptions', [
            'model' => 'whisper-1',
            'language' => $language === 'auto' ? null : $language,
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['word', 'segment'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Whisper API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'text' => $data['text'] ?? '',
            'language' => $data['language'] ?? $language,
            'duration' => $data['duration'] ?? null,
            'segments' => $data['segments'] ?? null,
            'words' => $data['words'] ?? null,
            'confidence' => $this->calculateAverageConfidence($data['segments'] ?? []),
        ];
    }

    /**
     * Transcribe using local Whisper installation (fallback)
     */
    private function transcribeWithLocalWhisper(string $filePath, string $language = 'auto'): array
    {
        $whisperPath = config('services.whisper.binary_path', 'whisper');
        $modelPath = config('services.whisper.model_path', 'base');
        $outputDir = storage_path('app/transcriptions/temp');
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputFile = $outputDir . '/' . uniqid('whisper_') . '.json';
        
        $command = sprintf(
            '%s "%s" --model "%s" --output_format json --output_dir "%s" --language "%s" --word_timestamps True',
            escapeshellarg($whisperPath),
            escapeshellarg($filePath),
            escapeshellarg($modelPath),
            escapeshellarg($outputDir),
            $language === 'auto' ? 'auto' : escapeshellarg($language)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Local Whisper transcription failed');
        }

        if (!file_exists($outputFile)) {
            throw new \Exception('Whisper output file not found');
        }

        $result = json_decode(file_get_contents($outputFile), true);
        unlink($outputFile);

        return [
            'text' => $result['text'] ?? '',
            'language' => $result['language'] ?? $language,
            'duration' => $result['duration'] ?? null,
            'segments' => $result['segments'] ?? null,
            'confidence' => $this->calculateAverageConfidence($result['segments'] ?? []),
        ];
    }

    /**
     * Validate audio file
     */
    private function validateAudioFile(string $filePath, string $contentType): void
    {
        if (!in_array($contentType, self::SUPPORTED_FORMATS)) {
            throw new \Exception('Unsupported audio format: ' . $contentType);
        }

        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new \Exception('Audio file too large. Maximum size: ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB');
        }

        if ($fileSize === 0) {
            throw new \Exception('Audio file is empty');
        }
    }

    /**
     * Detect message language
     */
    private function detectLanguage(Message $message): string
    {
        // Try to detect from user preferences or conversation settings
        $user = $message->sender;
        if ($user && isset($user->preferences['language'])) {
            return $user->preferences['language'];
        }

        $conversation = $message->conversation;
        if ($conversation && isset($conversation->settings['default_language'])) {
            return $conversation->settings['default_language'];
        }

        // Default to auto-detection
        return 'auto';
    }

    /**
     * Calculate average confidence from segments
     */
    private function calculateAverageConfidence(array $segments): ?float
    {
        if (empty($segments)) {
            return null;
        }

        $totalConfidence = 0;
        $count = 0;

        foreach ($segments as $segment) {
            if (isset($segment['avg_logprob'])) {
                // Convert log probability to confidence percentage
                $confidence = exp($segment['avg_logprob']) * 100;
                $totalConfidence += $confidence;
                $count++;
            }
        }

        return $count > 0 ? $totalConfidence / $count : null;
    }

    /**
     * Check if message contains voice content
     */
    private function isVoiceMessage(Message $message): bool
    {
        if ($message->content_type === 'voice') {
            return true;
        }

        return $message->attachments()
            ->where('content_type', 'LIKE', 'audio/%')
            ->exists();
    }

    /**
     * Get transcription status
     */
    public function getTranscriptionStatus(Message $message): ?array
    {
        if (!$this->isVoiceMessage($message)) {
            return null;
        }

        $transcription = VoiceTranscription::where('message_id', $message->id)->first();
        if (!$transcription) {
            return ['status' => 'not_started'];
        }

        return [
            'status' => $transcription->status,
            'progress' => $this->getTranscriptionProgress($transcription),
            'error' => $transcription->error_message,
        ];
    }

    /**
     * Get transcription progress percentage
     */
    private function getTranscriptionProgress(VoiceTranscription $transcription): int
    {
        switch ($transcription->status) {
            case 'pending':
                return 0;
            case 'processing':
                return 50;
            case 'completed':
                return 100;
            case 'failed':
                return 0;
            default:
                return 0;
        }
    }

    /**
     * Retry failed transcription
     */
    public function retryTranscription(VoiceTranscription $transcription): VoiceTranscription
    {
        if ($transcription->status !== 'failed') {
            throw new \Exception('Can only retry failed transcriptions');
        }

        $message = $transcription->message;
        $attachment = $transcription->attachment;

        $transcription->update([
            'status' => 'processing',
            'error_message' => null,
            'retry_count' => $transcription->retry_count + 1,
        ]);

        try {
            return $this->processTranscription($message, $attachment);
        } catch (\Exception $e) {
            $transcription->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete transcription
     */
    public function deleteTranscription(VoiceTranscription $transcription): bool
    {
        return $transcription->delete();
    }

    /**
     * Bulk transcribe voice messages
     */
    public function bulkTranscribe(array $messageIds, int $batchSize = 10): array
    {
        $results = [];
        $messages = Message::whereIn('id', $messageIds)
            ->with('attachments')
            ->get();

        foreach ($messages->chunk($batchSize) as $batch) {
            foreach ($batch as $message) {
                try {
                    $transcription = $this->transcribeVoiceMessage($message);
                    $results[$message->id] = [
                        'success' => true,
                        'transcription_id' => $transcription?->id,
                    ];
                } catch (\Exception $e) {
                    $results[$message->id] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                // Add delay between requests to avoid rate limits
                usleep(100000); // 100ms
            }
        }

        return $results;
    }
}
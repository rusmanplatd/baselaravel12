<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\Poll;
use App\Models\Chat\PollVote;
use App\Models\Chat\Survey;
use App\Models\Chat\SurveyQuestionResponse;
use App\Models\Chat\SurveyResponse;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class EncryptedPollService
{
    public function __construct(
        private readonly SignalProtocolService $signalService,
        private readonly QuantumCryptoService $quantumService
    ) {}

    /**
     * Create an encrypted poll
     */
    public function createPoll(
        User $creator,
        Conversation $conversation,
        array $pollData,
        string $deviceId
    ): Poll {
        try {
            // Encrypt poll question and options
            $encryptedQuestion = $this->encryptPollContent($pollData['question'], $conversation, $creator, $deviceId);

            $encryptedOptions = [];
            $optionHashes = [];

            foreach ($pollData['options'] as $option) {
                $encryptedOption = $this->encryptPollContent($option, $conversation, $creator, $deviceId);
                $encryptedOptions[] = $encryptedOption['encrypted_content'];
                $optionHashes[] = $encryptedOption['content_hash'];
            }

            // Create poll record
            $poll = Poll::create([
                'message_id' => $pollData['message_id'],
                'creator_id' => $creator->id,
                'poll_type' => $pollData['poll_type'] ?? 'single_choice',
                'encrypted_question' => $encryptedQuestion['encrypted_content'],
                'question_hash' => $encryptedQuestion['content_hash'],
                'encrypted_options' => $encryptedOptions,
                'option_hashes' => $optionHashes,
                'anonymous' => $pollData['anonymous'] ?? false,
                'allow_multiple_votes' => $pollData['allow_multiple_votes'] ?? false,
                'show_results_immediately' => $pollData['show_results_immediately'] ?? true,
                'expires_at' => $pollData['expires_at'] ?? null,
                'settings' => $pollData['settings'] ?? [],
            ]);

            Log::info('Encrypted poll created', [
                'poll_id' => $poll->id,
                'creator_id' => $creator->id,
                'conversation_id' => $conversation->id,
                'poll_type' => $poll->poll_type,
            ]);

            return $poll;

        } catch (Exception $e) {
            Log::error('Failed to create encrypted poll', [
                'creator_id' => $creator->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit an encrypted vote
     */
    public function submitVote(
        User $voter,
        Poll $poll,
        array $voteData,
        string $deviceId
    ): PollVote {
        try {
            // Check if user can vote
            if (! $poll->canVote()) {
                throw new Exception('Poll is not accepting votes');
            }

            if ($poll->hasVoted($voter->id) && ! $poll->allow_multiple_votes) {
                throw new Exception('User has already voted');
            }

            // Encrypt vote data
            $encryptedVote = $this->encryptVoteData($voteData, $poll, $voter, $deviceId);

            // Create vote record
            $vote = PollVote::create([
                'poll_id' => $poll->id,
                'voter_id' => $voter->id,
                'device_id' => $deviceId,
                'encrypted_vote_data' => $encryptedVote['encrypted_content'],
                'vote_hash' => $encryptedVote['content_hash'],
                'vote_encryption_keys' => $encryptedVote['encryption_keys'],
                'is_anonymous' => $poll->anonymous,
                'encrypted_reasoning' => $voteData['reasoning'] ?? null,
                'voted_at' => now(),
            ]);

            Log::info('Encrypted vote submitted', [
                'vote_id' => $vote->id,
                'poll_id' => $poll->id,
                'voter_id' => $voter->id,
                'is_anonymous' => $vote->is_anonymous,
            ]);

            return $vote;

        } catch (Exception $e) {
            Log::error('Failed to submit encrypted vote', [
                'poll_id' => $poll->id,
                'voter_id' => $voter->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create an encrypted survey
     */
    public function createSurvey(
        User $creator,
        Conversation $conversation,
        array $surveyData,
        string $deviceId
    ): Survey {
        try {
            // Encrypt survey title and description
            $encryptedTitle = $this->encryptPollContent($surveyData['title'], $conversation, $creator, $deviceId);
            $encryptedDescription = null;

            if (! empty($surveyData['description'])) {
                $encryptedDescription = $this->encryptPollContent($surveyData['description'], $conversation, $creator, $deviceId);
            }

            // Create survey record
            $survey = Survey::create([
                'message_id' => $surveyData['message_id'],
                'creator_id' => $creator->id,
                'encrypted_title' => $encryptedTitle['encrypted_content'],
                'title_hash' => $encryptedTitle['content_hash'],
                'encrypted_description' => $encryptedDescription['encrypted_content'] ?? null,
                'description_hash' => $encryptedDescription['content_hash'] ?? null,
                'anonymous' => $surveyData['anonymous'] ?? false,
                'allow_partial_responses' => $surveyData['allow_partial_responses'] ?? true,
                'randomize_questions' => $surveyData['randomize_questions'] ?? false,
                'expires_at' => $surveyData['expires_at'] ?? null,
                'settings' => $surveyData['settings'] ?? [],
            ]);

            // Create survey questions
            foreach ($surveyData['questions'] as $index => $questionData) {
                $this->createSurveyQuestion($survey, $questionData, $index, $conversation, $creator, $deviceId);
            }

            Log::info('Encrypted survey created', [
                'survey_id' => $survey->id,
                'creator_id' => $creator->id,
                'conversation_id' => $conversation->id,
                'question_count' => count($surveyData['questions']),
            ]);

            return $survey;

        } catch (Exception $e) {
            Log::error('Failed to create encrypted survey', [
                'creator_id' => $creator->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit encrypted survey response
     */
    public function submitSurveyResponse(
        User $respondent,
        Survey $survey,
        array $responseData,
        string $deviceId
    ): SurveyResponse {
        try {
            // Check if user can respond
            if (! $survey->canRespond()) {
                throw new Exception('Survey is not accepting responses');
            }

            // Create or update survey response
            $surveyResponse = SurveyResponse::updateOrCreate([
                'survey_id' => $survey->id,
                'respondent_id' => $respondent->id,
            ], [
                'device_id' => $deviceId,
                'is_anonymous' => $survey->anonymous,
                'started_at' => now(),
                'response_encryption_keys' => $this->generateResponseEncryptionKeys($survey, $respondent, $deviceId),
            ]);

            // Submit answers for each question
            foreach ($responseData['answers'] as $questionId => $answer) {
                $this->submitQuestionResponse($surveyResponse, $questionId, $answer, $survey, $respondent, $deviceId);
            }

            // Mark as complete if all required questions answered
            if ($responseData['is_complete'] ?? false) {
                $surveyResponse->markAsComplete();
            }

            Log::info('Encrypted survey response submitted', [
                'response_id' => $surveyResponse->id,
                'survey_id' => $survey->id,
                'respondent_id' => $respondent->id,
                'is_complete' => $surveyResponse->is_complete,
            ]);

            return $surveyResponse;

        } catch (Exception $e) {
            Log::error('Failed to submit encrypted survey response', [
                'survey_id' => $survey->id,
                'respondent_id' => $respondent->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt poll results for authorized users
     */
    public function decryptPollResults(Poll $poll, User $user, string $deviceId): array
    {
        try {
            if (! $poll->canViewResults($user)) {
                throw new Exception('User not authorized to view poll results');
            }

            $results = [];
            $votes = $poll->votes()->get();

            foreach ($votes as $vote) {
                $decryptedVote = $this->decryptVoteData($vote, $user, $deviceId);
                $results[] = [
                    'vote_id' => $vote->id,
                    'voter_id' => $vote->is_anonymous ? null : $vote->voter_id,
                    'choices' => $decryptedVote['choices'],
                    'reasoning' => $decryptedVote['reasoning'] ?? null,
                    'voted_at' => $vote->voted_at,
                ];
            }

            return [
                'poll_id' => $poll->id,
                'total_votes' => count($results),
                'votes' => $results,
                'aggregated_results' => $this->aggregatePollResults($poll, $results),
            ];

        } catch (Exception $e) {
            Log::error('Failed to decrypt poll results', [
                'poll_id' => $poll->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate analytics for polls (aggregated, anonymous data)
     */
    public function generatePollAnalytics(Poll $poll): array
    {
        try {
            $totalVotes = $poll->getTotalVotes();
            $totalParticipants = $poll->message->conversation->participants()->count();

            $optionCounts = [];
            foreach ($poll->encrypted_options as $index => $option) {
                $optionCounts[$index] = $poll->getVoteCount($index);
            }

            $analytics = [
                'total_votes' => $totalVotes,
                'total_participants' => $totalParticipants,
                'participation_rate' => $totalParticipants > 0 ? ($totalVotes / $totalParticipants) * 100 : 0,
                'option_distribution' => $optionCounts,
                'voting_timeline' => $this->getVotingTimeline($poll),
                'completion_time_avg' => $this->getAverageVotingTime($poll),
            ];

            return $analytics;

        } catch (Exception $e) {
            Log::error('Failed to generate poll analytics', [
                'poll_id' => $poll->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Private helper methods
     */
    private function encryptPollContent(string $content, Conversation $conversation, User $creator, string $deviceId): array
    {
        return $this->signalService->encryptMessage(
            $creator,
            $conversation,
            $content,
            $deviceId,
            ['message_type' => 'poll_content']
        );
    }

    private function encryptVoteData(array $voteData, Poll $poll, User $voter, string $deviceId): array
    {
        $voteContent = json_encode([
            'choices' => $voteData['choices'],
            'reasoning' => $voteData['reasoning'] ?? null,
        ]);

        return $this->signalService->encryptMessage(
            $voter,
            $poll->message->conversation,
            $voteContent,
            $deviceId,
            ['message_type' => 'poll_vote']
        );
    }

    private function decryptVoteData(PollVote $vote, User $user, string $deviceId): array
    {
        $decryptedContent = $this->signalService->decryptMessage(
            $user,
            $vote->poll->message->conversation,
            $vote->encrypted_vote_data,
            $deviceId
        );

        return json_decode($decryptedContent, true);
    }

    private function createSurveyQuestion(Survey $survey, array $questionData, int $order, Conversation $conversation, User $creator, string $deviceId): void
    {
        $encryptedQuestion = $this->encryptPollContent($questionData['question_text'], $conversation, $creator, $deviceId);

        $encryptedOptions = null;
        $optionHashes = null;

        if (! empty($questionData['options'])) {
            $encryptedOptions = [];
            $optionHashes = [];

            foreach ($questionData['options'] as $option) {
                $encryptedOption = $this->encryptPollContent($option, $conversation, $creator, $deviceId);
                $encryptedOptions[] = $encryptedOption['encrypted_content'];
                $optionHashes[] = $encryptedOption['content_hash'];
            }
        }

        $survey->questions()->create([
            'question_order' => $order,
            'question_type' => $questionData['question_type'],
            'encrypted_question_text' => $encryptedQuestion['encrypted_content'],
            'question_hash' => $encryptedQuestion['content_hash'],
            'required' => $questionData['required'] ?? false,
            'encrypted_options' => $encryptedOptions,
            'option_hashes' => $optionHashes,
            'validation_rules' => $questionData['validation_rules'] ?? null,
            'settings' => $questionData['settings'] ?? null,
        ]);
    }

    private function submitQuestionResponse(SurveyResponse $surveyResponse, string $questionId, string $answer, Survey $survey, User $respondent, string $deviceId): void
    {
        $encryptedAnswer = $this->encryptPollContent($answer, $survey->message->conversation, $respondent, $deviceId);

        SurveyQuestionResponse::updateOrCreate([
            'survey_response_id' => $surveyResponse->id,
            'question_id' => $questionId,
        ], [
            'encrypted_answer' => $encryptedAnswer['encrypted_content'],
            'answer_hash' => $encryptedAnswer['content_hash'],
            'answered_at' => now(),
        ]);
    }

    private function generateResponseEncryptionKeys(Survey $survey, User $respondent, string $deviceId): array
    {
        // Generate encryption keys for survey responses
        return [
            'primary_key' => base64_encode(random_bytes(32)),
            'device_id' => $deviceId,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function aggregatePollResults(Poll $poll, array $votes): array
    {
        $optionCounts = [];
        $totalVotes = count($votes);

        foreach ($votes as $vote) {
            foreach ($vote['choices'] as $choice) {
                $optionCounts[$choice] = ($optionCounts[$choice] ?? 0) + 1;
            }
        }

        $results = [];
        foreach ($optionCounts as $option => $count) {
            $results[$option] = [
                'count' => $count,
                'percentage' => $totalVotes > 0 ? ($count / $totalVotes) * 100 : 0,
            ];
        }

        return $results;
    }

    private function getVotingTimeline(Poll $poll): array
    {
        return $poll->votes()
            ->selectRaw('DATE(voted_at) as date, COUNT(*) as votes')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getAverageVotingTime(Poll $poll): float
    {
        // Placeholder - would calculate average time from poll creation to vote
        return 0.0;
    }
}

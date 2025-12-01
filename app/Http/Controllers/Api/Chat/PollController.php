<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Poll;
use App\Models\User;
use App\Services\EncryptedPollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    public function __construct(
        private readonly EncryptedPollService $pollService
    ) {}

    public function create(Request $request, string $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message_id' => 'required|string|exists:chat_messages,id',
                'device_id' => 'required|string',
                'question' => 'required|string|max:1000',
                'options' => 'required|array|min:2|max:10',
                'options.*' => 'required|string|max:500',
                'poll_type' => 'in:single_choice,multiple_choice,rating',
                'anonymous' => 'boolean',
                'allow_multiple_votes' => 'boolean',
                'show_results_immediately' => 'boolean',
                'expires_at' => 'nullable|date|after:now',
                'settings' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $deviceId = $request->input('device_id');

            DB::beginTransaction();

            $poll = $this->pollService->createPoll(
                $user,
                $conversation,
                $request->all(),
                $deviceId
            );

            DB::commit();

            Log::info('Poll created', [
                'poll_id' => $poll->id,
                'creator_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            return response()->json([
                'success' => true,
                'poll' => [
                    'id' => $poll->id,
                    'message_id' => $poll->message_id,
                    'creator_id' => $poll->creator_id,
                    'poll_type' => $poll->poll_type,
                    'anonymous' => $poll->anonymous,
                    'allow_multiple_votes' => $poll->allow_multiple_votes,
                    'show_results_immediately' => $poll->show_results_immediately,
                    'expires_at' => $poll->expires_at,
                    'is_closed' => $poll->is_closed,
                    'created_at' => $poll->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Poll creation failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create poll',
            ], 500);
        }
    }

    public function vote(Request $request, string $conversationId, string $pollId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'choices' => 'required|array|min:1',
                'choices.*' => 'integer|min:0',
                'reasoning' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $poll = Poll::findOrFail($pollId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            // Verify poll belongs to conversation
            if ($poll->message->conversation_id !== $conversation->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Poll not found in this conversation',
                ], 404);
            }

            $deviceId = $request->input('device_id');
            $voteData = [
                'choices' => $request->input('choices'),
                'reasoning' => $request->input('reasoning'),
            ];

            DB::beginTransaction();

            $vote = $this->pollService->submitVote($user, $poll, $voteData, $deviceId);

            DB::commit();

            Log::info('Vote submitted', [
                'vote_id' => $vote->id,
                'poll_id' => $poll->id,
                'voter_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'vote' => [
                    'id' => $vote->id,
                    'poll_id' => $vote->poll_id,
                    'is_anonymous' => $vote->is_anonymous,
                    'voted_at' => $vote->voted_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vote submission failed', [
                'user_id' => Auth::id(),
                'poll_id' => $pollId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $conversationId, string $pollId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $poll = Poll::with(['creator', 'votes'])->findOrFail($pollId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $deviceId = $request->input('device_id');

            // Get poll details
            $pollData = [
                'id' => $poll->id,
                'message_id' => $poll->message_id,
                'creator' => [
                    'id' => $poll->creator->id,
                    'name' => $poll->creator->name,
                ],
                'poll_type' => $poll->poll_type,
                'anonymous' => $poll->anonymous,
                'allow_multiple_votes' => $poll->allow_multiple_votes,
                'show_results_immediately' => $poll->show_results_immediately,
                'expires_at' => $poll->expires_at,
                'is_closed' => $poll->is_closed,
                'closed_at' => $poll->closed_at,
                'total_votes' => $poll->getTotalVotes(),
                'participation_rate' => $poll->getParticipationRate(),
                'user_has_voted' => $poll->hasVoted($user->id),
                'can_vote' => $poll->canVote(),
                'can_view_results' => $poll->canViewResults($user),
                'created_at' => $poll->created_at,
            ];

            // Include results if user can view them
            if ($poll->canViewResults($user)) {
                try {
                    $results = $this->pollService->decryptPollResults($poll, $user, $deviceId);
                    $pollData['results'] = $results;
                } catch (\Exception $e) {
                    Log::warning('Could not decrypt poll results for user', [
                        'poll_id' => $poll->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    $pollData['results'] = null;
                }
            }

            return response()->json([
                'success' => true,
                'poll' => $pollData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve poll', [
                'user_id' => Auth::id(),
                'poll_id' => $pollId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve poll',
            ], 500);
        }
    }

    public function results(Request $request, string $conversationId, string $pollId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $poll = Poll::findOrFail($pollId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            if (! $poll->canViewResults($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not authorized to view results',
                ], 403);
            }

            $deviceId = $request->input('device_id');
            $results = $this->pollService->decryptPollResults($poll, $user, $deviceId);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve poll results', [
                'user_id' => Auth::id(),
                'poll_id' => $pollId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve poll results',
            ], 500);
        }
    }

    public function close(Request $request, string $conversationId, string $pollId): JsonResponse
    {
        try {
            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $poll = Poll::findOrFail($pollId);

            // Verify user is creator or has moderator permissions
            if ($poll->creator_id !== $user->id) {
                $participant = $conversation->participants()->where('user_id', $user->id)->first();
                if (! $participant || ! $participant->isModerator()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Not authorized to close this poll',
                    ], 403);
                }
            }

            $poll->close($user);

            Log::info('Poll closed', [
                'poll_id' => $poll->id,
                'closed_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'poll' => [
                    'id' => $poll->id,
                    'is_closed' => $poll->is_closed,
                    'closed_at' => $poll->closed_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to close poll', [
                'user_id' => Auth::id(),
                'poll_id' => $pollId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to close poll',
            ], 500);
        }
    }

    public function analytics(Request $request, string $conversationId, string $pollId): JsonResponse
    {
        try {
            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $poll = Poll::findOrFail($pollId);

            // Verify user is creator or has moderator permissions
            if ($poll->creator_id !== $user->id) {
                $participant = $conversation->participants()->where('user_id', $user->id)->first();
                if (! $participant || ! $participant->isModerator()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Not authorized to view analytics',
                    ], 403);
                }
            }

            $analytics = $this->pollService->generatePollAnalytics($poll);

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate poll analytics', [
                'user_id' => Auth::id(),
                'poll_id' => $pollId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate analytics',
            ], 500);
        }
    }

    public function list(Request $request, string $conversationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $polls = Poll::where('message_id', 'in', function ($query) use ($conversation) {
                $query->select('id')->from('chat_messages')->where('conversation_id', $conversation->id);
            })
                ->with(['creator'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $pollsData = $polls->items();
            $formattedPolls = array_map(function ($poll) use ($user) {
                return [
                    'id' => $poll->id,
                    'message_id' => $poll->message_id,
                    'creator' => [
                        'id' => $poll->creator->id,
                        'name' => $poll->creator->name,
                    ],
                    'poll_type' => $poll->poll_type,
                    'anonymous' => $poll->anonymous,
                    'expires_at' => $poll->expires_at,
                    'is_closed' => $poll->is_closed,
                    'total_votes' => $poll->getTotalVotes(),
                    'user_has_voted' => $poll->hasVoted($user->id),
                    'can_vote' => $poll->canVote(),
                    'created_at' => $poll->created_at,
                ];
            }, $pollsData);

            return response()->json([
                'success' => true,
                'polls' => $formattedPolls,
                'pagination' => [
                    'current_page' => $polls->currentPage(),
                    'per_page' => $polls->perPage(),
                    'total' => $polls->total(),
                    'last_page' => $polls->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list polls', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve polls',
            ], 500);
        }
    }
}

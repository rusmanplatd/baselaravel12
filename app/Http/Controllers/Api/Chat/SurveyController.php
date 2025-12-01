<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Survey;
use App\Models\User;
use App\Services\EncryptedPollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
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
                'title' => 'required|string|max:500',
                'description' => 'nullable|string|max:2000',
                'questions' => 'required|array|min:1|max:50',
                'questions.*.question_text' => 'required|string|max:1000',
                'questions.*.question_type' => 'required|in:text,multiple_choice,single_choice,rating,date,email,number,file',
                'questions.*.required' => 'boolean',
                'questions.*.options' => 'nullable|array',
                'questions.*.options.*' => 'string|max:500',
                'questions.*.validation_rules' => 'nullable|array',
                'questions.*.settings' => 'nullable|array',
                'anonymous' => 'boolean',
                'allow_partial_responses' => 'boolean',
                'randomize_questions' => 'boolean',
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

            $survey = $this->pollService->createSurvey(
                $user,
                $conversation,
                $request->all(),
                $deviceId
            );

            DB::commit();

            Log::info('Survey created', [
                'survey_id' => $survey->id,
                'creator_id' => $user->id,
                'conversation_id' => $conversation->id,
                'question_count' => count($request->input('questions')),
            ]);

            return response()->json([
                'success' => true,
                'survey' => [
                    'id' => $survey->id,
                    'message_id' => $survey->message_id,
                    'creator_id' => $survey->creator_id,
                    'anonymous' => $survey->anonymous,
                    'allow_partial_responses' => $survey->allow_partial_responses,
                    'randomize_questions' => $survey->randomize_questions,
                    'expires_at' => $survey->expires_at,
                    'is_closed' => $survey->is_closed,
                    'total_questions' => $survey->getTotalQuestions(),
                    'created_at' => $survey->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Survey creation failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create survey',
            ], 500);
        }
    }

    public function respond(Request $request, string $conversationId, string $surveyId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'answers' => 'required|array',
                'answers.*' => 'string|max:5000',
                'is_complete' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $survey = Survey::findOrFail($surveyId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            // Verify survey belongs to conversation
            if ($survey->message->conversation_id !== $conversation->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Survey not found in this conversation',
                ], 404);
            }

            $deviceId = $request->input('device_id');
            $responseData = [
                'answers' => $request->input('answers'),
                'is_complete' => $request->boolean('is_complete', false),
            ];

            DB::beginTransaction();

            $surveyResponse = $this->pollService->submitSurveyResponse(
                $user,
                $survey,
                $responseData,
                $deviceId
            );

            DB::commit();

            Log::info('Survey response submitted', [
                'response_id' => $surveyResponse->id,
                'survey_id' => $survey->id,
                'respondent_id' => $user->id,
                'is_complete' => $surveyResponse->is_complete,
            ]);

            return response()->json([
                'success' => true,
                'response' => [
                    'id' => $surveyResponse->id,
                    'survey_id' => $surveyResponse->survey_id,
                    'is_complete' => $surveyResponse->is_complete,
                    'is_anonymous' => $surveyResponse->is_anonymous,
                    'progress' => $surveyResponse->getProgress(),
                    'started_at' => $surveyResponse->started_at,
                    'completed_at' => $surveyResponse->completed_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Survey response submission failed', [
                'user_id' => Auth::id(),
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $conversationId, string $surveyId): JsonResponse
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
            $survey = Survey::with(['creator', 'questions', 'responses'])->findOrFail($surveyId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $deviceId = $request->input('device_id');
            $userResponse = $survey->getUserResponse($user->id);

            // Get survey details
            $surveyData = [
                'id' => $survey->id,
                'message_id' => $survey->message_id,
                'creator' => [
                    'id' => $survey->creator->id,
                    'name' => $survey->creator->name,
                ],
                'anonymous' => $survey->anonymous,
                'allow_partial_responses' => $survey->allow_partial_responses,
                'randomize_questions' => $survey->randomize_questions,
                'expires_at' => $survey->expires_at,
                'is_closed' => $survey->is_closed,
                'closed_at' => $survey->closed_at,
                'total_questions' => $survey->getTotalQuestions(),
                'required_questions' => $survey->getRequiredQuestions(),
                'total_responses' => $survey->getTotalResponses(),
                'completion_rate' => $survey->getCompletionRate(),
                'participation_rate' => $survey->getParticipationRate(),
                'user_has_responded' => ! is_null($userResponse),
                'user_response_progress' => $userResponse ? $userResponse->getProgress() : null,
                'can_respond' => $survey->canRespond() && (is_null($userResponse) || $survey->allowsPartialResponses()),
                'can_view_results' => $survey->canViewResults($user),
                'created_at' => $survey->created_at,
            ];

            return response()->json([
                'success' => true,
                'survey' => $surveyData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve survey', [
                'user_id' => Auth::id(),
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve survey',
            ], 500);
        }
    }

    public function results(Request $request, string $conversationId, string $surveyId): JsonResponse
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
            $survey = Survey::with(['questions', 'responses.questionResponses'])->findOrFail($surveyId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            if (! $survey->canViewResults($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not authorized to view results',
                ], 403);
            }

            // Generate aggregated results (preserving anonymity)
            $results = [
                'total_responses' => $survey->getTotalResponses(),
                'complete_responses' => $survey->getCompleteResponses(),
                'partial_responses' => $survey->getPartialResponses(),
                'completion_rate' => $survey->getCompletionRate(),
                'participation_rate' => $survey->getParticipationRate(),
                'question_analytics' => [],
            ];

            // Add per-question analytics
            foreach ($survey->questions as $question) {
                $results['question_analytics'][] = [
                    'question_id' => $question->id,
                    'question_order' => $question->question_order,
                    'question_type' => $question->question_type,
                    'required' => $question->required,
                    'total_responses' => $question->getTotalResponses(),
                    'response_rate' => $question->getResponseRate(),
                ];
            }

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve survey results', [
                'user_id' => Auth::id(),
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve survey results',
            ], 500);
        }
    }

    public function close(Request $request, string $conversationId, string $surveyId): JsonResponse
    {
        try {
            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $survey = Survey::findOrFail($surveyId);

            // Verify user is creator or has moderator permissions
            if ($survey->creator_id !== $user->id) {
                $participant = $conversation->participants()->where('user_id', $user->id)->first();
                if (! $participant || ! $participant->isModerator()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Not authorized to close this survey',
                    ], 403);
                }
            }

            $survey->close($user);

            Log::info('Survey closed', [
                'survey_id' => $survey->id,
                'closed_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'survey' => [
                    'id' => $survey->id,
                    'is_closed' => $survey->is_closed,
                    'closed_at' => $survey->closed_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to close survey', [
                'user_id' => Auth::id(),
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to close survey',
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

            $surveys = Survey::where('message_id', 'in', function ($query) use ($conversation) {
                $query->select('id')->from('chat_messages')->where('conversation_id', $conversation->id);
            })
                ->with(['creator'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $surveysData = $surveys->items();
            $formattedSurveys = array_map(function ($survey) use ($user) {
                $userResponse = $survey->getUserResponse($user->id);

                return [
                    'id' => $survey->id,
                    'message_id' => $survey->message_id,
                    'creator' => [
                        'id' => $survey->creator->id,
                        'name' => $survey->creator->name,
                    ],
                    'anonymous' => $survey->anonymous,
                    'expires_at' => $survey->expires_at,
                    'is_closed' => $survey->is_closed,
                    'total_questions' => $survey->getTotalQuestions(),
                    'total_responses' => $survey->getTotalResponses(),
                    'user_has_responded' => ! is_null($userResponse),
                    'user_response_complete' => $userResponse ? $userResponse->is_complete : false,
                    'can_respond' => $survey->canRespond(),
                    'created_at' => $survey->created_at,
                ];
            }, $surveysData);

            return response()->json([
                'success' => true,
                'surveys' => $formattedSurveys,
                'pagination' => [
                    'current_page' => $surveys->currentPage(),
                    'per_page' => $surveys->perPage(),
                    'total' => $surveys->total(),
                    'last_page' => $surveys->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list surveys', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve surveys',
            ], 500);
        }
    }
}

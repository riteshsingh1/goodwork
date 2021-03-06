<?php

namespace App\Core\Http\Controllers;

use Exception;
use App\Core\Models\Message;
use App\Core\Utilities\GroupTrait;
use App\Core\Events\MessageCreated;
use App\Core\Events\MessageUpdated;
use App\Core\Repositories\MentionRepository;
use App\Core\Repositories\MessageRepository;
use App\Core\Http\Requests\StoreMessageRequest;

class MessageController extends Controller
{
    use GroupTrait;

    public function index(MessageRepository $repository)
    {
        try {
            $group = $this->getGroupModel();
            if ($group->notOpenForPublic()) {
                abort(401);
            } elseif (auth()->user()) {
                $this->authorize('view', $group);
            }
            $messages = $repository->getAllMessages(request('group_type'), request('group_id'));

            return response()->json([
                'status'   => 'success',
                'total'    => count($messages),
                'messages' => $messages,
            ]);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function store(StoreMessageRequest $request, MessageRepository $repository, MentionRepository $mentionRepository)
    {
        try {
            $this->authorize('create', Message::class);
            $message = $repository->saveMessage([
                'body'             => $request->get('message'),
                'user_id'          => auth()->user()->id,
                'messageable_type' => $request->get('group_type'),
                'messageable_id'   => $request->get('group_id'),
            ]);
            if (request('mentions')) {
                $mentionRepository->create('message', $message);
            }
            event(new MessageCreated($message));
            $message->load('user');

            return response()->json([
                'status'  => 'success',
                'message' => $message,
            ]);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function update(Message $message, MentionRepository $mentionRepository)
    {
        try {
            $message->update(['body' => request('message')]);

            if (request('mentions')) {
                $mentionRepository->create('message', $message);
            }

            event(new MessageUpdated($message));

            $message->load('user');

            return response()->json([
                'status'  => 'success',
                'message' => $message,
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function delete(Message $message)
    {
        $message->delete();

        return response()->json([
            'status'  => 'success',
            'message' => localize('misc.Message has been deleted'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\CalendarRequest;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendarService) {}

    public function index(CalendarRequest $request): JsonResponse
    {
        $filters = $request->validated();

        // Collaborateur : forcer le filtre sur ses propres missions
        if ($request->user()->hasRole('collaborateur')) {
            $filters['collaborateur_id'] = $request->user()->id;
        }

        return response()->json([
            'data' => $this->calendarService->getEvents($filters),
        ]);
    }
}

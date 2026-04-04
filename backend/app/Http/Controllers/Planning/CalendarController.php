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
        return response()->json([
            'data' => $this->calendarService->getEvents($request->validated()),
        ]);
    }
}

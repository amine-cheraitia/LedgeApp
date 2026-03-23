<?php

namespace App\Http\Controllers\Exercices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exercices\StoreExerciceRequest;
use App\Http\Requests\Exercices\UpdateExerciceRequest;
use App\Http\Resources\Exercices\ExerciceResource;
use App\Models\Exercice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExerciceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $exercices = Exercice::latest('annee')->get();

        return ExerciceResource::collection($exercices);
    }

    public function store(StoreExerciceRequest $request): JsonResponse
    {
        $exercice = Exercice::create($request->validated());

        return (new ExerciceResource($exercice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Exercice $exercice): ExerciceResource
    {
        return new ExerciceResource($exercice);
    }

    public function update(UpdateExerciceRequest $request, Exercice $exercice): ExerciceResource
    {
        $exercice->update($request->validated());

        return new ExerciceResource($exercice);
    }

    public function current(): ExerciceResource
    {
        return new ExerciceResource(Exercice::current());
    }
}

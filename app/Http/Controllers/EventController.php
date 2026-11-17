<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        // Resolving the filter to a case first keeps an unknown value from
        // reaching the query, and lets the listing reuse the ofType scope.
        $selectedType = EventType::tryFrom($request->string('type')->toString());
        $search = $request->string('search', '');

        $events = Event::query()
            ->with(['client', 'loan', 'installment', 'payment', 'creator'])
            ->when($selectedType, fn ($query) => $query->ofType($selectedType))
            ->when($search->isNotEmpty(), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->search($search)
            ))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'types' => EventType::cases(),
            'type' => $selectedType?->value ?? 'all',
            'search' => $search->toString(),
        ]);
    }
}

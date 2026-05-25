<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/tags/index', [
            'tags' => Tag::withCount('products')->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/tags/create');
    }

    public function store(TagRequest $request)
    {
        Tag::create($request->validated());
        return redirect()->route('admin.tags.index')->with('success', 'Etiqueta creada.');
    }

    public function edit(Tag $tag): Response
    {
        return Inertia::render('admin/tags/edit', compact('tag'));
    }

    public function update(TagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());
        return redirect()->route('admin.tags.index')->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return redirect()->route('admin.tags.index')->with('success', 'Etiqueta eliminada.');
    }
}

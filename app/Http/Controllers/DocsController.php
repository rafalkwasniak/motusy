<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves hand-written documentation pages from the docs directory, which lives
 * outside the webroot next to the specification.
 *
 * Anyone can drop a new .html file into docs/ and it is reachable immediately, with
 * no route to add. Only .html is served, so the markdown specification and any config
 * sitting in the same directory stay unreachable.
 */
class DocsController extends Controller
{
    public function show(string $page): Response
    {
        // The route already constrains the slug to [A-Za-z0-9_-]+, which leaves no
        // slashes or dots to climb out of the directory. realpath is the second lock:
        // it resolves the final path and we refuse anything landing outside docs/.
        $directory = realpath(base_path('docs'));
        $path = realpath($directory.DIRECTORY_SEPARATOR.$page.'.html');

        if ($path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException();
        }

        return response(file_get_contents($path))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\ReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Longhand\Identity\Models\Workspace;

final readonly class ShowWorkspace
{
    public function __construct(private Document $document, private ReadAccess $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->access->require(ApiActor::of($request), 'workspace.read', 'workspace:read');

        return $this->document->resource(
            $request,
            Workspace::query()->findOrFail(CurrentMember::of($request)->workspace_id),
            QueryParameters::from($request),
        );
    }
}

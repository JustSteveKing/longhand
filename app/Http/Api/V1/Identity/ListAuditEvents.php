<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Identity;

use App\Exceptions\ErrorCode;
use App\Http\Api\ApiActor;
use App\Http\Api\CurrentMember;
use App\Http\Api\JsonApi\ApiError;
use App\Http\Api\JsonApi\CursorPage;
use App\Http\Api\JsonApi\Document;
use App\Http\Api\JsonApi\QueryParameters;
use App\Http\Api\ReadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Longhand\Identity\Audit\AuditEvent;
use Longhand\Identity\Models\Member;
use Throwable;

/**
 * GET /v1/audit-events: owners and admins, newest first (RFC 0003). No
 * agent can hold audit:read.
 */
final readonly class ListAuditEvents
{
    public function __construct(private Document $document, private ReadAccess $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->access->require(ApiActor::of($request), 'audit.read', 'audit:read');

        $parameters = QueryParameters::from(
            $request,
            includes: ['actor'],
            filters: ['actor', 'kind', 'action', 'outcome', 'surface', 'subject', 'occurred_after', 'occurred_before'],
            sorts: ['occurred_at'],
            defaultSort: ['-occurred_at'],
            paginated: true,
        );

        $workspaceId = CurrentMember::of($request)->workspace_id;
        $query = AuditEvent::query()->where('workspace_id', $workspaceId);

        foreach (['actor' => 'actor_id', 'action' => 'action', 'outcome' => 'outcome', 'surface' => 'surface', 'subject' => 'subject_id'] as $filter => $column) {
            if (($values = $parameters->filterList($filter)) !== null) {
                $query->whereIn($column, $values);
            }
        }

        if (($kinds = $parameters->filterList('kind')) !== null) {
            $query->whereIn('actor_id', Member::query()->select('id')->where('workspace_id', $workspaceId)->whereIn('kind', $kinds));
        }

        foreach (['occurred_after' => '>', 'occurred_before' => '<'] as $filter => $operator) {
            if (isset($parameters->filters[$filter])) {
                $query->where('occurred_at', $operator, $this->time($parameters->filters[$filter], $filter));
            }
        }

        $page = CursorPage::of($query, $parameters, ['occurred_at' => 'occurred_at'], 'id');

        return $this->document->collection($request, $page, $parameters);
    }

    private function time(string $value, string $filter): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new ApiError(ErrorCode::InvalidQueryParameter, "filter[{$filter}] must be an RFC 3339 time.", source: ['parameter' => "filter[{$filter}]"]);
        }
    }
}

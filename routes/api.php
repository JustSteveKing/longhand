<?php

use App\Http\Api\Middleware\AssignRequestId;
use App\Http\Api\Middleware\AuthenticateMember;
use App\Http\Api\Middleware\IdempotencyKeys;
use App\Http\Api\Middleware\NegotiateJsonApi;
use App\Http\Api\Middleware\RateLimitHeaders;
use App\Http\Api\V1\Identity;
use Illuminate\Support\Facades\Route;

/*
 * The REST API, JSON:API 1.1 at /v1 (RFC 0002, api/openapi.yaml).
 */
Route::middleware([
    AssignRequestId::class,
    NegotiateJsonApi::class,
    AuthenticateMember::class,
    RateLimitHeaders::class,
    IdempotencyKeys::class,
])->group(function (): void {
    Route::get('me', Identity\ShowMe::class);

    Route::get('workspace', Identity\ShowWorkspace::class);
    Route::patch('workspace', Identity\UpdateWorkspaceController::class);

    Route::get('members', Identity\ListMembers::class);
    Route::post('members', Identity\CreateAgentController::class);
    Route::get('members/{member}', Identity\ShowMember::class);
    Route::patch('members/{member}', Identity\UpdateMemberController::class);
    Route::post('members/{member}/credentials', Identity\RotateCredentialsController::class);

    Route::get('invitations', Identity\ListInvitations::class);
    Route::post('invitations', Identity\CreateInvitationController::class);
    Route::patch('invitations/{invitation}', Identity\ResendInvitationController::class);
    Route::delete('invitations/{invitation}', Identity\RevokeInvitationController::class);

    Route::get('audit-events', Identity\ListAuditEvents::class);
});

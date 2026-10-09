<?php

declare(strict_types=1);

namespace Longhand\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A domain whose verified addresses may join a workspace without an
 * invitation, as members (RFC 0003).
 *
 * @property int $id
 * @property string $workspace_id
 * @property string $domain
 * @property string $added_by_id
 * @property Carbon $created_at
 */
final class EmailDomain extends Model
{
    public const array PUBLIC_PROVIDERS = [
        'aol.com', 'fastmail.com', 'gmail.com', 'gmx.com', 'googlemail.com', 'hey.com',
        'hotmail.com', 'icloud.com', 'live.com', 'mail.com', 'me.com', 'msn.com',
        'outlook.com', 'pm.me', 'proton.me', 'protonmail.com', 'yahoo.com', 'yandex.com',
        'zoho.com',
    ];

    public const null UPDATED_AT = null;

    protected $table = 'workspace_email_domains';

    /** @var list<string> */
    protected $guarded = [];
}

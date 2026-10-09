<?php

declare(strict_types=1);

namespace Longhand\Shared\Actions;

use Illuminate\Database\Eloquent\Model;

/**
 * An Action result that is not itself a model, but is about one; the
 * audit log records that model as the subject.
 */
interface HasSubject
{
    public function subject(): Model;
}

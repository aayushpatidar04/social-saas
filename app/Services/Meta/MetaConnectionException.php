<?php

namespace App\Services\Meta;

/** A problem that is safe to show to the user (bad config, limit reached, token refused ...). */
class MetaConnectionException extends \RuntimeException
{
}

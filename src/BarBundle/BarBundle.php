<?php

declare(strict_types=1);

namespace App\BarBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle that provides the `bar:hi` command,
 * which is registered as a member of the foo:hello chain.
 */
final class BarBundle extends Bundle
{
}

<?php
declare(strict_types=1);
namespace App\Services;
use Illuminate\Session\DatabaseSessionHandler;
final class MinimalDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function addRequestInformation(&$payload): static { return $this; }
}

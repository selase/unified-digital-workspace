<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a tenant tries to enable a module whose required features
 * aren't part of their package (and haven't been granted directly via
 * tenant_features). Distinct from ModuleDependencyException so callers
 * can prompt the tenant to upgrade rather than to enable a sibling module.
 */
final class ModuleEntitlementException extends Exception {}

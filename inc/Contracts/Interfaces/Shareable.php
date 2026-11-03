<?php
/**
 * Interface for Shareable classes.
 *
 * Shareable is a SOFT ANTI-PATTERN. It introduces hidden shared state similar
 * to a Singleton. Use only when a hooked class genuinely must be retrieved
 * later.
 *
 * Shareable classes are those whose instance should be reused. When the Loader
 * meets a Shareable, it caches the instance in a Container for later retrieval
 * via get_shared(). Absence of this interface means a fresh, non-shared
 * instance — the default. It is a marker: implement it, nothing more.
 *
 * Implemented alongside Registrable when a hooked class must also be shared,
 * or on its own for a plain shared service.
 *
 * @package rtCamp\WPPrimitives\Contracts\Interfaces
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Interfaces;

/**
 * Interface - Shareable
 */
interface Shareable {}

<?php
/**
 * Singleton fixture that keeps the trait's default constructor.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

/**
 * Class - BareSingleton
 */
class BareSingleton {

	// phpcs:ignore SlevomatCodingStandard.Classes.TraitUseSpacing.IncorrectLinesCountAfterLastUse -- @todo fix upstream config conflict with trait spacing.
	use Singleton;
}

<?php
/**
 * PHPStan type tests for the Singleton trait.
 *
 * Analysed by PHPStan only; never executed.
 *
 * @package rtCamp\WPPrimitives\Tests\PHPStan
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\PHPStan;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;
use function PHPStan\Testing\assertType;

/**
 * A minimal class using the Singleton trait.
 */
final class SingletonFixture {

	// phpcs:ignore SlevomatCodingStandard.Classes.TraitUseSpacing.IncorrectLinesCountAfterLastUse -- @todo fix upstream config conflict with trait spacing.
	use Singleton;
}

/**
 * A singleton that seeds the instance from its own constructor.
 */
final class ReentrantSingletonFixture {

	use Singleton;

	/**
	 * Assign the instance before doing work that may call get_instance().
	 */
	protected function __construct() {
		self::$instance = $this;
	}
}

assertType( SingletonFixture::class, SingletonFixture::get_instance() );
assertType( ReentrantSingletonFixture::class, ReentrantSingletonFixture::get_instance() );

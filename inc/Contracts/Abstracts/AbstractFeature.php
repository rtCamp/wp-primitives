<?php
/**
 * Abstract Feature.
 *
 * Base class for a service that runs only when its feature flag is enabled.
 * Each feature extends a consumer-defined intermediate class whose get_feature_registry() returns the shared FeatureSelector.
 *
 * The constructor adds the feature to the registry, which the settings page reads.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Utils\FeatureSelector;

/**
 * Class AbstractFeature
 *
 * @since 1.0.0
 */
abstract class AbstractFeature implements ConditionallyRegistrable {
	/**
	 * Return the slug that identifies this feature flag.
	 *
	 * @return string Feature flag slug.
	 */
	abstract protected function get_slug(): string;

	/**
	 * Return the shared FeatureSelector registry for this consumer.
	 *
	 * @return FeatureSelector Shared registry instance.
	 */
	abstract protected function get_feature_registry(): FeatureSelector;

	/**
	 * Return the human-readable label shown on the settings page.
	 *
	 * Defaults to the slug in title case, so "author-bio" becomes "Author Bio".
	 *
	 * @return string Display name.
	 */
	protected function get_name(): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $this->get_slug() ) );
	}

	/**
	 * Return the description shown beneath the label on the settings page.
	 *
	 * Defaults to empty. Override to describe what enabling this feature does.
	 *
	 * @return string Description.
	 */
	protected function get_description(): string {
		return '';
	}

	/**
	 * Constructor. Self-registers the flag into the shared registry with metadata.
	 *
	 * Name and description are passed as closures, so their translations load when
	 * the settings page reads them rather than at construction, which can run
	 * before init.
	 */
	public function __construct() {
		$this->get_feature_registry()->register(
			[
				$this->get_slug() => [
					'name'        => fn (): string => $this->get_name(),
					'description' => fn (): string => $this->get_description(),
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function can_register(): bool {
		return $this->get_feature_registry()->is_enabled( $this->get_slug() );
	}
}
